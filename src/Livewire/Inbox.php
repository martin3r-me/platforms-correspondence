<?php

namespace Platform\Correspondence\Livewire;

use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Support\Facades\Auth;
use Platform\Correspondence\Models\CorrespondenceThread;

class Inbox extends Component
{
    use WithPagination;

    public string $statusFilter = 'inbox';
    public string $search = '';

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingStatusFilter()
    {
        $this->resetPage();
    }

    public function setStatus(string $status)
    {
        $this->statusFilter = $status;
        $this->resetPage();
    }

    public function rendered()
    {
        $this->dispatch('comms', [
            'model' => null,
            'modelId' => null,
            'subject' => 'Korrespondenz Posteingang',
            'description' => 'Posteingang der Korrespondenz',
            'url' => route('correspondence.inbox'),
            'source' => 'correspondence.inbox',
            'recipients' => [],
            'meta' => [
                'view_type' => 'inbox',
            ],
        ]);
    }

    public function render()
    {
        $user = Auth::user();
        $team = $user->currentTeam;
        $teamId = $team->id;

        $query = CorrespondenceThread::forTeam($teamId)
            ->withCount(['items', 'items as unread_items_count' => fn($q) => $q->unread()])
            ->where('status', $this->statusFilter);

        if ($this->search !== '') {
            $query->where('subject_normalized', 'like', '%' . mb_strtolower($this->search) . '%');
        }

        $threads = $query->orderByDesc('latest_item_at')->paginate(25);

        $counts = [
            'inbox' => CorrespondenceThread::forTeam($teamId)->inbox()->count(),
            'assigned' => CorrespondenceThread::forTeam($teamId)->assigned()->count(),
            'archived' => CorrespondenceThread::forTeam($teamId)->archived()->count(),
        ];

        return view('correspondence::livewire.inbox', [
            'threads' => $threads,
            'counts' => $counts,
        ])->layout('platform::layouts.app');
    }
}
