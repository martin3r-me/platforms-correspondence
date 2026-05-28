<?php

namespace Platform\Correspondence\Livewire;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Platform\Correspondence\Models\CorrespondenceThread;
use Platform\Organization\Services\EntityDimensionBridge;
use Platform\Organization\Models\OrganizationEntity;
use Livewire\Attributes\On;

class Sidebar extends Component
{
    public bool $showAllThreads = false;

    public function mount()
    {
        $this->showAllThreads = false;
    }

    #[On('updateSidebar')]
    public function updateSidebar()
    {
    }

    public function toggleShowAllThreads()
    {
        $this->showAllThreads = !$this->showAllThreads;
    }

    public function render()
    {
        $user = auth()->user();
        $teamId = $user?->currentTeam?->id ?? null;

        if (!$user || !$teamId) {
            return view('correspondence::livewire.sidebar', [
                'inboxCount' => 0,
                'entityTypeGroups' => collect(),
                'unlinkedThreads' => collect(),
                'hasMoreThreads' => false,
            ]);
        }

        $inboxCount = CorrespondenceThread::forTeam($teamId)->inbox()->count();

        // 1. Threads laden
        $activeThreads = CorrespondenceThread::forTeam($teamId)
            ->whereIn('status', ['inbox', 'assigned'])
            ->orderByDesc('latest_item_at')
            ->get();

        $allThreads = CorrespondenceThread::forTeam($teamId)
            ->orderByDesc('latest_item_at')
            ->get();

        $threadsToShow = $this->showAllThreads ? $allThreads : $activeThreads;
        $hasMoreThreads = $allThreads->count() > $activeThreads->count();

        // 2. Entity-Verknüpfungen laden via DimensionLink
        $threadIds = $threadsToShow->pluck('id')->toArray();

        $entityThreadMap = []; // entity_id => [thread_ids]
        $linkedThreadIds = [];

        try {
            $contextMorphTypes = ['correspondence_thread', CorrespondenceThread::class];
            $entityLinks = EntityDimensionBridge::linksForLinkables($contextMorphTypes, $threadIds);

            foreach ($entityLinks as $link) {
                $entityId = $link->entity_id;
                $threadId = $link->linkable_id;
                $entityThreadMap[$entityId][] = $threadId;
                $linkedThreadIds[] = $threadId;
            }

            foreach ($entityThreadMap as $entityId => $tids) {
                $entityThreadMap[$entityId] = array_unique($tids);
            }
            $linkedThreadIds = array_unique($linkedThreadIds);

            // 2c. Aufwärts-Traversierung: Ancestors ins Entity-Set aufnehmen
            $directEntityIds = array_keys($entityThreadMap);
            if (!empty($directEntityIds)) {
                $directEntities = OrganizationEntity::with(['allParents.type'])
                    ->whereIn('id', $directEntityIds)
                    ->get()
                    ->keyBy('id');

                foreach ($directEntities as $entityId => $entity) {
                    $ancestor = $entity->allParents;
                    while ($ancestor) {
                        if (!isset($entityThreadMap[$ancestor->id])) {
                            $entityThreadMap[$ancestor->id] = [];
                        }
                        $ancestor = $ancestor->allParents;
                    }
                }
            }

            // 3. Gruppieren: EntityType → Entity-Baum → Threads
            $entityTypeGroups = collect();
            $entityIds = array_keys($entityThreadMap);

            if (!empty($entityIds)) {
                $entities = OrganizationEntity::with('type')
                    ->whereIn('id', $entityIds)
                    ->get()
                    ->keyBy('id');

                $entityChildrenMap = [];
                $rootEntityIds = [];

                foreach ($entities as $entity) {
                    $parentId = $entity->parent_entity_id;
                    if ($parentId && $entities->has($parentId)) {
                        $entityChildrenMap[$parentId][] = $entity->id;
                    } else {
                        $rootEntityIds[] = $entity->id;
                    }
                }

                // Rekursiver Baum-Builder
                $buildTree = function (int $entityId) use (&$buildTree, $entities, $entityChildrenMap, $entityThreadMap, $threadsToShow): ?array {
                    $entity = $entities->get($entityId);
                    if (!$entity) {
                        return null;
                    }

                    $childIds = $entityChildrenMap[$entityId] ?? [];
                    $childNodes = collect($childIds)
                        ->map(fn ($childId) => $buildTree($childId))
                        ->filter();

                    $childrenByType = $childNodes
                        ->groupBy(fn ($child) => $child['type_id'])
                        ->map(function ($group) use ($entities) {
                            $firstChild = $group->first();
                            $typeEntity = $entities->get($firstChild['entity_id']);
                            $type = $typeEntity?->type;

                            return [
                                'type_id' => $firstChild['type_id'],
                                'type_name' => $type?->name ?? 'Sonstige',
                                'type_icon' => $type?->icon ?? null,
                                'sort_order' => $type?->sort_order ?? 999,
                                'children' => $group->sortBy('entity_name')->values(),
                            ];
                        })
                        ->sortBy('sort_order')
                        ->values();

                    $threads = collect($entityThreadMap[$entityId] ?? [])
                        ->map(fn ($tid) => $threadsToShow->firstWhere('id', $tid))
                        ->filter()
                        ->values();

                    $totalThreads = $threads->count();
                    foreach ($childNodes as $child) {
                        $totalThreads += $child['total_threads'];
                    }

                    if ($totalThreads === 0) {
                        return null;
                    }

                    return [
                        'entity_id' => $entityId,
                        'entity_name' => $entity->name,
                        'type_id' => $entity->type?->id,
                        'threads' => $threads,
                        'children_by_type' => $childrenByType,
                        'total_threads' => $totalThreads,
                    ];
                };

                // Root-Entities nach Typ gruppieren
                $groupedByType = [];
                foreach ($rootEntityIds as $entityId) {
                    $entity = $entities->get($entityId);
                    if (!$entity || !$entity->type) {
                        continue;
                    }

                    $tree = $buildTree($entityId);
                    if (!$tree) {
                        continue;
                    }

                    $typeId = $entity->type->id;
                    if (!isset($groupedByType[$typeId])) {
                        $groupedByType[$typeId] = [
                            'type_id' => $typeId,
                            'type_name' => $entity->type->name,
                            'type_icon' => $entity->type->icon,
                            'sort_order' => $entity->type->sort_order ?? 999,
                            'entities' => [],
                        ];
                    }
                    $groupedByType[$typeId]['entities'][] = $tree;
                }

                $entityTypeGroups = collect($groupedByType)
                    ->sortBy('sort_order')
                    ->map(function ($group) {
                        $group['entities'] = collect($group['entities'])
                            ->sortBy('entity_name')
                            ->values();
                        return $group;
                    })
                    ->values();
            }
        } catch (\Throwable $e) {
            // Organization module not loaded
            $entityTypeGroups = collect();
        }

        // 4. Unverknüpfte Threads
        $unlinkedThreads = $threadsToShow->filter(function ($thread) use ($linkedThreadIds) {
            return !in_array($thread->id, $linkedThreadIds);
        })->values();

        return view('correspondence::livewire.sidebar', [
            'inboxCount' => $inboxCount,
            'entityTypeGroups' => $entityTypeGroups,
            'unlinkedThreads' => $unlinkedThreads,
            'hasMoreThreads' => $hasMoreThreads,
        ]);
    }
}
