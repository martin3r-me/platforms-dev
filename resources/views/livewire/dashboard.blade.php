<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Dev" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Dev', 'icon' => 'code-bracket'],
        ]" />
    </x-slot>

    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Schnellzugriff" width="w-80" :defaultOpen="true">
            <div class="p-6 space-y-6">
                {{-- Schnellstatistiken --}}
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-3">Schnellstatistiken</h3>
                    <div class="space-y-3">
                        <div class="p-3 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40">
                            <div class="text-xs text-[var(--ui-muted)]">Aktive Packages</div>
                            <div class="text-lg font-bold text-[var(--ui-secondary)]">{{ $totalPackages }}</div>
                        </div>
                        <div class="p-3 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40">
                            <div class="text-xs text-[var(--ui-muted)]">Offene Issues</div>
                            <div class="text-lg font-bold text-[var(--ui-secondary)]">{{ $totalOpenIssues }}</div>
                        </div>
                    </div>
                </div>

                {{-- Erstellen --}}
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-3">Erstellen</h3>
                    <x-ui-button variant="secondary-outline" size="sm" wire:click="openActivateModal" class="w-full">
                        <span class="flex items-center gap-2">
                            @svg('heroicon-o-plus', 'w-4 h-4')
                            <span>Package aktivieren</span>
                        </span>
                    </x-ui-button>
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
        {{-- Haupt-Kacheln --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
            <x-ui-dashboard-tile title="Aktive Packages" :count="$totalPackages" icon="cube" variant="secondary" size="lg" />
            <x-ui-dashboard-tile title="Offene Issues" :count="$totalOpenIssues" icon="exclamation-circle" variant="warning" size="lg" />
        </div>

        {{-- Package-Übersicht --}}
        <div class="bg-[var(--ui-surface)] rounded-lg border border-[var(--ui-border)]/60">
            <div class="p-6 border-b border-[var(--ui-border)]/60">
                <h3 class="text-lg font-semibold text-[var(--ui-secondary)]">Packages</h3>
                <p class="text-sm text-[var(--ui-muted)] mt-1">Aktivierte Packages</p>
            </div>

            <div class="p-6">
                @if($packages->isNotEmpty())
                    <div class="space-y-4">
                        @foreach($packages as $package)
                            <div class="d-flex items-center justify-between p-4 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/60 hover:bg-[var(--ui-primary-5)] hover:border-[var(--ui-primary)]/60 transition">
                                <div class="d-flex items-center gap-4">
                                    <div class="w-10 h-10 rounded-lg d-flex items-center justify-center bg-[var(--ui-primary-5)] text-[var(--ui-primary)]">
                                        @svg($package->icon ?? 'heroicon-o-cube', 'w-5 h-5')
                                    </div>
                                    <div>
                                        <h4 class="font-medium text-[var(--ui-secondary)]">{{ $package->name }}</h4>
                                        <p class="text-sm text-[var(--ui-muted)]">
                                            {{ $packageStats[$package->id]['open_features'] ?? 0 }} Features
                                            · {{ $packageStats[$package->id]['open_bugs'] ?? 0 }} Bugs
                                            · {{ $package->discussions_count }} Diskussionen
                                            @if($package->github_repo_full_name)
                                                · {{ $package->github_repo_full_name }}
                                            @endif
                                        </p>
                                    </div>
                                </div>
                                <a href="{{ route('dev.packages.show', $package) }}"
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
                        <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-[var(--ui-muted-5)] mb-4">
                            @svg('heroicon-o-code-bracket', 'w-8 h-8 text-[var(--ui-muted)]')
                        </div>
                        <h4 class="text-lg font-medium text-[var(--ui-secondary)] mb-2">Keine Packages</h4>
                        <p class="text-[var(--ui-muted)] mb-4">Noch keine Packages aktiviert.</p>
                        <x-ui-button variant="primary" size="sm" wire:click="openActivateModal">
                            @svg('heroicon-o-plus', 'w-4 h-4')
                            <span>Package aktivieren</span>
                        </x-ui-button>
                    </div>
                @endif
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
