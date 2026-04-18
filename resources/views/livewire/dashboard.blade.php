<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Dev" icon="heroicon-o-code-bracket" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Dev', 'href' => route('dev.dashboard'), 'icon' => 'code-bracket'],
        ]" />
    </x-slot>

    <x-ui-page-container>
        <div class="space-y-6">
            {{-- Stats --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                <x-ui-dashboard-tile
                    title="Packages"
                    :count="$totalPackages"
                    subtitle="Aktiv"
                    icon="cube"
                    variant="secondary"
                    size="lg"
                />
                <x-ui-dashboard-tile
                    title="Offene Issues"
                    :count="$totalOpenIssues"
                    subtitle="Gesamt"
                    icon="exclamation-circle"
                    variant="secondary"
                    size="lg"
                />
            </div>

            {{-- Packages --}}
            <x-ui-panel title="Packages" subtitle="Aktivierte Packages">
                @if($packages->isNotEmpty())
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                        @foreach($packages as $package)
                            <a href="{{ route('dev.packages.show', $package) }}" wire:navigate
                               class="block p-4 rounded-lg border border-[var(--ui-border)]/40 bg-[var(--ui-muted-5)] hover:border-[var(--ui-primary)]/40 transition-colors">
                                <div class="d-flex items-center gap-3 mb-3">
                                    <div class="w-10 h-10 rounded-lg bg-[var(--ui-primary)]/10 d-flex items-center justify-center flex-shrink-0">
                                        @svg($package->icon ?? 'heroicon-o-cube', 'w-5 h-5 text-[var(--ui-primary)]')
                                    </div>
                                    <div class="min-w-0">
                                        <h3 class="text-sm font-semibold text-[var(--ui-secondary)] truncate">{{ $package->name }}</h3>
                                        @if($package->github_repo_full_name)
                                            <p class="text-xs text-[var(--ui-muted)] truncate">{{ $package->github_repo_full_name }}</p>
                                        @endif
                                    </div>
                                </div>
                                @if($package->description)
                                    <p class="text-xs text-[var(--ui-muted)] mb-3 line-clamp-2">{{ $package->description }}</p>
                                @endif
                                <div class="d-flex items-center gap-4 text-xs text-[var(--ui-muted)]">
                                    <span class="d-flex items-center gap-1">
                                        @svg('heroicon-o-light-bulb', 'w-3.5 h-3.5')
                                        {{ $packageStats[$package->id]['open_features'] ?? 0 }} Features
                                    </span>
                                    <span class="d-flex items-center gap-1">
                                        @svg('heroicon-o-bug-ant', 'w-3.5 h-3.5')
                                        {{ $packageStats[$package->id]['open_bugs'] ?? 0 }} Bugs
                                    </span>
                                    <span class="d-flex items-center gap-1">
                                        @svg('heroicon-o-chat-bubble-left-right', 'w-3.5 h-3.5')
                                        {{ $package->discussions_count }}
                                    </span>
                                </div>
                            </a>
                        @endforeach
                    </div>
                @else
                    <div class="py-12 text-center">
                        <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-[var(--ui-muted-5)] mb-4">
                            @svg('heroicon-o-code-bracket', 'w-8 h-8 text-[var(--ui-muted)]')
                        </div>
                        <p class="text-sm text-[var(--ui-muted)] mb-4">Noch keine Packages aktiviert.</p>
                        <button wire:click="openActivateModal" class="inline-flex items-center gap-2 px-4 py-2 text-sm rounded-lg bg-[var(--ui-primary)] text-[var(--ui-on-primary)] hover:opacity-90 transition-opacity">
                            @svg('heroicon-o-plus', 'w-4 h-4')
                            <span>Package aktivieren</span>
                        </button>
                    </div>
                @endif
            </x-ui-panel>
        </div>
    </x-ui-page-container>

    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Aktionen" width="w-80" :defaultOpen="true">
            <div class="p-5 space-y-6">
                <div>
                    <h3 class="text-[10px] font-semibold uppercase tracking-wider text-[var(--ui-muted)] mb-3">Erstellen</h3>
                    <div class="space-y-2">
                        <x-ui-button variant="secondary-outline" size="sm" wire:click="openActivateModal" class="w-full">
                            <span class="flex items-center gap-2">
                                @svg('heroicon-o-plus', 'w-4 h-4')
                                <span>Package aktivieren</span>
                            </span>
                        </x-ui-button>
                    </div>
                </div>

                <div>
                    <h3 class="text-[10px] font-semibold uppercase tracking-wider text-[var(--ui-muted)] mb-3">Statistiken</h3>
                    <div class="space-y-2">
                        <div class="flex items-center justify-between p-3 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40">
                            <div class="flex items-center gap-2">
                                @svg('heroicon-o-cube', 'w-4 h-4 text-[var(--ui-muted)]')
                                <span class="text-xs text-[var(--ui-muted)]">Packages</span>
                            </div>
                            <span class="text-sm font-bold text-[var(--ui-secondary)]">{{ $totalPackages }}</span>
                        </div>
                        <div class="flex items-center justify-between p-3 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40">
                            <div class="flex items-center gap-2">
                                @svg('heroicon-o-exclamation-circle', 'w-4 h-4 text-[var(--ui-muted)]')
                                <span class="text-xs text-[var(--ui-muted)]">Offene Issues</span>
                            </div>
                            <span class="text-sm font-bold text-[var(--ui-secondary)]">{{ $totalOpenIssues }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </x-ui-page-sidebar>
    </x-slot>

    {{-- Activate Modal --}}
    @if($showActivateModal)
        <x-ui-modal wire:model="showActivateModal" title="Package aktivieren">
            <div class="space-y-4">
                @if($availableRepos->isNotEmpty())
                    <x-ui-input-select name="selectedRepoId" wire:model.live="selectedRepoId" label="GitHub Repository" :nullable="true" nullLabel="-- Ohne Repository --">
                        @foreach($availableRepos as $repo)
                            <option value="{{ $repo->id }}">{{ $repo->full_name }}{{ $repo->is_private ? ' (privat)' : '' }}{{ $repo->language ? ' · '.$repo->language : '' }}</option>
                        @endforeach
                    </x-ui-input-select>
                @else
                    <div class="p-3 rounded-lg bg-[var(--ui-muted-5)] border border-[var(--ui-border)]/40">
                        <p class="text-xs text-[var(--ui-muted)]">Keine GitHub-Repositories verfuegbar. Verbinde GitHub unter Integrationen und synchronisiere deine Repos.</p>
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
