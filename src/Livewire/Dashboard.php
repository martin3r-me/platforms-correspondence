<?php

namespace Platform\Correspondence\Livewire;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Platform\Correspondence\Models\CorrespondenceItem;
use Platform\Correspondence\Models\CorrespondenceThread;

class Dashboard extends Component
{
    public function rendered()
    {
        $this->dispatch('comms', [
            'model' => null,
            'modelId' => null,
            'subject' => 'Korrespondenz Dashboard',
            'description' => 'Übersicht der Korrespondenz',
            'url' => route('correspondence.dashboard'),
            'source' => 'correspondence.dashboard',
            'recipients' => [],
            'meta' => [
                'view_type' => 'dashboard',
            ],
        ]);
    }

    public function render()
    {
        $user = Auth::user();
        $team = $user->currentTeam;
        $teamId = $team->id;

        $stats = [
            'inbox' => CorrespondenceThread::forTeam($teamId)->inbox()->count(),
            'assigned' => CorrespondenceThread::forTeam($teamId)->assigned()->count(),
            'archived' => CorrespondenceThread::forTeam($teamId)->archived()->count(),
            'unread' => CorrespondenceItem::forTeam($teamId)->unread()->count(),
        ];

        $recentThreads = CorrespondenceThread::forTeam($teamId)
            ->withCount(['items', 'items as unread_items_count' => fn($q) => $q->unread()])
            ->orderByDesc('latest_item_at')
            ->limit(5)
            ->get();

        return view('correspondence::livewire.dashboard', [
            'stats' => $stats,
            'recentThreads' => $recentThreads,
        ])->layout('platform::layouts.app');
    }
}
