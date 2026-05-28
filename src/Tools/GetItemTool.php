<?php

namespace Platform\Correspondence\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Correspondence\Models\CorrespondenceItem;
use Platform\Correspondence\Tools\Concerns\ResolvesCorrespondenceTeam;

class GetItemTool implements ToolContract, ToolMetadataContract
{
    use ResolvesCorrespondenceTeam;

    public function getName(): string
    {
        return 'correspondence.items.detail.GET';
    }

    public function getDescription(): string
    {
        return 'GET /correspondence/items/{id} - Ruft ein einzelnes Korrespondenz-Item mit Body und Metadata ab.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'id' => [
                    'type' => 'integer',
                    'description' => 'Item-ID (erforderlich).',
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
                return ToolResult::error('VALIDATION_ERROR', 'Item-ID ist erforderlich.');
            }

            $item = CorrespondenceItem::forTeam($teamId)
                ->with('thread:id,uuid,subject,status')
                ->find($arguments['id']);

            if (!$item) {
                return ToolResult::error('NOT_FOUND', 'Item nicht gefunden.');
            }

            return ToolResult::success([
                'id' => $item->id,
                'uuid' => $item->uuid,
                'thread_id' => $item->thread_id,
                'thread_subject' => $item->thread?->subject,
                'thread_status' => $item->thread?->status,
                'type' => $item->type,
                'status' => $item->status,
                'direction' => $item->direction,
                'sender_name' => $item->sender_name,
                'sender_email' => $item->sender_email,
                'recipient_name' => $item->recipient_name,
                'recipient_email' => $item->recipient_email,
                'body_text' => $item->body_text,
                'body_html' => $item->body_html,
                'metadata' => $item->metadata,
                'provider' => $item->provider,
                'provider_id' => $item->provider_id,
                'correspondence_date' => $item->correspondence_date?->toDateString(),
                'is_read' => $item->is_read,
                'created_at' => $item->created_at->toIso8601String(),
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden des Items: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => true,
            'category' => 'query',
            'tags' => ['correspondence', 'item', 'detail'],
            'risk_level' => 'safe',
            'requires_auth' => true,
            'requires_team' => true,
            'idempotent' => true,
        ];
    }
}
