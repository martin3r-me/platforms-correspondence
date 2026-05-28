<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Korrespondenz', 'href' => route('correspondence.dashboard'), 'icon' => 'envelope'],
        ]" />
    </x-slot>

    <x-ui-page-container>
        <div class="space-y-8">

            {{-- Hero Section --}}
            <div class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-blue-500/10 via-indigo-500/5 to-transparent dark:from-blue-500/20 dark:via-indigo-500/10 dark:to-transparent border border-white/20 dark:border-white/10 shadow-sm shadow-black/5 p-8">
                <div class="absolute inset-x-0 top-0 h-px bg-gradient-to-r from-transparent via-blue-500/60 to-transparent"></div>
                <div class="absolute -top-24 -right-24 w-64 h-64 bg-blue-500/10 rounded-full blur-3xl"></div>
                <div class="relative">
                    <div class="inline-flex items-center gap-2 px-3 py-1 text-xs font-medium text-blue-600 dark:text-blue-400 bg-blue-500/10 rounded-full mb-4">
                        @svg('heroicon-o-envelope', 'w-3.5 h-3.5')
                        <span>Korrespondenz</span>
                    </div>
                    <h1 class="text-2xl font-medium tracking-tight text-gray-900 dark:text-gray-100 mb-2">
                        Korrespondenz
                    </h1>
                    <p class="text-sm text-gray-500 dark:text-gray-400 max-w-lg">
                        Verwaltung externer Geschäftskorrespondenz: Emails und Briefe, gruppiert in Threads.
                    </p>
                </div>
            </div>

            {{-- Stat Cards --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                {{-- Inbox --}}
                <a href="{{ route('correspondence.inbox') }}" wire:navigate class="group relative overflow-hidden rounded-xl bg-white/60 dark:bg-white/5 backdrop-blur-xl border border-black/5 dark:border-white/10 shadow-sm shadow-black/5 p-5 hover:-translate-y-0.5 hover:shadow-md transition-all duration-150">
                    <div class="absolute inset-x-0 top-0 h-px bg-gradient-to-r from-transparent via-blue-500/50 to-transparent"></div>
                    <div class="flex items-center justify-between mb-3">
                        <span class="text-xs font-medium uppercase tracking-wider text-gray-400">Posteingang</span>
                        <div class="flex items-center justify-center w-8 h-8 rounded-lg bg-blue-500/10">
                            @svg('heroicon-o-inbox', 'w-4 h-4 text-blue-500')
                        </div>
                    </div>
                    <div class="text-3xl font-semibold tracking-tight text-gray-900 dark:text-gray-100">{{ $stats['inbox'] }}</div>
                    <div class="text-xs text-gray-400 mt-1">Threads</div>
                </a>

                {{-- Assigned --}}
                <div class="group relative overflow-hidden rounded-xl bg-white/60 dark:bg-white/5 backdrop-blur-xl border border-black/5 dark:border-white/10 shadow-sm shadow-black/5 p-5">
                    <div class="absolute inset-x-0 top-0 h-px bg-gradient-to-r from-transparent via-emerald-500/50 to-transparent"></div>
                    <div class="flex items-center justify-between mb-3">
                        <span class="text-xs font-medium uppercase tracking-wider text-gray-400">Zugewiesen</span>
                        <div class="flex items-center justify-center w-8 h-8 rounded-lg bg-emerald-500/10">
                            @svg('heroicon-o-check-circle', 'w-4 h-4 text-emerald-500')
                        </div>
                    </div>
                    <div class="text-3xl font-semibold tracking-tight text-gray-900 dark:text-gray-100">{{ $stats['assigned'] }}</div>
                    <div class="text-xs text-gray-400 mt-1">Threads</div>
                </div>

                {{-- Archived --}}
                <div class="group relative overflow-hidden rounded-xl bg-white/60 dark:bg-white/5 backdrop-blur-xl border border-black/5 dark:border-white/10 shadow-sm shadow-black/5 p-5">
                    <div class="absolute inset-x-0 top-0 h-px bg-gradient-to-r from-transparent via-gray-500/50 to-transparent"></div>
                    <div class="flex items-center justify-between mb-3">
                        <span class="text-xs font-medium uppercase tracking-wider text-gray-400">Archiviert</span>
                        <div class="flex items-center justify-center w-8 h-8 rounded-lg bg-gray-500/10">
                            @svg('heroicon-o-archive-box', 'w-4 h-4 text-gray-500')
                        </div>
                    </div>
                    <div class="text-3xl font-semibold tracking-tight text-gray-900 dark:text-gray-100">{{ $stats['archived'] }}</div>
                    <div class="text-xs text-gray-400 mt-1">Threads</div>
                </div>

                {{-- Unread --}}
                <div class="group relative overflow-hidden rounded-xl bg-white/60 dark:bg-white/5 backdrop-blur-xl border border-black/5 dark:border-white/10 shadow-sm shadow-black/5 p-5">
                    <div class="absolute inset-x-0 top-0 h-px bg-gradient-to-r from-transparent via-amber-500/50 to-transparent"></div>
                    <div class="flex items-center justify-between mb-3">
                        <span class="text-xs font-medium uppercase tracking-wider text-gray-400">Ungelesen</span>
                        <div class="flex items-center justify-center w-8 h-8 rounded-lg bg-amber-500/10">
                            @svg('heroicon-o-envelope-open', 'w-4 h-4 text-amber-500')
                        </div>
                    </div>
                    <div class="text-3xl font-semibold tracking-tight text-gray-900 dark:text-gray-100">{{ $stats['unread'] }}</div>
                    <div class="text-xs text-gray-400 mt-1">Items</div>
                </div>
            </div>

            {{-- Recent Threads --}}
            <div class="relative overflow-hidden rounded-xl bg-white/60 dark:bg-white/5 backdrop-blur-xl border border-black/5 dark:border-white/10 shadow-sm shadow-black/5">
                <div class="absolute inset-x-0 top-0 h-px bg-gradient-to-r from-transparent via-blue-500/30 to-transparent"></div>
                <div class="px-5 py-4 border-b border-black/5 dark:border-white/5 flex items-center justify-between">
                    <h2 class="text-sm font-medium tracking-tight text-gray-900 dark:text-gray-100">Letzte Threads</h2>
                    <a href="{{ route('correspondence.inbox') }}" wire:navigate class="text-xs text-blue-500 hover:text-blue-600">Alle anzeigen</a>
                </div>
                <div class="divide-y divide-black/5 dark:divide-white/5">
                    @forelse($recentThreads as $thread)
                    <a href="{{ route('correspondence.threads.show', $thread->id) }}" wire:navigate class="flex items-center gap-3 px-5 py-3 hover:bg-black/[0.02] dark:hover:bg-white/[0.02] transition-colors duration-150">
                        <div class="flex-shrink-0 w-8 h-8 rounded-full bg-gradient-to-br from-blue-500/20 to-indigo-500/20 flex items-center justify-center">
                            @svg('heroicon-o-envelope', 'w-4 h-4 text-blue-500')
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="text-sm font-medium text-gray-700 dark:text-gray-300 truncate">{{ $thread->subject }}</div>
                            <div class="text-xs text-gray-400">{{ $thread->item_count }} Items &middot; {{ $thread->latest_item_at?->diffForHumans() ?? 'Keine Items' }}</div>
                        </div>
                        <div class="flex items-center gap-2">
                            @if($thread->unread_items_count > 0)
                            <span class="inline-flex items-center px-2 py-0.5 text-xs font-medium text-blue-600 dark:text-blue-400 bg-blue-500/10 rounded-full">{{ $thread->unread_items_count }}</span>
                            @endif
                            <span class="inline-flex items-center px-2 py-0.5 text-xs font-medium rounded-full
                                @if($thread->status === 'inbox') text-amber-600 dark:text-amber-400 bg-amber-500/10
                                @elseif($thread->status === 'assigned') text-emerald-600 dark:text-emerald-400 bg-emerald-500/10
                                @else text-gray-600 dark:text-gray-400 bg-gray-500/10
                                @endif
                            ">{{ $thread->status }}</span>
                        </div>
                    </a>
                    @empty
                    <div class="px-5 py-8 text-center">
                        <span class="text-sm text-gray-400">Noch keine Korrespondenz vorhanden</span>
                    </div>
                    @endforelse
                </div>
            </div>

        </div>
    </x-ui-page-container>

    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Schnellzugriff" width="w-80" :defaultOpen="true">
            <div class="p-5 space-y-5">
                <div>
                    <h3 class="text-xs font-medium uppercase tracking-wider text-gray-400 mb-3">Navigation</h3>
                    <div class="space-y-1">
                        <a href="{{ route('correspondence.inbox') }}" wire:navigate class="flex items-center gap-2 p-2 rounded-lg hover:bg-black/[0.03] dark:hover:bg-white/[0.03] text-sm text-gray-700 dark:text-gray-300">
                            @svg('heroicon-o-inbox', 'w-4 h-4 text-gray-400')
                            Posteingang
                            @if($stats['inbox'] > 0)
                            <span class="ml-auto text-xs text-blue-500 font-medium">{{ $stats['inbox'] }}</span>
                            @endif
                        </a>
                    </div>
                </div>
            </div>
        </x-ui-page-sidebar>
    </x-slot>
</x-ui-page>
