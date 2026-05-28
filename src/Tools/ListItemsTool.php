<?php

namespace Platform\Correspondence\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardGetOperations;
use Platform\Correspondence\Models\CorrespondenceItem;
use Platform\Correspondence\Tools\Concerns\ResolvesCorrespondenceTeam;

class ListItemsTool implements ToolContract, ToolMetadataContract
{
    use ResolvesCorrespondenceTeam, HasStandardGetOperations;

    public function getName(): string
    {
        return 'correspondence.items.GET';
    }

    public function getDescription(): string
    {
        return 'GET /correspondence/items - Listet Korrespondenz-Items auf. Filterbar nach thread_id, type (email/letter), status (pending/processed/failed), is_read. Paginiert.';
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
                    'thread_id' => [
                        'type' => 'integer',
                        'description' => 'Optional: Filter nach Thread-ID.',
                    ],
                    'type' => [
                        'type' => 'string',
                        'enum' => ['email', 'letter'],
                        'description' => 'Optional: Filter nach Typ.',
                    ],
                    'status' => [
                        'type' => 'string',
                        'enum' => ['pending', 'processed', 'failed'],
                        'description' => 'Optional: Filter nach Status.',
                    ],
                    'is_read' => [
                        'type' => 'boolean',
                        'description' => 'Optional: Filter nach Gelesen-Status.',
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

            $query = CorrespondenceItem::forTeam($teamId)->with('thread:id,subject,status');

            if (!empty($arguments['thread_id'])) {
                $query->where('thread_id', (int) $arguments['thread_id']);
            }
            if (!empty($arguments['type'])) {
                $query->where('type', $arguments['type']);
            }
            if (!empty($arguments['status'])) {
                $query->where('status', $arguments['status']);
            }
            if (isset($arguments['is_read'])) {
                $query->where('is_read', (bool) $arguments['is_read']);
            }

            $this->applyStandardFilters($query, $arguments, [
                'type', 'status', 'direction', 'is_read', 'provider', 'correspondence_date', 'created_at',
            ]);
            $this->applyStandardSearch($query, $arguments, ['sender_name', 'sender_email', 'recipient_name', 'recipient_email']);
            $this->applyStandardSort($query, $arguments, [
                'correspondence_date', 'created_at', 'is_read', 'type',
            ], 'created_at', 'desc');

            $result = $this->applyStandardPaginationResult($query, $arguments);

            $items = $result['data']->map(fn($item) => [
                'id' => $item->id,
                'uuid' => $item->uuid,
                'thread_id' => $item->thread_id,
                'thread_subject' => $item->thread?->subject,
                'type' => $item->type,
                'status' => $item->status,
                'direction' => $item->direction,
                'sender_name' => $item->sender_name,
                'sender_email' => $item->sender_email,
                'recipient_name' => $item->recipient_name,
                'recipient_email' => $item->recipient_email,
                'correspondence_date' => $item->correspondence_date?->toDateString(),
                'is_read' => $item->is_read,
                'provider' => $item->provider,
                'created_at' => $item->created_at->toIso8601String(),
            ])->toArray();

            return ToolResult::success([
                'items' => $items,
                'pagination' => $result['pagination'],
                'team_id' => $teamId,
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden der Items: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => true,
            'category' => 'query',
            'tags' => ['correspondence', 'items', 'list'],
            'risk_level' => 'safe',
            'requires_auth' => true,
            'requires_team' => true,
            'idempotent' => true,
        ];
    }
}
