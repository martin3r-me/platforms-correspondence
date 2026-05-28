<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Korrespondenz', 'href' => route('correspondence.dashboard'), 'icon' => 'envelope'],
            ['label' => 'Posteingang'],
        ]" />
    </x-slot>

    <x-ui-page-container>
        <div class="space-y-6">

            {{-- Status Tabs --}}
            <div class="flex items-center gap-1 p-1 bg-black/[0.02] dark:bg-white/[0.03] rounded-lg w-fit">
                @foreach(['inbox' => 'Posteingang', 'assigned' => 'Zugewiesen', 'archived' => 'Archiviert'] as $status => $label)
                <button
                    wire:click="setStatus('{{ $status }}')"
                    class="px-4 py-2 text-sm font-medium rounded-md transition-colors duration-150
                        {{ $statusFilter === $status
                            ? 'bg-white dark:bg-white/10 text-gray-900 dark:text-gray-100 shadow-sm'
                            : 'text-gray-500 hover:text-gray-700 dark:hover:text-gray-300' }}"
                >
                    {{ $label }}
                    <span class="ml-1 text-xs text-gray-400">({{ $counts[$status] }})</span>
                </button>
                @endforeach
            </div>

            {{-- Search --}}
            <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    @svg('heroicon-o-magnifying-glass', 'w-4 h-4 text-gray-400')
                </div>
                <input
                    type="text"
                    wire:model.live.debounce.300ms="search"
                    placeholder="Threads durchsuchen..."
                    class="block w-full pl-10 pr-4 py-2.5 text-sm bg-white/60 dark:bg-white/5 border border-black/5 dark:border-white/10 rounded-lg placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500/30"
                >
            </div>

            {{-- Thread List --}}
            <div class="relative overflow-hidden rounded-xl bg-white/60 dark:bg-white/5 backdrop-blur-xl border border-black/5 dark:border-white/10 shadow-sm shadow-black/5">
                <div class="divide-y divide-black/5 dark:divide-white/5">
                    @forelse($threads as $thread)
                    <a href="{{ route('correspondence.threads.show', $thread->id) }}" wire:navigate class="flex items-center gap-4 px-5 py-4 hover:bg-black/[0.02] dark:hover:bg-white/[0.02] transition-colors duration-150">
                        <div class="flex-shrink-0 w-10 h-10 rounded-full bg-gradient-to-br from-blue-500/20 to-indigo-500/20 flex items-center justify-center">
                            @if($thread->unread_items_count > 0)
                                @svg('heroicon-s-envelope', 'w-5 h-5 text-blue-500')
                            @else
                                @svg('heroicon-o-envelope-open', 'w-5 h-5 text-gray-400')
                            @endif
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="text-sm {{ $thread->unread_items_count > 0 ? 'font-semibold text-gray-900 dark:text-gray-100' : 'font-medium text-gray-700 dark:text-gray-300' }} truncate">
                                {{ $thread->subject }}
                            </div>
                            <div class="text-xs text-gray-400 mt-0.5">
                                {{ $thread->item_count }} {{ $thread->item_count === 1 ? 'Item' : 'Items' }}
                                &middot; {{ $thread->latest_item_at?->diffForHumans() ?? 'Keine Items' }}
                            </div>
                        </div>
                        @if($thread->unread_items_count > 0)
                        <span class="flex-shrink-0 inline-flex items-center justify-center w-6 h-6 text-xs font-bold text-white bg-blue-500 rounded-full">
                            {{ $thread->unread_items_count }}
                        </span>
                        @endif
                    </a>
                    @empty
                    <div class="px-5 py-12 text-center">
                        @svg('heroicon-o-inbox', 'w-12 h-12 text-gray-300 dark:text-gray-600 mx-auto mb-3')
                        <p class="text-sm text-gray-400">Keine Threads gefunden</p>
                    </div>
                    @endforelse
                </div>
            </div>

            {{-- Pagination --}}
            <div class="mt-4">
                {{ $threads->links() }}
            </div>

        </div>
    </x-ui-page-container>

    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Filter" width="w-72" :defaultOpen="false">
            <div class="p-5 space-y-4">
                <div>
                    <h3 class="text-xs font-medium uppercase tracking-wider text-gray-400 mb-3">Statistiken</h3>
                    <div class="space-y-2">
                        @foreach(['inbox' => 'Posteingang', 'assigned' => 'Zugewiesen', 'archived' => 'Archiviert'] as $status => $label)
                        <div class="p-3 rounded-lg bg-black/[0.02] dark:bg-white/[0.03]">
                            <div class="flex items-center justify-between">
                                <span class="text-xs text-gray-400">{{ $label }}</span>
                                <span class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $counts[$status] }}</span>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </x-ui-page-sidebar>
    </x-slot>
</x-ui-page>
