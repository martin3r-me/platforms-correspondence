<?php

namespace Platform\Correspondence\Organization;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Platform\Correspondence\Models\CorrespondenceItem;
use Platform\Organization\Contracts\EntityLinkProvider;
use Platform\Organization\Contracts\HasMetricDefinitions;

class CorrespondenceEntityLinkProvider implements EntityLinkProvider, HasMetricDefinitions
{
    public function morphAliases(): array
    {
        return ['correspondence_thread'];
    }

    public function linkTypeConfig(): array
    {
        return [
            'correspondence_thread' => [
                'label' => 'Korrespondenz',
                'singular' => 'Korrespondenz-Thread',
                'icon' => 'envelope',
                'route' => 'correspondence.threads.show',
            ],
        ];
    }

    public function applyEagerLoading(Builder $query, string $morphAlias, string $fqcn): void
    {
        if ($morphAlias === 'correspondence_thread') {
            $query->withCount([
                'items',
                'items as unread_items_count' => fn($q) => $q->where('is_read', false),
            ]);
        }
    }

    public function extractMetadata(string $morphAlias, mixed $model): array
    {
        if ($morphAlias !== 'correspondence_thread') {
            return [];
        }

        return [
            'subject' => $model->subject,
            'status' => $model->status,
            'item_count' => $model->items_count ?? $model->item_count,
            'unread_count' => $model->unread_items_count ?? 0,
            'latest_item_at' => $model->latest_item_at?->format('d.m.Y'),
        ];
    }

    public function metadataDisplayRules(): array
    {
        return [
            'correspondence_thread' => [
                ['field' => 'status', 'format' => 'text'],
                ['field' => 'item_count', 'format' => 'text', 'suffix' => 'Items'],
                ['field' => 'unread_count', 'format' => 'text', 'suffix' => 'ungelesen'],
                ['field' => 'latest_item_at', 'format' => 'text'],
            ],
        ];
    }

    public function timeTrackableCascades(): array
    {
        return [];
    }

    public function activityChildren(string $morphAlias, array $linkableIds): array
    {
        return [];
    }

    public function metrics(string $morphAlias, array $linksByEntity): array
    {
        if ($morphAlias !== 'correspondence_thread') {
            return [];
        }

        $allIds = [];
        foreach ($linksByEntity as $ids) {
            $allIds = array_merge($allIds, $ids);
        }
        $allIds = array_values(array_unique($allIds));

        if (empty($allIds)) {
            return [];
        }

        // Count items per thread
        $itemCounts = CorrespondenceItem::whereIn('thread_id', $allIds)
            ->selectRaw('thread_id, COUNT(*) as total, SUM(CASE WHEN is_read = 0 THEN 1 ELSE 0 END) as unread')
            ->groupBy('thread_id')
            ->get()
            ->keyBy('thread_id');

        // Count items this week per thread
        $weekStart = Carbon::now()->startOfWeek();
        $thisWeek = CorrespondenceItem::whereIn('thread_id', $allIds)
            ->where('created_at', '>=', $weekStart)
            ->selectRaw('thread_id, COUNT(*) as cnt')
            ->groupBy('thread_id')
            ->get()
            ->keyBy('thread_id');

        $result = [];
        foreach ($linksByEntity as $entityId => $ids) {
            $total = 0;
            $unread = 0;
            $week = 0;
            foreach ($ids as $id) {
                $counts = $itemCounts[$id] ?? null;
                if ($counts) {
                    $total += (int) $counts->total;
                    $unread += (int) $counts->unread;
                }
                $week += (int) ($thisWeek[$id]?->cnt ?? 0);
            }
            $result[$entityId] = [
                'correspondence_total' => $total,
                'correspondence_unread' => $unread,
                'correspondence_this_week' => $week,
            ];
        }

        return $result;
    }

    public function metricDefinitions(): array
    {
        return [
            'correspondence_total' => [
                'label' => 'Korrespondenz (gesamt)',
                'group' => 'correspondence',
                'direction' => 'neutral',
                'unit' => 'count',
                'type' => 'stock',
                'aggregation_mode' => 'rolled_up',
            ],
            'correspondence_unread' => [
                'label' => 'Korrespondenz (ungelesen)',
                'group' => 'correspondence',
                'direction' => 'down',
                'unit' => 'count',
                'type' => 'stock',
                'aggregation_mode' => 'rolled_up',
            ],
            'correspondence_this_week' => [
                'label' => 'Korrespondenz (diese Woche)',
                'group' => 'correspondence',
                'direction' => 'neutral',
                'unit' => 'count',
                'type' => 'flow',
                'aggregation_mode' => 'rolled_up',
            ],
        ];
    }
}
