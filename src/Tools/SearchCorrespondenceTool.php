<?php

namespace Platform\Correspondence\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardGetOperations;
use Platform\Correspondence\Models\CorrespondenceItem;
use Platform\Correspondence\Models\CorrespondenceThread;
use Platform\Correspondence\Tools\Concerns\ResolvesCorrespondenceTeam;

class SearchCorrespondenceTool implements ToolContract, ToolMetadataContract
{
    use ResolvesCorrespondenceTeam, HasStandardGetOperations;

    public function getName(): string
    {
        return 'correspondence.search.GET';
    }

    public function getDescription(): string
    {
        return 'GET /correspondence/search - Suche über Korrespondenz: subject, sender, recipient. Gibt Threads mit passenden Items zurück.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'team_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Team-ID. Default: aktuelles Team aus Kontext.',
                ],
                'query' => [
                    'type' => 'string',
                    'description' => 'Suchbegriff (erforderlich). Durchsucht Thread-Betreff, Absender- und Empfänger-Namen/Email.',
                ],
                'limit' => [
                    'type' => 'integer',
                    'description' => 'Optional: Max. Ergebnisse. Default: 20.',
                ],
            ],
            'required' => ['query'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $resolved = $this->resolveTeam($arguments, $context);
            if ($resolved['error']) {
                return $resolved['error'];
            }
            $teamId = $resolved['team_id'];

            $search = trim($arguments['query'] ?? '');
            if ($search === '') {
                return ToolResult::error('VALIDATION_ERROR', 'Suchbegriff ist erforderlich.');
            }

            $limit = min((int) ($arguments['limit'] ?? 20), 100);

            // Search threads by subject
            $threadsBySubject = CorrespondenceThread::forTeam($teamId)
                ->where('subject_normalized', 'like', '%' . mb_strtolower($search) . '%')
                ->withCount('items')
                ->orderByDesc('latest_item_at')
                ->limit($limit)
                ->get();

            // Search items by sender/recipient
            $itemsByContact = CorrespondenceItem::forTeam($teamId)
                ->where(function ($q) use ($search) {
                    $q->where('sender_name', 'like', '%' . $search . '%')
                      ->orWhere('sender_email', 'like', '%' . $search . '%')
                      ->orWhere('recipient_name', 'like', '%' . $search . '%')
                      ->orWhere('recipient_email', 'like', '%' . $search . '%');
                })
                ->with('thread:id,subject,status')
                ->orderByDesc('created_at')
                ->limit($limit)
                ->get();

            // Merge thread IDs
            $threadIds = $threadsBySubject->pluck('id')
                ->merge($itemsByContact->pluck('thread_id'))
                ->unique()
                ->values();

            $threads = CorrespondenceThread::forTeam($teamId)
                ->whereIn('id', $threadIds)
                ->withCount(['items', 'items as unread_items_count' => fn($q) => $q->unread()])
                ->orderByDesc('latest_item_at')
                ->limit($limit)
                ->get()
                ->map(fn($t) => [
                    'id' => $t->id,
                    'uuid' => $t->uuid,
                    'subject' => $t->subject,
                    'status' => $t->status,
                    'item_count' => $t->item_count,
                    'unread_items_count' => $t->unread_items_count,
                    'latest_item_at' => $t->latest_item_at?->toIso8601String(),
                    'created_at' => $t->created_at->toIso8601String(),
                ])->toArray();

            return ToolResult::success([
                'threads' => $threads,
                'count' => count($threads),
                'query' => $search,
                'team_id' => $teamId,
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler bei der Suche: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => true,
            'category' => 'query',
            'tags' => ['correspondence', 'search'],
            'risk_level' => 'safe',
            'requires_auth' => true,
            'requires_team' => true,
            'idempotent' => true,
        ];
    }
}
