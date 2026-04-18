<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="{{ $package->name }}" icon="heroicon-o-cube" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Dev', 'href' => route('dev.dashboard'), 'icon' => 'code-bracket'],
            ['label' => $package->name, 'href' => route('dev.packages.show', $package)],
        ]" />
    </x-slot>

    <x-ui-page-container>
        <div class="space-y-6">
            {{-- Package Header --}}
            <div class="d-flex items-center justify-between">
                <div class="d-flex items-center gap-3">
                    <div class="w-12 h-12 rounded-xl bg-[var(--ui-primary)]/10 d-flex items-center justify-center">
                        @svg($package->icon ?? 'heroicon-o-cube', 'w-6 h-6 text-[var(--ui-primary)]')
                    </div>
                    <div>
                        <h1 class="text-lg font-bold text-[var(--ui-secondary)]">{{ $package->name }}</h1>
                        @if($package->github_repo_full_name)
                            <p class="text-xs text-[var(--ui-muted)]">{{ $package->github_repo_full_name }}</p>
                        @endif
                    </div>
                </div>
                <x-ui-badge :variant="$package->status === 'active' ? 'success' : 'secondary'">
                    {{ $package->status === 'active' ? 'Aktiv' : 'Archiviert' }}
                </x-ui-badge>
            </div>

            @if($package->description)
                <p class="text-sm text-[var(--ui-muted)]">{{ $package->description }}</p>
            @endif

            {{-- Board Tabs --}}
            <div class="d-flex items-center gap-1 border-b border-[var(--ui-border)]/40">
                @foreach($boards as $board)
                    @php
                        $boardType = $board->type instanceof \BackedEnum ? $board->type->value : $board->type;
                    @endphp
                    <button
                        wire:click="setTab('{{ $boardType }}')"
                        class="px-4 py-2.5 text-sm font-medium transition-colors border-b-2 {{ $activeTab === $boardType ? 'border-[var(--ui-primary)] text-[var(--ui-primary)]' : 'border-transparent text-[var(--ui-muted)] hover:text-[var(--ui-secondary)]' }}"
                    >
                        {{ $board->name }}
                        <span class="ml-1 text-xs opacity-60">({{ $board->issues()->where('status', 'open')->count() }})</span>
                    </button>
                @endforeach
                <a href="{{ route('dev.packages.discussions', $package) }}" wire:navigate
                   class="px-4 py-2.5 text-sm font-medium transition-colors border-b-2 border-transparent text-[var(--ui-muted)] hover:text-[var(--ui-secondary)]">
                    Diskussionen
                    <span class="ml-1 text-xs opacity-60">({{ $discussions->count() }})</span>
                </a>
            </div>

            {{-- Kanban Board --}}
            @if($activeBoard)
                <div class="d-flex items-center justify-between mb-2">
                    <a href="{{ route('dev.packages.boards.show', [$package, $activeBoard]) }}" wire:navigate
                       class="text-xs text-[var(--ui-primary)] hover:underline">
                        Board vollstaendig oeffnen
                    </a>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-5 gap-3">
                    {{-- Backlog --}}
                    @if($backlogIssues->isNotEmpty())
                        <div class="rounded-lg bg-[var(--ui-muted-5)] border border-[var(--ui-border)]/40 p-3">
                            <h4 class="text-xs font-semibold text-[var(--ui-muted)] uppercase mb-2">Backlog ({{ $backlogIssues->count() }})</h4>
                            <div class="space-y-2">
                                @foreach($backlogIssues->take(5) as $issue)
                                    @include('dev::livewire.package.partials.issue-card', ['issue' => $issue])
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
                                    @include('dev::livewire.package.partials.issue-card', ['issue' => $issue])
                                @endforeach
                                @if($slot->issues->count() > 5)
                                    <p class="text-xs text-[var(--ui-muted)] text-center">+{{ $slot->issues->count() - 5 }} weitere</p>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </x-ui-page-container>

    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Info" width="w-80" :defaultOpen="true">
            <div class="p-5 space-y-4">
                @if($package->github_repo_full_name)
                    <div class="p-3 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40">
                        <div class="text-[10px] font-semibold uppercase tracking-wider text-[var(--ui-muted)] mb-1">Repository</div>
                        <div class="text-sm text-[var(--ui-secondary)]">{{ $package->github_repo_full_name }}</div>
                    </div>
                @endif

                <div>
                    <h3 class="text-[10px] font-semibold uppercase tracking-wider text-[var(--ui-muted)] mb-3">Boards</h3>
                    <div class="space-y-1">
                        @foreach($boards as $board)
                            <a href="{{ route('dev.packages.boards.show', [$package, $board]) }}" wire:navigate
                               class="flex items-center justify-between p-2 rounded-lg text-xs text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)] transition-colors">
                                <span>{{ $board->name }}</span>
                                <span class="text-[var(--ui-muted)]">{{ $board->issues()->where('status', 'open')->count() }}</span>
                            </a>
                        @endforeach
                    </div>
                </div>
            </div>
        </x-ui-page-sidebar>
    </x-slot>
</x-ui-page>
