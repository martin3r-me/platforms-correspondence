<?php

namespace Platform\Correspondence\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Correspondence\Models\CorrespondenceItem;
use Platform\Correspondence\Models\CorrespondenceThread;
use Platform\Correspondence\Tools\Concerns\ResolvesCorrespondenceTeam;

class CorrespondenceOverviewTool implements ToolContract, ToolMetadataContract
{
    use ResolvesCorrespondenceTeam;

    public function getName(): string
    {
        return 'correspondence.overview.GET';
    }

    public function getDescription(): string
    {
        return 'GET /correspondence/overview - Zeigt Übersicht und Zähler für das Korrespondenz-Modul: Threads nach Status (inbox, assigned, archived), ungelesene Items, Items nach Typ (email, letter).';
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
            ],
            'required' => [],
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

            $threads = CorrespondenceThread::forTeam($teamId);
            $items = CorrespondenceItem::forTeam($teamId);

            return ToolResult::success([
                'team_id' => $teamId,
                'threads' => [
                    'total' => (clone $threads)->count(),
                    'inbox' => (clone $threads)->inbox()->count(),
                    'assigned' => (clone $threads)->assigned()->count(),
                    'archived' => (clone $threads)->archived()->count(),
                ],
                'items' => [
                    'total' => (clone $items)->count(),
                    'unread' => (clone $items)->unread()->count(),
                    'emails' => (clone $items)->emails()->count(),
                    'letters' => (clone $items)->letters()->count(),
                ],
                'related_tools' => [
                    'threads' => 'correspondence.threads.GET',
                    'items' => 'correspondence.items.GET',
                    'import_email' => 'correspondence.items.import_email.POST',
                    'import_letter' => 'correspondence.items.import_letter.POST',
                    'assign' => 'correspondence.threads.assign.POST',
                    'search' => 'correspondence.search.GET',
                ],
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden der Übersicht: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => true,
            'category' => 'overview',
            'tags' => ['correspondence', 'overview', 'stats'],
            'risk_level' => 'safe',
            'requires_auth' => true,
            'requires_team' => true,
            'idempotent' => true,
        ];
    }
}
