<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="{{ $package->name }}" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Dev', 'href' => route('dev.dashboard'), 'icon' => 'code-bracket'],
            ['label' => $package->name],
        ]">
            <x-slot name="left">
                {{-- Board-Tabs als echte Navigation --}}
                @foreach($boards as $board)
                    <a href="{{ route('dev.packages.boards.show', [$package, $board]) }}" wire:navigate>
                        <x-ui-button variant="ghost" size="sm">
                            @svg('heroicon-o-view-columns', 'w-4 h-4')
                            <span>{{ $board->name }}</span>
                            <span class="ml-1 opacity-60">({{ $board->open_issues_count }})</span>
                        </x-ui-button>
                    </a>
                @endforeach
            </x-slot>
        </x-ui-page-actionbar>
    </x-slot>

    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Übersicht" width="w-80" :defaultOpen="true" storeKey="sidebarOpen" side="left">
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

                {{-- Package Info --}}
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-4">Info</h3>
                    <div class="space-y-2 text-sm">
                        @if($package->userInCharge)
                            <div class="flex justify-between">
                                <span class="text-[var(--ui-muted)]">Verantwortlich:</span>
                                <span class="font-medium text-[var(--ui-secondary)]">{{ $package->userInCharge->name }}</span>
                            </div>
                        @endif
                        @if($package->github_repo_full_name)
                            <div class="flex justify-between">
                                <span class="text-[var(--ui-muted)]">Repository:</span>
                                <span class="font-medium text-[var(--ui-secondary)] truncate max-w-[10rem]">{{ $package->github_repo_full_name }}</span>
                            </div>
                        @endif
                        <div class="flex justify-between">
                            <span class="text-[var(--ui-muted)]">Erstellt:</span>
                            <span class="font-medium text-[var(--ui-secondary)]">{{ $package->created_at->format('d.m.Y') }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </x-ui-page-sidebar>
    </x-slot>

    <x-slot name="activity">
        <x-ui-page-sidebar title="Aktivitäten" width="w-80" :defaultOpen="false" storeKey="activityOpen" side="right">
            <div class="p-4 space-y-4">
                <div class="text-sm text-[var(--ui-muted)]">Letzte Aktivitäten</div>
                <div class="space-y-3 text-sm">
                    @foreach(($activities ?? []) as $activity)
                        <div class="p-2 rounded border border-[var(--ui-border)]/60 bg-[var(--ui-muted-5)]">
                            <div class="font-medium text-[var(--ui-secondary)] truncate">{{ $activity['title'] ?? 'Aktivität' }}</div>
                            <div class="text-[var(--ui-muted)]">{{ $activity['time'] ?? '' }}</div>
                        </div>
                    @endforeach
                </div>
            </div>
        </x-ui-page-sidebar>
    </x-slot>

    <x-ui-page-container>
        {{-- Statistiken --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <x-ui-dashboard-tile title="Offene Issues" :count="$totalOpen" icon="clock" variant="warning" size="lg" />
            <x-ui-dashboard-tile title="Erledigt" :count="$totalDone" icon="check-circle" variant="success" size="lg" />
            <x-ui-dashboard-tile title="Überfällig" :count="$totalOverdue" icon="exclamation-circle" variant="danger" size="lg" />
            <x-ui-dashboard-tile title="Boards" :count="$boards->count()" icon="view-columns" variant="secondary" size="lg" />
        </div>

        {{-- Boards --}}
        <div class="bg-[var(--ui-surface)] rounded-lg border border-[var(--ui-border)]/60">
            <div class="p-6 border-b border-[var(--ui-border)]/60">
                <h3 class="text-lg font-semibold text-[var(--ui-secondary)]">Boards</h3>
                <p class="text-sm text-[var(--ui-muted)] mt-1">Alle Boards dieses Packages</p>
            </div>

            <div class="p-6">
                @if($boards->isNotEmpty())
                    <div class="space-y-4">
                        @foreach($boards as $board)
                            <div class="d-flex items-center justify-between p-4 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/60 hover:bg-[var(--ui-primary-5)] hover:border-[var(--ui-primary)]/60 transition">
                                <div class="d-flex items-center gap-4">
                                    <div class="w-10 h-10 rounded-lg d-flex items-center justify-center bg-[var(--ui-primary-5)] text-[var(--ui-primary)]">
                                        @svg('heroicon-o-view-columns', 'w-5 h-5')
                                    </div>
                                    <div>
                                        <h4 class="font-medium text-[var(--ui-secondary)]">{{ $board->name }}</h4>
                                        <p class="text-sm text-[var(--ui-muted)]">
                                            {{ $board->open_issues_count }} offene Issues
                                            @if($board->description)
                                                · {{ $board->description }}
                                            @endif
                                        </p>
                                    </div>
                                </div>
                                <a href="{{ route('dev.packages.boards.show', [$package, $board]) }}"
                                   class="inline-flex items-center gap-2 px-3 py-2 rounded-md border border-[var(--ui-primary)] text-[var(--ui-primary)] bg-[var(--ui-primary-5)] hover:bg-[var(--ui-primary-10)] transition text-sm"
                                   wire:navigate>
                                    <div class="d-flex items-center gap-2">
                                        @svg('heroicon-o-arrow-right', 'w-4 h-4')
                                        <span>Öffnen</span>
                                    </div>
                                </a>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="text-center py-8">
                        <p class="text-[var(--ui-muted)]">Keine Boards vorhanden.</p>
                    </div>
                @endif
            </div>
        </div>
    </x-ui-page-container>
</x-ui-page>
