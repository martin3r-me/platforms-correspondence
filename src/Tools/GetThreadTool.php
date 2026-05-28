<?php

namespace Platform\Correspondence\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Correspondence\Models\CorrespondenceThread;
use Platform\Correspondence\Tools\Concerns\ResolvesCorrespondenceTeam;

class GetThreadTool implements ToolContract, ToolMetadataContract
{
    use ResolvesCorrespondenceTeam;

    public function getName(): string
    {
        return 'correspondence.threads.detail.GET';
    }

    public function getDescription(): string
    {
        return 'GET /correspondence/threads/{id} - Ruft einen einzelnen Thread mit allen Items und Entity-Links ab.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'id' => [
                    'type' => 'integer',
                    'description' => 'Thread-ID (erforderlich).',
                ],
                'team_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Team-ID. Default: aktuelles Team aus Kontext.',
                ],
            ],
            'required' => ['id'],
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

            if (empty($arguments['id'])) {
                return ToolResult::error('VALIDATION_ERROR', 'Thread-ID ist erforderlich.');
            }

            $thread = CorrespondenceThread::forTeam($teamId)
                ->with(['items' => fn($q) => $q->orderBy('correspondence_date')->orderBy('created_at')])
                ->withCount(['items', 'items as unread_items_count' => fn($q) => $q->unread()])
                ->find($arguments['id']);

            if (!$thread) {
                return ToolResult::error('NOT_FOUND', 'Thread nicht gefunden.');
            }

            // Entity-Links laden (loose coupling)
            $entityLinks = [];
            try {
                $links = \Platform\Organization\Services\EntityDimensionBridge::linksForLinkables(
                    ['correspondence_thread'],
                    [$thread->id]
                );
                $entityLinks = $links->map(fn($l) => [
                    'entity_id' => $l->entity_id,
                    'entity_name' => $l->entity?->name,
                    'entity_type' => $l->entity?->type?->name,
                ])->toArray();
            } catch (\Throwable) {
                // Organization module not loaded
            }

            $items = $thread->items->map(fn($item) => [
                'id' => $item->id,
                'uuid' => $item->uuid,
                'type' => $item->type,
                'status' => $item->status,
                'direction' => $item->direction,
                'sender_name' => $item->sender_name,
                'sender_email' => $item->sender_email,
                'recipient_name' => $item->recipient_name,
                'recipient_email' => $item->recipient_email,
                'body_text' => $item->body_text,
                'correspondence_date' => $item->correspondence_date?->toDateString(),
                'is_read' => $item->is_read,
                'provider' => $item->provider,
                'created_at' => $item->created_at->toIso8601String(),
            ])->toArray();

            return ToolResult::success([
                'id' => $thread->id,
                'uuid' => $thread->uuid,
                'subject' => $thread->subject,
                'status' => $thread->status,
                'item_count' => $thread->item_count,
                'unread_items_count' => $thread->unread_items_count,
                'latest_item_at' => $thread->latest_item_at?->toIso8601String(),
                'ms365_conversation_id' => $thread->ms365_conversation_id,
                'entity_links' => $entityLinks,
                'items' => $items,
                'created_at' => $thread->created_at->toIso8601String(),
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden des Threads: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => true,
            'category' => 'query',
            'tags' => ['correspondence', 'thread', 'detail'],
            'risk_level' => 'safe',
            'requires_auth' => true,
            'requires_team' => true,
            'idempotent' => true,
        ];
    }
}
