<div
    x-data="{
        init() {
            const savedState = localStorage.getItem('correspondence.showAllThreads');
            if (savedState !== null) {
                @this.set('showAllThreads', savedState === 'true');
            }
        }
    }"
>
    {{-- Modul Header --}}
    <div x-show="!collapsed" class="p-3 text-sm italic text-[var(--ui-secondary)] uppercase border-b border-[var(--ui-border)] mb-2">
        Korrespondenz
    </div>

    {{-- Abschnitt: Allgemein --}}
    <x-ui-sidebar-list label="Allgemein">
        <x-ui-sidebar-item :href="route('correspondence.dashboard')">
            @svg('heroicon-o-home', 'w-4 h-4 text-[var(--ui-secondary)]')
            <span class="ml-2 text-sm">Dashboard</span>
        </x-ui-sidebar-item>
        <x-ui-sidebar-item :href="route('correspondence.inbox')">
            @svg('heroicon-o-inbox', 'w-4 h-4 text-[var(--ui-secondary)]')
            <span class="ml-2 text-sm">Posteingang</span>
            @if($inboxCount > 0)
            <span class="ml-auto inline-flex items-center justify-center px-1.5 py-0.5 text-xs font-bold text-blue-600 dark:text-blue-400 bg-blue-500/10 rounded-full">{{ $inboxCount }}</span>
            @endif
        </x-ui-sidebar-item>
    </x-ui-sidebar-list>

    {{-- Collapsed: Icons-only --}}
    <div x-show="collapsed" class="px-2 py-2 border-b border-[var(--ui-border)]">
        <div class="flex flex-col gap-2">
            <a href="{{ route('correspondence.dashboard') }}" wire:navigate class="flex items-center justify-center p-2 rounded-md text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)]">
                @svg('heroicon-o-home', 'w-5 h-5')
            </a>
            <a href="{{ route('correspondence.inbox') }}" wire:navigate class="relative flex items-center justify-center p-2 rounded-md text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)]">
                @svg('heroicon-o-inbox', 'w-5 h-5')
                @if($inboxCount > 0)
                <span class="absolute -top-0.5 -right-0.5 w-4 h-4 text-[10px] font-bold text-white bg-blue-500 rounded-full flex items-center justify-center">{{ $inboxCount }}</span>
                @endif
            </a>
        </div>
    </div>

    {{-- Abschnitt: Threads (Entity-basierte Gruppierung) --}}
    <div>
        <div class="mt-2" x-show="!collapsed">
            {{-- Entity Type Gruppen (Baum-Darstellung) --}}
            @foreach($entityTypeGroups as $typeGroup)
                <x-ui-sidebar-list wire:key="type-group-{{ $typeGroup['type_id'] }}" :label="$typeGroup['type_name']">
                    @foreach($typeGroup['entities'] as $entityNode)
                        @include('correspondence::livewire.partials.sidebar-entity-node', [
                            'node' => $entityNode,
                            'typeIcon' => $typeGroup['type_icon'] ?? null,
                        ])
                    @endforeach
                </x-ui-sidebar-list>
            @endforeach

            {{-- Unverknüpfte Threads --}}
            @if($unlinkedThreads->isNotEmpty())
                <x-ui-sidebar-list label="Unverknüpft">
                    @foreach($unlinkedThreads as $thread)
                        <a wire:key="unlinked-thread-{{ $thread->id }}"
                           href="{{ route('correspondence.threads.show', ['thread' => $thread->id]) }}"
                           wire:navigate
                           title="{{ $thread->subject }}"
                           class="flex items-center gap-1.5 py-0.5 pl-3 pr-2 text-[var(--ui-secondary)] hover:text-[var(--ui-primary)] transition truncate">
                            <span class="w-1 h-1 rounded-full flex-shrink-0 bg-[var(--ui-muted)] opacity-40"></span>
                            <span class="truncate text-[11px]">{{ $thread->subject }}</span>
                            @if($thread->item_count > 0)
                                <span class="ml-auto text-[10px] tabular-nums text-[var(--ui-muted)] opacity-60">{{ $thread->item_count }}</span>
                            @endif
                        </a>
                    @endforeach
                </x-ui-sidebar-list>
            @endif

            {{-- Button zum Ein-/Ausblenden aller Threads --}}
            @if($hasMoreThreads)
                <div class="px-3 py-2">
                    <button
                        type="button"
                        wire:click="toggleShowAllThreads"
                        x-on:click="localStorage.setItem('correspondence.showAllThreads', (!$wire.showAllThreads).toString())"
                        class="flex items-center gap-2 text-xs text-[var(--ui-muted)] hover:text-[var(--ui-secondary)] transition-colors"
                    >
                        @if($showAllThreads)
                            @svg('heroicon-o-eye-slash', 'w-4 h-4')
                            <span>Nur aktive Threads</span>
                        @else
                            @svg('heroicon-o-eye', 'w-4 h-4')
                            <span>Alle Threads anzeigen</span>
                        @endif
                    </button>
                </div>
            @endif

            {{-- Keine Threads --}}
            @if($entityTypeGroups->isEmpty() && $unlinkedThreads->isEmpty())
                <div class="px-3 py-1 text-xs text-[var(--ui-muted)]">
                    @if($showAllThreads)
                        Keine Threads
                    @else
                        Keine aktiven Threads
                    @endif
                </div>
            @endif
        </div>
    </div>
</div>
