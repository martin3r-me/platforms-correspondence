<?php

namespace Platform\Correspondence\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Correspondence\Models\CorrespondenceThread;
use Platform\Correspondence\Services\ThreadResolver;
use Platform\Correspondence\Tools\Concerns\ResolvesCorrespondenceTeam;

class SplitThreadTool implements ToolContract, ToolMetadataContract
{
    use ResolvesCorrespondenceTeam;

    public function getName(): string
    {
        return 'correspondence.threads.split.POST';
    }

    public function getDescription(): string
    {
        return 'POST /correspondence/threads/split - Lagert Items aus einem Thread in einen neuen Thread aus.';
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
                    'description' => 'Source-Thread-ID (erforderlich).',
                ],
                'item_ids' => [
                    'type' => 'array',
                    'items' => ['type' => 'integer'],
                    'description' => 'Array von Item-IDs die ausgelagert werden sollen (erforderlich).',
                ],
                'new_subject' => [
                    'type' => 'string',
                    'description' => 'Optional: Betreff für den neuen Thread. Default: Betreff des Source-Threads.',
                ],
            ],
            'required' => ['thread_id', 'item_ids'],
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
            if (empty($arguments['item_ids']) || !is_array($arguments['item_ids'])) {
                return ToolResult::error('VALIDATION_ERROR', 'item_ids muss ein nicht-leeres Array sein.');
            }

            $source = CorrespondenceThread::forTeam($teamId)->find($arguments['thread_id']);
            if (!$source) {
                return ToolResult::error('NOT_FOUND', 'Source-Thread nicht gefunden.');
            }

            $itemIds = array_map('intval', $arguments['item_ids']);
            $newSubject = $arguments['new_subject'] ?? null;

            $resolver = new ThreadResolver();
            $newThread = $resolver->splitItems($source, $itemIds, $newSubject);

            return ToolResult::success([
                'source_thread_id' => $source->id,
                'new_thread_id' => $newThread->id,
                'new_thread_uuid' => $newThread->uuid,
                'new_thread_subject' => $newThread->subject,
                'items_moved' => $newThread->item_count,
                'message' => "{$newThread->item_count} Item(s) in neuen Thread #{$newThread->id} ausgelagert.",
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Split: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => false,
            'category' => 'action',
            'tags' => ['correspondence', 'thread', 'split'],
            'risk_level' => 'write',
            'requires_auth' => true,
            'requires_team' => true,
            'idempotent' => false,
        ];
    }
}
