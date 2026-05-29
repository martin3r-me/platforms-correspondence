<?php

namespace Platform\Correspondence\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Correspondence\Models\CorrespondenceThread;
use Platform\Correspondence\Tools\Concerns\ResolvesCorrespondenceTeam;

class DeleteThreadTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesCorrespondenceTeam;

    public function getName(): string
    {
        return 'correspondence.threads.DELETE';
    }

    public function getDescription(): string
    {
        return 'DELETE /correspondence/threads/{id} - Soft-deletet einen Thread inkl. aller zugehörigen Items. ERFORDERLICH: thread_id.';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                'team_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Team-ID. Default: aktuelles Team aus Kontext.',
                ],
                'thread_id' => [
                    'type' => 'integer',
                    'description' => 'ID des Threads (ERFORDERLICH).',
                ],
            ],
            'required' => ['thread_id'],
        ]);
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $resolved = $this->resolveTeam($arguments, $context);
            if ($resolved['error']) {
                return $resolved['error'];
            }
            $teamId = (int) $resolved['team_id'];

            $found = $this->validateAndFindModel(
                $arguments,
                $context,
                'thread_id',
                CorrespondenceThread::class,
                'NOT_FOUND',
                'Thread nicht gefunden.'
            );
            if ($found['error']) {
                return $found['error'];
            }

            /** @var CorrespondenceThread $thread */
            $thread = $found['model'];

            if ((int) $thread->team_id !== $teamId) {
                return ToolResult::error('ACCESS_DENIED', 'Kein Zugriff auf diesen Thread.');
            }

            $subject = $thread->subject;
            $itemCount = $thread->items()->count();

            // Soft-delete items first, then thread
            $thread->items()->delete();
            $thread->delete();

            return ToolResult::success([
                'id' => $thread->id,
                'subject' => $subject,
                'items_deleted' => $itemCount,
                'message' => "Thread \"{$subject}\" mit {$itemCount} Items gelöscht.",
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Löschen: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => false,
            'category' => 'action',
            'tags' => ['correspondence', 'threads', 'delete'],
            'risk_level' => 'write',
            'requires_auth' => true,
            'requires_team' => true,
            'idempotent' => true,
        ];
    }
}
