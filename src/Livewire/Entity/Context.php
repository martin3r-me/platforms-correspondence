<?php

namespace Platform\Correspondence\Livewire\Entity;

use Livewire\Component;
use Platform\Correspondence\Models\CorrespondenceThread;

class Context extends Component
{
    public int $entityId;
    public array $threadIds = [];

    public function mount(int $entityId, array $threadIds = [])
    {
        $this->entityId = $entityId;
        $this->threadIds = $threadIds;
    }

    public function render()
    {
        $threads = collect();

        if (!empty($this->threadIds)) {
            $threads = CorrespondenceThread::whereIn('id', $this->threadIds)
                ->withCount(['items', 'items as unread_items_count' => fn($q) => $q->unread()])
                ->orderByDesc('latest_item_at')
                ->get();
        }

        return view('correspondence::livewire.entity.context', [
            'threads' => $threads,
        ]);
    }
}
