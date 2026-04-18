<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Dev" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Dev', 'icon' => 'code-bracket'],
        ]">
            <x-ui-button variant="secondary-outline" size="sm" wire:click="openActivateModal">
                @svg('heroicon-o-plus', 'w-4 h-4')
                <span>Package aktivieren</span>
            </x-ui-button>
        </x-ui-page-actionbar>
    </x-slot>

    <x-ui-page-container>
        {{-- Stats --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <x-ui-dashboard-tile title="Offene Issues" :count="$totalOpen" icon="clock" variant="warning" size="lg" />
            <x-ui-dashboard-tile title="Hohe Priorität" :count="$totalHighPriority" icon="fire" variant="danger" size="lg" />
            <x-ui-dashboard-tile title="Überfällig" :count="$totalOverdue" icon="exclamation-circle" variant="danger" size="lg" />
            <x-ui-dashboard-tile title="Erledigt" :count="$totalDone" icon="check-circle" variant="success" size="lg" />
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            {{-- Letzte offene Issues --}}
            <div class="lg:col-span-2 bg-[var(--ui-surface)] rounded-lg border border-[var(--ui-border)]/60">
                <div class="p-4 border-b border-[var(--ui-border)]/60">
                    <h3 class="text-sm font-semibold text-[var(--ui-secondary)]">Letzte offene Issues</h3>
                </div>

                <div class="divide-y divide-[var(--ui-border)]/40">
                    @forelse($recentIssues as $issue)
                        <a href="{{ route('dev.packages.issues.show', [$issue->board->package, $issue]) }}"
                           wire:navigate
                           class="d-flex items-center gap-3 p-3 hover:bg-[var(--ui-muted-5)] transition-colors">
                            <div class="flex-shrink-0">
                                @if($issue->priority === 'high')
                                    @svg('heroicon-s-fire', 'w-4 h-4 text-[var(--ui-danger)]')
                                @else
                                    @svg('heroicon-o-circle-stack', 'w-4 h-4 text-[var(--ui-muted)]')
                                @endif
                            </div>
                            <div class="min-w-0 flex-grow-1">
                                <div class="text-sm font-medium text-[var(--ui-secondary)] truncate">{{ $issue->title }}</div>
                                <div class="text-xs text-[var(--ui-muted)]">
                                    {{ $issue->board->package->name }} · {{ $issue->board->name }}
                                    @if($issue->userInCharge)
                                        · {{ $issue->userInCharge->name }}
                                    @endif
                                </div>
                            </div>
                            <div class="flex-shrink-0 text-xs text-[var(--ui-muted)]">
                                {{ $issue->created_at->diffForHumans() }}
                            </div>
                        </a>
                    @empty
                        <div class="p-8 text-center">
                            <p class="text-sm text-[var(--ui-muted)]">Keine offenen Issues.</p>
                        </div>
                    @endforelse
                </div>
            </div>

            {{-- Rechte Spalte: Packages + Erledigt --}}
            <div class="space-y-6">
                {{-- Packages --}}
                <div class="bg-[var(--ui-surface)] rounded-lg border border-[var(--ui-border)]/60">
                    <div class="p-4 border-b border-[var(--ui-border)]/60">
                        <h3 class="text-sm font-semibold text-[var(--ui-secondary)]">Packages</h3>
                    </div>
                    <div class="divide-y divide-[var(--ui-border)]/40">
                        @forelse($packages as $package)
                            <a href="{{ route('dev.packages.show', $package) }}"
                               wire:navigate
                               class="d-flex items-center gap-3 p-3 hover:bg-[var(--ui-muted-5)] transition-colors">
                                <div class="w-8 h-8 rounded-lg d-flex items-center justify-center bg-[var(--ui-primary-5)] text-[var(--ui-primary)] flex-shrink-0">
                                    @svg($package->icon ?? 'heroicon-o-cube', 'w-4 h-4')
                                </div>
                                <div class="min-w-0 flex-grow-1">
                                    <div class="text-sm font-medium text-[var(--ui-secondary)] truncate">{{ $package->name }}</div>
                                    <div class="text-xs text-[var(--ui-muted)]">
                                        {{ $packageStats[$package->id]['open_features'] ?? 0 }} Features · {{ $packageStats[$package->id]['open_bugs'] ?? 0 }} Bugs
                                    </div>
                                </div>
                            </a>
                        @empty
                            <div class="p-4 text-center">
                                <p class="text-xs text-[var(--ui-muted)]">Noch keine Packages aktiviert.</p>
                            </div>
                        @endforelse
                    </div>
                </div>

                {{-- Zuletzt erledigt --}}
                <div class="bg-[var(--ui-surface)] rounded-lg border border-[var(--ui-border)]/60">
                    <div class="p-4 border-b border-[var(--ui-border)]/60">
                        <h3 class="text-sm font-semibold text-[var(--ui-secondary)]">Zuletzt erledigt</h3>
                    </div>
                    <div class="divide-y divide-[var(--ui-border)]/40">
                        @forelse($recentlyDone as $issue)
                            <a href="{{ route('dev.packages.issues.show', [$issue->board->package, $issue]) }}"
                               wire:navigate
                               class="d-flex items-center gap-3 p-3 hover:bg-[var(--ui-muted-5)] transition-colors">
                                <div class="flex-shrink-0">
                                    @svg('heroicon-o-check-circle', 'w-4 h-4 text-[var(--ui-success)]')
                                </div>
                                <div class="min-w-0 flex-grow-1">
                                    <div class="text-sm text-[var(--ui-muted)] line-through truncate">{{ $issue->title }}</div>
                                    <div class="text-xs text-[var(--ui-muted)]">
                                        {{ $issue->board->package->name }}
                                        @if($issue->done_at)
                                            · {{ $issue->done_at->diffForHumans() }}
                                        @endif
                                    </div>
                                </div>
                            </a>
                        @empty
                            <div class="p-4 text-center">
                                <p class="text-xs text-[var(--ui-muted)]">Noch nichts erledigt.</p>
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </x-ui-page-container>

    {{-- Activate Modal --}}
    @if($showActivateModal)
        <x-ui-modal wire:model="showActivateModal" title="Package aktivieren">
            <div class="space-y-4">
                @if($availableRepos->isNotEmpty())
                    <x-ui-input-select
                        name="selectedRepoId"
                        wire:model.live="selectedRepoId"
                        label="GitHub Repository"
                        :nullable="true"
                        nullLabel="-- Ohne Repository --"
                        :options="$availableRepos->mapWithKeys(fn ($r) => [$r->id => $r->full_name . ($r->is_private ? ' (privat)' : '') . ($r->language ? ' · '.$r->language : '')])->toArray()"
                    />
                @else
                    <div class="p-3 rounded-lg bg-[var(--ui-muted-5)] border border-[var(--ui-border)]/40">
                        <p class="text-xs text-[var(--ui-muted)]">Keine GitHub-Repositories verfügbar. Verbinde GitHub unter Integrationen und synchronisiere deine Repos.</p>
                    </div>
                @endif
                <x-ui-input-text wire:model="activatePackageName" label="Name" placeholder="z.B. Platform Sheets" required />
                <x-ui-input-textarea wire:model="activatePackageDescription" label="Beschreibung" placeholder="Optional" />
            </div>
            <x-slot name="footer">
                <x-ui-button variant="secondary-outline" wire:click="$set('showActivateModal', false)">Abbrechen</x-ui-button>
                <x-ui-button variant="primary" wire:click="activatePackage">Aktivieren</x-ui-button>
            </x-slot>
        </x-ui-modal>
    @endif
</x-ui-page>
