<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="{{ $package->name }}" icon="heroicon-o-cube" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Dev', 'href' => route('dev.dashboard'), 'icon' => 'code-bracket'],
            ['label' => $package->name],
        ]">
            <x-slot name="left">
                {{-- Board Tabs --}}
                @foreach($boards as $board)
                    @php
                        $boardType = $board->type instanceof \BackedEnum ? $board->type->value : $board->type;
                    @endphp
                    <x-ui-button
                        :variant="$activeTab === $boardType ? 'primary' : 'ghost'"
                        size="sm"
                        wire:click="setTab('{{ $boardType }}')"
                    >
                        {{ $board->name }}
                        <span class="ml-1 opacity-60">({{ $board->issues()->where('status', 'open')->count() }})</span>
                    </x-ui-button>
                @endforeach
                <a href="{{ route('dev.packages.discussions', $package) }}" wire:navigate>
                    <x-ui-button variant="ghost" size="sm">
                        @svg('heroicon-o-chat-bubble-left-right', 'w-4 h-4')
                        <span>Diskussionen</span>
                        <span class="ml-1 opacity-60">({{ $discussions->count() }})</span>
                    </x-ui-button>
                </a>
            </x-slot>
        </x-ui-page-actionbar>
    </x-slot>

    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Info" width="w-80" :defaultOpen="true" side="left">
            <div class="p-6 space-y-6">
                {{-- Package Header --}}
                <div class="d-flex items-center gap-3">
                    <div class="w-12 h-12 rounded-xl bg-[var(--ui-primary)]/10 d-flex items-center justify-center flex-shrink-0">
                        @svg($package->icon ?? 'heroicon-o-cube', 'w-6 h-6 text-[var(--ui-primary)]')
                    </div>
                    <div class="min-w-0">
                        <h3 class="text-lg font-semibold text-[var(--ui-secondary)]">{{ $package->name }}</h3>
                        <x-ui-badge :variant="$package->status === 'active' ? 'success' : 'secondary'" class="mt-1">
                            {{ $package->status === 'active' ? 'Aktiv' : 'Archiviert' }}
                        </x-ui-badge>
                    </div>
                </div>

                @if($package->description)
                    <p class="text-sm text-[var(--ui-muted)]">{{ $package->description }}</p>
                @endif

                @if($package->github_repo_full_name)
                    <div class="p-3 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40">
                        <div class="text-[10px] font-semibold uppercase tracking-wider text-[var(--ui-muted)] mb-1">Repository</div>
                        <div class="text-sm text-[var(--ui-secondary)] d-flex items-center gap-1.5">
                            @svg('heroicon-o-code-bracket', 'w-3.5 h-3.5')
                            {{ $package->github_repo_full_name }}
                        </div>
                    </div>
                @endif

                {{-- Boards --}}
                <div>
                    <h4 class="text-[10px] font-semibold uppercase tracking-wider text-[var(--ui-muted)] mb-3">Boards</h4>
                    <div class="space-y-1">
                        @foreach($boards as $board)
                            <a href="{{ route('dev.packages.boards.show', [$package, $board]) }}" wire:navigate
                               class="flex items-center justify-between p-2.5 rounded-lg text-sm text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)] transition-colors">
                                <span class="d-flex items-center gap-2">
                                    @svg('heroicon-o-view-columns', 'w-4 h-4 text-[var(--ui-muted)]')
                                    {{ $board->name }}
                                </span>
                                <span class="text-xs text-[var(--ui-muted)]">{{ $board->issues()->where('status', 'open')->count() }} offen</span>
                            </a>
                        @endforeach
                    </div>
                </div>
            </div>
        </x-ui-page-sidebar>
    </x-slot>

    <x-ui-page-container>
        {{-- Kanban Preview --}}
        @if($activeBoard)
            <div class="space-y-4">
                <div class="d-flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-[var(--ui-secondary)]">{{ $activeBoard->name }}</h2>
                    <a href="{{ route('dev.packages.boards.show', [$package, $activeBoard]) }}" wire:navigate
                       class="text-xs text-[var(--ui-primary)] hover:underline d-flex items-center gap-1">
                        @svg('heroicon-o-arrow-top-right-on-square', 'w-3.5 h-3.5')
                        Board oeffnen
                    </a>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-5 gap-3">
                    {{-- Backlog --}}
                    @if($backlogIssues->isNotEmpty())
                        <div class="rounded-lg bg-[var(--ui-muted-5)] border border-[var(--ui-border)]/40 p-3">
                            <h4 class="text-xs font-semibold text-[var(--ui-muted)] uppercase mb-2">Backlog ({{ $backlogIssues->count() }})</h4>
                            <div class="space-y-2">
                                @foreach($backlogIssues->take(5) as $issue)
                                    <a href="{{ route('dev.packages.issues.show', [$package, $issue]) }}" wire:navigate
                                       class="block p-2 rounded-lg bg-white dark:bg-[var(--ui-bg)] border border-[var(--ui-border)]/40 hover:border-[var(--ui-primary)]/40 transition-colors">
                                        <p class="text-xs font-medium text-[var(--ui-secondary)] truncate">{{ $issue->title }}</p>
                                    </a>
                                @endforeach
                                @if($backlogIssues->count() > 5)
                                    <p class="text-xs text-[var(--ui-muted)] text-center">+{{ $backlogIssues->count() - 5 }} weitere</p>
                                @endif
                            </div>
                        </div>
                    @endif

                    {{-- Slots --}}
                    @foreach($boardSlots as $slot)
                        <div class="rounded-lg bg-[var(--ui-muted-5)] border border-[var(--ui-border)]/40 p-3">
                            <h4 class="text-xs font-semibold text-[var(--ui-muted)] uppercase mb-2">{{ $slot->name }} ({{ $slot->issues->count() }})</h4>
                            <div class="space-y-2">
                                @foreach($slot->issues->take(5) as $issue)
                                    <a href="{{ route('dev.packages.issues.show', [$package, $issue]) }}" wire:navigate
                                       class="block p-2 rounded-lg bg-white dark:bg-[var(--ui-bg)] border border-[var(--ui-border)]/40 hover:border-[var(--ui-primary)]/40 transition-colors">
                                        <p class="text-xs font-medium text-[var(--ui-secondary)] truncate">{{ $issue->title }}</p>
                                    </a>
                                @endforeach
                                @if($slot->issues->count() > 5)
                                    <p class="text-xs text-[var(--ui-muted)] text-center">+{{ $slot->issues->count() - 5 }} weitere</p>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </x-ui-page-container>
</x-ui-page>
