<div>
    @if($threads->isNotEmpty())
    <div class="space-y-2">
        @foreach($threads as $thread)
        <a href="{{ route('correspondence.threads.show', $thread->id) }}" wire:navigate class="flex items-center gap-3 p-3 rounded-lg bg-black/[0.02] dark:bg-white/[0.02] hover:bg-black/[0.04] dark:hover:bg-white/[0.04] transition-colors duration-150">
            <div class="flex-shrink-0 w-8 h-8 rounded-full bg-blue-500/10 flex items-center justify-center">
                @svg('heroicon-o-envelope', 'w-4 h-4 text-blue-500')
            </div>
            <div class="flex-1 min-w-0">
                <div class="text-sm font-medium text-gray-700 dark:text-gray-300 truncate">{{ $thread->subject }}</div>
                <div class="text-xs text-gray-400">
                    {{ $thread->item_count }} {{ $thread->item_count === 1 ? 'Item' : 'Items' }}
                    &middot; {{ $thread->latest_item_at?->diffForHumans() ?? '' }}
                </div>
            </div>
            @if(($thread->unread_items_count ?? 0) > 0)
            <span class="flex-shrink-0 inline-flex items-center justify-center w-5 h-5 text-[10px] font-bold text-white bg-blue-500 rounded-full">{{ $thread->unread_items_count }}</span>
            @endif
        </a>
        @endforeach
    </div>
    @else
    <div class="py-4 text-center">
        <span class="text-xs text-gray-400">Keine Korrespondenz verknüpft</span>
    </div>
    @endif
</div>
