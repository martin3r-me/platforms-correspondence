<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Korrespondenz', 'href' => route('correspondence.dashboard'), 'icon' => 'envelope'],
            ['label' => 'Posteingang', 'href' => route('correspondence.inbox')],
            ['label' => $thread?->subject ?? 'Thread'],
        ]" />
    </x-slot>

    <x-ui-page-container>
        @if($thread)
        <div class="space-y-6">

            {{-- Thread Header --}}
            <div class="relative overflow-hidden rounded-xl bg-white/60 dark:bg-white/5 backdrop-blur-xl border border-black/5 dark:border-white/10 shadow-sm shadow-black/5 p-6">
                <div class="absolute inset-x-0 top-0 h-px bg-gradient-to-r from-transparent via-blue-500/30 to-transparent"></div>
                <div class="flex items-start justify-between">
                    <div>
                        <h1 class="text-lg font-medium tracking-tight text-gray-900 dark:text-gray-100">{{ $thread->subject }}</h1>
                        <div class="flex items-center gap-3 mt-2">
                            <span class="inline-flex items-center px-2 py-0.5 text-xs font-medium rounded-full
                                @if($thread->status === 'inbox') text-amber-600 dark:text-amber-400 bg-amber-500/10
                                @elseif($thread->status === 'assigned') text-emerald-600 dark:text-emerald-400 bg-emerald-500/10
                                @else text-gray-600 dark:text-gray-400 bg-gray-500/10
                                @endif
                            ">{{ $thread->status }}</span>
                            <span class="text-xs text-gray-400">{{ $thread->item_count }} {{ $thread->item_count === 1 ? 'Item' : 'Items' }}</span>
                            <span class="text-xs text-gray-400">Erstellt {{ $thread->created_at->diffForHumans() }}</span>
                        </div>
                    </div>
                    <x-ui-confirm-button
                        action="deleteThread"
                        text="Löschen"
                        confirmText="Wirklich löschen?"
                        variant="danger"
                        size="sm"
                        :icon="@svg('heroicon-o-trash', 'w-4 h-4')->toHtml()"
                    />
                </div>
            </div>

            {{-- Items --}}
            <div class="space-y-4">
                @foreach($items as $item)
                <div class="relative overflow-hidden rounded-xl bg-white/60 dark:bg-white/5 backdrop-blur-xl border border-black/5 dark:border-white/10 shadow-sm shadow-black/5">
                    <div class="absolute inset-x-0 top-0 h-px bg-gradient-to-r from-transparent
                        {{ $item->direction === 'inbound' ? 'via-blue-500/30' : 'via-emerald-500/30' }}
                        to-transparent"></div>

                    {{-- Item Header --}}
                    <div class="px-5 py-3 border-b border-black/5 dark:border-white/5 flex items-center gap-3">
                        <div class="flex-shrink-0 w-8 h-8 rounded-full flex items-center justify-center
                            {{ $item->direction === 'inbound' ? 'bg-blue-500/10' : 'bg-emerald-500/10' }}">
                            @if($item->type === 'email')
                                @svg('heroicon-o-envelope', 'w-4 h-4 ' . ($item->direction === 'inbound' ? 'text-blue-500' : 'text-emerald-500'))
                            @else
                                @svg('heroicon-o-document-text', 'w-4 h-4 ' . ($item->direction === 'inbound' ? 'text-blue-500' : 'text-emerald-500'))
                            @endif
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="text-sm font-medium text-gray-700 dark:text-gray-300">
                                {{ $item->direction === 'inbound' ? 'Von' : 'An' }}:
                                {{ $item->sender_name ?? $item->sender_email ?? 'Unbekannt' }}
                                @if($item->sender_email && $item->sender_name)
                                <span class="text-gray-400">&lt;{{ $item->sender_email }}&gt;</span>
                                @endif
                            </div>
                            <div class="text-xs text-gray-400">
                                {{ $item->correspondence_date?->format('d.m.Y') ?? $item->created_at->format('d.m.Y H:i') }}
                                &middot; {{ ucfirst($item->type) }}
                                @if($item->provider)
                                &middot; {{ $item->provider }}
                                @endif
                            </div>
                        </div>
                        <span class="inline-flex items-center px-2 py-0.5 text-xs font-medium rounded-full
                            {{ $item->direction === 'inbound' ? 'text-blue-600 dark:text-blue-400 bg-blue-500/10' : 'text-emerald-600 dark:text-emerald-400 bg-emerald-500/10' }}">
                            {{ $item->direction === 'inbound' ? 'Eingang' : 'Ausgang' }}
                        </span>
                    </div>

                    {{-- Item Body --}}
                    <div class="px-5 py-4">
                        @if($item->body_text)
                        <div class="text-sm text-gray-700 dark:text-gray-300 whitespace-pre-wrap leading-relaxed">{{ $item->body_text }}</div>
                        @else
                        <div class="text-sm text-gray-400 italic">Kein Text-Inhalt</div>
                        @endif
                    </div>
                </div>
                @endforeach
            </div>

        </div>
        @else
        <div class="text-center py-12">
            <p class="text-sm text-gray-400">Thread nicht gefunden</p>
        </div>
        @endif
    </x-ui-page-container>

    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Details" width="w-80" :defaultOpen="true">
            @if($thread)
            <div class="p-5 space-y-5">
                <div>
                    <h3 class="text-xs font-medium uppercase tracking-wider text-gray-400 mb-3">Thread-Info</h3>
                    <div class="space-y-2">
                        <div class="p-3 rounded-lg bg-black/[0.02] dark:bg-white/[0.03]">
                            <div class="flex items-center justify-between">
                                <span class="text-xs text-gray-400">Status</span>
                                <span class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ ucfirst($thread->status) }}</span>
                            </div>
                        </div>
                        <div class="p-3 rounded-lg bg-black/[0.02] dark:bg-white/[0.03]">
                            <div class="flex items-center justify-between">
                                <span class="text-xs text-gray-400">Items</span>
                                <span class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $thread->item_count }}</span>
                            </div>
                        </div>
                        <div class="p-3 rounded-lg bg-black/[0.02] dark:bg-white/[0.03]">
                            <div class="flex items-center justify-between">
                                <span class="text-xs text-gray-400">Erstellt</span>
                                <span class="text-sm text-gray-900 dark:text-gray-100">{{ $thread->created_at->format('d.m.Y') }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            @endif
        </x-ui-page-sidebar>
    </x-slot>
</x-ui-page>
