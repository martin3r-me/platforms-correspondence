<?php

namespace Platform\Correspondence\Livewire\Thread;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Platform\Correspondence\Models\CorrespondenceThread;

class Show extends Component
{
    public ?CorrespondenceThread $thread = null;

    public function mount(CorrespondenceThread $thread)
    {
        $user = Auth::user();
        $teamId = $user->currentTeam->id;

        if ($thread->team_id !== $teamId) {
            return redirect()->route('correspondence.inbox');
        }

        $thread->load(['items' => fn($q) => $q->orderBy('correspondence_date')->orderBy('created_at')]);
        $this->thread = $thread;

        // Mark items as read
        $this->thread->items()->where('is_read', false)->update(['is_read' => true]);
    }

    public function deleteThread()
    {
        if (!$this->thread) {
            return;
        }

        $subject = $this->thread->subject;

        $this->thread->items()->delete();
        $this->thread->delete();
        $this->thread = null;

        $this->dispatch('notify', [
            'type' => 'info',
            'message' => "Thread \"{$subject}\" wurde gelöscht.",
        ]);

        $this->dispatch('updateSidebar');

        $this->redirect(route('correspondence.inbox'), navigate: true);
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
