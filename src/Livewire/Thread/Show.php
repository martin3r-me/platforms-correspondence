<?php

namespace Platform\Correspondence\Livewire\Thread;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Platform\Correspondence\Models\CorrespondenceThread;

class Show extends Component
{
    public ?CorrespondenceThread $thread = null;

    public function mount(int $thread)
    {
        $user = Auth::user();
        $teamId = $user->currentTeam->id;

        $this->thread = CorrespondenceThread::forTeam($teamId)
            ->with(['items' => fn($q) => $q->orderBy('correspondence_date')->orderBy('created_at')])
            ->find($thread);

        if (!$this->thread) {
            return redirect()->route('correspondence.inbox');
        }

        // Mark items as read
        $this->thread->items()->where('is_read', false)->update(['is_read' => true]);
    }

    public function rendered()
    {
        if ($this->thread) {
            $this->dispatch('comms', [
                'model' => 'correspondence_thread',
                'modelId' => $this->thread->id,
                'subject' => $this->thread->subject,
                'description' => 'Thread-Detail',
                'url' => route('correspondence.threads.show', $this->thread->id),
                'source' => 'correspondence.thread.show',
                'recipients' => [],
                'meta' => [
                    'view_type' => 'thread_detail',
                ],
            ]);
        }
    }

    public function render()
    {
        return view('correspondence::livewire.thread.show', [
            'items' => $this->thread?->items ?? collect(),
        ])->layout('platform::layouts.app');
    }
}
