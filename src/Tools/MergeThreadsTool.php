<?php

namespace Platform\Correspondence\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Correspondence\Models\CorrespondenceThread;
use Platform\Correspondence\Services\ThreadResolver;
use Platform\Correspondence\Tools\Concerns\ResolvesCorrespondenceTeam;

class MergeThreadsTool implements ToolContract, ToolMetadataContract
{
    use ResolvesCorrespondenceTeam;

    public function getName(): string
    {
        return 'correspondence.threads.merge.POST';
    }

    public function getDescription(): string
    {
        return 'POST /correspondence/threads/merge - Führt Source-Threads in einen Target-Thread zusammen. Items werden verschoben, Source-Threads gelöscht.';
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
                'target_thread_id' => [
                    'type' => 'integer',
                    'description' => 'Ziel-Thread-ID (erforderlich).',
                ],
                'source_thread_ids' => [
                    'type' => 'array',
                    'items' => ['type' => 'integer'],
                    'description' => 'Array von Source-Thread-IDs die in den Target zusammengeführt werden (erforderlich).',
                ],
            ],
            'required' => ['target_thread_id', 'source_thread_ids'],
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

            if (empty($arguments['target_thread_id'])) {
                return ToolResult::error('VALIDATION_ERROR', 'target_thread_id ist erforderlich.');
            }
            if (empty($arguments['source_thread_ids']) || !is_array($arguments['source_thread_ids'])) {
                return ToolResult::error('VALIDATION_ERROR', 'source_thread_ids muss ein nicht-leeres Array sein.');
            }

            $target = CorrespondenceThread::forTeam($teamId)->find($arguments['target_thread_id']);
            if (!$target) {
                return ToolResult::error('NOT_FOUND', 'Ziel-Thread nicht gefunden.');
            }

            $sourceIds = array_map('intval', $arguments['source_thread_ids']);

            // Verify all source threads exist and belong to same team
            $sources = CorrespondenceThread::forTeam($teamId)
                ->whereIn('id', $sourceIds)
                ->get();

            if ($sources->count() !== count($sourceIds)) {
                return ToolResult::error('NOT_FOUND', 'Nicht alle Source-Threads gefunden oder gehören nicht zum selben Team.');
            }

            // Prevent merging target into itself
            $sourceIds = array_filter($sourceIds, fn($id) => $id !== $target->id);
            if (empty($sourceIds)) {
                return ToolResult::error('VALIDATION_ERROR', 'Source-Threads dürfen nicht den Target-Thread enthalten.');
            }

            $resolver = new ThreadResolver();
            $resolver->mergeThreads($target, $sourceIds);

            $target->refresh();

            return ToolResult::success([
                'target_thread_id' => $target->id,
                'merged_source_count' => count($sourceIds),
                'new_item_count' => $target->item_count,
                'message' => count($sourceIds) . " Thread(s) in Thread #{$target->id} zusammengeführt.",
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Merge: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => false,
            'category' => 'action',
            'tags' => ['correspondence', 'thread', 'merge'],
            'risk_level' => 'write',
            'requires_auth' => true,
            'requires_team' => true,
            'idempotent' => false,
        ];
    }
}
