<?php

namespace Platform\Correspondence\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Correspondence\Models\CorrespondenceThread;
use Platform\Correspondence\Tools\Concerns\ResolvesCorrespondenceTeam;

class AssignThreadTool implements ToolContract, ToolMetadataContract
{
    use ResolvesCorrespondenceTeam;

    public function getName(): string
    {
        return 'correspondence.threads.assign.POST';
    }

    public function getDescription(): string
    {
        return 'POST /correspondence/threads/assign - Weist einen Thread einer Organization Entity zu via DimensionLink. Setzt Thread-Status auf "assigned".';
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
                'thread_id' => [
                    'type' => 'integer',
                    'description' => 'Thread-ID (erforderlich).',
                ],
                'entity_id' => [
                    'type' => 'integer',
                    'description' => 'Organization Entity-ID (erforderlich).',
                ],
            ],
            'required' => ['thread_id', 'entity_id'],
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

            if (empty($arguments['thread_id'])) {
                return ToolResult::error('VALIDATION_ERROR', 'thread_id ist erforderlich.');
            }
            if (empty($arguments['entity_id'])) {
                return ToolResult::error('VALIDATION_ERROR', 'entity_id ist erforderlich.');
            }

            $thread = CorrespondenceThread::forTeam($teamId)->find($arguments['thread_id']);
            if (!$thread) {
                return ToolResult::error('NOT_FOUND', 'Thread nicht gefunden.');
            }

            $entityId = (int) $arguments['entity_id'];

            // Create DimensionLink via EntityDimensionBridge
            try {
                $link = \Platform\Organization\Services\EntityDimensionBridge::createLink(
                    $entityId,
                    'correspondence_thread',
                    $thread->id
                );

                if (!$link) {
                    return ToolResult::error('LINK_ERROR', 'DimensionLink konnte nicht erstellt werden. Prüfe, ob die Entity existiert.');
                }
            } catch (\Throwable $e) {
                return ToolResult::error('LINK_ERROR', 'Fehler beim Erstellen des DimensionLinks: ' . $e->getMessage());
            }

            // Update thread status
            $thread->update(['status' => 'assigned']);

            return ToolResult::success([
                'thread_id' => $thread->id,
                'entity_id' => $entityId,
                'status' => 'assigned',
                'message' => "Thread #{$thread->id} wurde Entity #{$entityId} zugewiesen und Status auf 'assigned' gesetzt.",
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Zuweisen: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => false,
            'category' => 'action',
            'tags' => ['correspondence', 'thread', 'assign', 'entity'],
            'risk_level' => 'write',
            'requires_auth' => true,
            'requires_team' => true,
            'idempotent' => false,
        ];
    }
}
