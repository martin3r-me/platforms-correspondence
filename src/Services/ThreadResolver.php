<?php

namespace Platform\Correspondence\Services;

use Illuminate\Support\Facades\DB;
use Platform\Correspondence\Models\CorrespondenceItem;
use Platform\Correspondence\Models\CorrespondenceThread;

class ThreadResolver
{
    /**
     * Finds or creates a thread for an email import.
     *
     * Resolution order:
     * 1. ms365_conversation_id match
     * 2. In-Reply-To / References headers → against metadata->headers->message_id
     * 3. subject_normalized + time window fallback
     * 4. Create new thread
     */
    public function resolve(int $teamId, array $emailData, int $userId): CorrespondenceThread
    {
        $conversationId = $emailData['ms365_conversation_id'] ?? null;
        $subject = $emailData['subject'] ?? '';
        $normalized = $this->normalizeSubject($subject);
        $headers = $emailData['headers'] ?? [];
        $inReplyTo = $headers['in_reply_to'] ?? null;
        $references = $headers['references'] ?? [];

        // 1. ms365_conversation_id
        if ($conversationId) {
            $thread = CorrespondenceThread::forTeam($teamId)
                ->where('ms365_conversation_id', $conversationId)
                ->first();
            if ($thread) {
                return $thread;
            }
        }

        // 2. In-Reply-To / References header matching
        if ($inReplyTo || !empty($references)) {
            $messageIds = array_filter(array_merge([$inReplyTo], (array) $references));
            if (!empty($messageIds)) {
                $existingItem = CorrespondenceItem::forTeam($teamId)
                    ->where(function ($q) use ($messageIds) {
                        foreach ($messageIds as $mid) {
                            $q->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.headers.message_id')) = ?", [$mid]);
                        }
                    })
                    ->first();

                if ($existingItem) {
                    $thread = $existingItem->thread;
                    // Update conversation ID if we have one and thread doesn't
                    if ($conversationId && !$thread->ms365_conversation_id) {
                        $thread->update(['ms365_conversation_id' => $conversationId]);
                    }
                    return $thread;
                }
            }
        }

        // 3. subject_normalized + time window (30 days)
        if ($normalized !== '') {
            $thread = CorrespondenceThread::forTeam($teamId)
                ->where('subject_normalized', $normalized)
                ->where('latest_item_at', '>=', now()->subDays(30))
                ->orderByDesc('latest_item_at')
                ->first();
            if ($thread) {
                if ($conversationId && !$thread->ms365_conversation_id) {
                    $thread->update(['ms365_conversation_id' => $conversationId]);
                }
                return $thread;
            }
        }

        // 4. Create new thread
        return CorrespondenceThread::create([
            'team_id' => $teamId,
            'subject' => $subject,
            'subject_normalized' => $normalized,
            'status' => 'inbox',
            'ms365_conversation_id' => $conversationId,
            'created_by_user_id' => $userId,
        ]);
    }

    /**
     * Strips Re:/Fwd:/AW:/WG: prefixes and normalizes to lowercase.
     */
    public function normalizeSubject(string $subject): string
    {
        $normalized = preg_replace('/^(\s*(re|fwd|aw|wg)\s*:\s*)+/i', '', $subject);
        return mb_strtolower(trim($normalized));
    }

    /**
     * Merges source threads into a target thread.
     */
    public function mergeThreads(CorrespondenceThread $target, array $sourceIds): void
    {
        DB::transaction(function () use ($target, $sourceIds) {
            // Move all items from source threads to target
            CorrespondenceItem::whereIn('thread_id', $sourceIds)
                ->update(['thread_id' => $target->id]);

            // Soft-delete source threads
            CorrespondenceThread::whereIn('id', $sourceIds)->delete();

            // Update denormalized counts
            $target->updateDenormalized();
        });
    }

    /**
     * Splits items from a source thread into a new thread.
     */
    public function splitItems(CorrespondenceThread $source, array $itemIds, ?string $newSubject = null): CorrespondenceThread
    {
        return DB::transaction(function () use ($source, $itemIds, $newSubject) {
            $subject = $newSubject ?? $source->subject;

            $newThread = CorrespondenceThread::create([
                'team_id' => $source->team_id,
                'subject' => $subject,
                'subject_normalized' => $this->normalizeSubject($subject),
                'status' => $source->status,
                'created_by_user_id' => $source->created_by_user_id,
            ]);

            CorrespondenceItem::whereIn('id', $itemIds)
                ->where('thread_id', $source->id)
                ->update(['thread_id' => $newThread->id]);

            $source->updateDenormalized();
            $newThread->updateDenormalized();

            return $newThread;
        });
    }
}
