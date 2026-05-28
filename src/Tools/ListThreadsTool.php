<?php

namespace Platform\Correspondence\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardGetOperations;
use Platform\Correspondence\Models\CorrespondenceThread;
use Platform\Correspondence\Tools\Concerns\ResolvesCorrespondenceTeam;

class ListThreadsTool implements ToolContract, ToolMetadataContract
{
    use ResolvesCorrespondenceTeam, HasStandardGetOperations;

    public function getName(): string
    {
        return 'correspondence.threads.GET';
    }

    public function getDescription(): string
    {
        return 'GET /correspondence/threads - Listet Korrespondenz-Threads auf. Filterbar nach Status (inbox, assigned, archived) und Typ (email, letter). Paginiert.';
    }

    public function getSchema(): array
    {
        return $this->mergeSchemas(
            $this->getStandardGetSchema(),
            [
                'properties' => [
                    'team_id' => [
                        'type' => 'integer',
                        'description' => 'Optional: Team-ID. Default: aktuelles Team aus Kontext.',
                    ],
                    'status' => [
                        'type' => 'string',
                        'enum' => ['inbox', 'assigned', 'archived'],
                        'description' => 'Optional: Filter nach Thread-Status.',
                    ],
                    'type' => [
                        'type' => 'string',
                        'enum' => ['email', 'letter'],
                        'description' => 'Optional: Filter nach Item-Typ (zeigt nur Threads die mindestens ein Item dieses Typs haben).',
                    ],
                ],
            ]
        );
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $resolved = $this->resolveTeam($arguments, $context);
            if ($resolved['error']) {
                return $resolved['error'];
            }
            $teamId = $resolved['team_id'];

            $query = CorrespondenceThread::forTeam($teamId)
                ->withCount(['items', 'items as unread_items_count' => fn($q) => $q->unread()]);

            if (!empty($arguments['status'])) {
                $query->where('status', $arguments['status']);
            }

            if (!empty($arguments['type'])) {
                $query->whereHas('items', fn($q) => $q->where('type', $arguments['type']));
            }

            $this->applyStandardFilters($query, $arguments, [
                'status', 'subject_normalized', 'ms365_conversation_id', 'created_at', 'latest_item_at',
            ]);
            $this->applyStandardSearch($query, $arguments, ['subject_normalized']);
            $this->applyStandardSort($query, $arguments, [
                'latest_item_at', 'created_at', 'item_count', 'subject_normalized',
            ], 'latest_item_at', 'desc');

            $result = $this->applyStandardPaginationResult($query, $arguments);

            $threads = $result['data']->map(fn($t) => [
                'id' => $t->id,
                'uuid' => $t->uuid,
                'subject' => $t->subject,
                'status' => $t->status,
                'item_count' => $t->item_count,
                'unread_items_count' => $t->unread_items_count,
                'latest_item_at' => $t->latest_item_at?->toIso8601String(),
                'ms365_conversation_id' => $t->ms365_conversation_id,
                'created_at' => $t->created_at->toIso8601String(),
            ])->toArray();

            return ToolResult::success([
                'threads' => $threads,
                'pagination' => $result['pagination'],
                'team_id' => $teamId,
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden der Threads: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => true,
            'category' => 'query',
            'tags' => ['correspondence', 'threads', 'list'],
            'risk_level' => 'safe',
            'requires_auth' => true,
            'requires_team' => true,
            'idempotent' => true,
        ];
    }
}
