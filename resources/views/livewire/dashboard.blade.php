<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Dev" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Dev', 'icon' => 'code-bracket'],
        ]">
            <x-ui-button variant="secondary-outline" size="sm" wire:click="$set('showErrorTracking', true)">
                @svg('heroicon-o-bug-ant', 'w-4 h-4')
                <span>Error Tracking</span>
            </x-ui-button>
            <x-ui-button variant="secondary-outline" size="sm" wire:click="openActivateModal">
                @svg('heroicon-o-plus', 'w-4 h-4')
                <span>Package aktivieren</span>
            </x-ui-button>
        </x-ui-page-actionbar>
    </x-slot>

    <x-ui-page-container>
        {{-- Hero Section --}}
        <div class="relative bg-[var(--ui-surface)] rounded-xl border border-[var(--ui-border)]/60 overflow-hidden mb-8">
            <div class="absolute inset-x-0 top-0 h-1 bg-gradient-to-r from-[var(--ui-primary)] via-[var(--ui-primary)]/70 to-[var(--ui-success)]"></div>
            <div class="p-5 d-flex items-center justify-between">
                <div class="d-flex items-center gap-4">
                    <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-[var(--ui-primary)]/20 to-[var(--ui-success)]/10 d-flex items-center justify-center flex-shrink-0">
                        @svg('heroicon-o-command-line', 'w-6 h-6 text-[var(--ui-primary)]')
                    </div>
                    <div>
                        <h1 class="text-lg font-bold text-[var(--ui-secondary)] tracking-tight">Dev Command Center</h1>
                        <p class="text-xs text-[var(--ui-muted)] mt-0.5">{{ $totalPackages }} {{ $totalPackages === 1 ? 'Package' : 'Packages' }} aktiv</p>
                    </div>
                </div>
                <div class="d-flex items-center gap-4">
                    <x-ui-dashboard-tile title="Offen" :count="$totalOpen" icon="clock" variant="warning" size="sm" />
                    <x-ui-dashboard-tile title="Hoch" :count="$totalHighPriority" icon="fire" variant="danger" size="sm" />
                    <x-ui-dashboard-tile title="Ueberfaellig" :count="$totalOverdue" icon="exclamation-circle" variant="danger" size="sm" />
                    <x-ui-dashboard-tile title="Erledigt" :count="$totalDone" icon="check-circle" variant="success" size="sm" />
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
            {{-- Letzte Commits --}}
            <div class="lg:col-span-2 bg-[var(--ui-surface)] rounded-xl border border-[var(--ui-border)]/60 overflow-hidden">
                <div class="p-4 border-b border-[var(--ui-border)]/60 d-flex items-center gap-2">
                    @svg('heroicon-o-code-bracket', 'w-4 h-4 text-[var(--ui-primary)]')
                    <h3 class="text-sm font-semibold text-[var(--ui-secondary)]">Letzte Commits</h3>
                    @if($recentCommits->isNotEmpty())
                        <span class="text-xs px-1.5 py-0.5 rounded-full bg-[var(--ui-muted-5)] text-[var(--ui-muted)] font-medium">{{ $recentCommits->count() }}</span>
                    @endif
                </div>

                <div>
                    @forelse($recentCommits as $commit)
                        <div class="d-flex items-start gap-3 px-4 py-2.5 hover:bg-[var(--ui-muted-5)] transition-colors group">
                            {{-- Git Graph Dot --}}
                            <div class="flex-shrink-0 d-flex flex-col items-center mt-1" style="width: 12px;">
                                <div class="w-2.5 h-2.5 rounded-full border-2 border-[var(--ui-primary)] bg-[var(--ui-surface)]"></div>
                                @if(!$loop->last)
                                    <div class="w-px flex-grow-1 bg-[var(--ui-primary)]/20 mt-0.5" style="min-height: 20px;"></div>
                                @endif
                            </div>
                            <div class="min-w-0 flex-grow-1">
                                <div class="text-sm text-[var(--ui-secondary)] truncate group-hover:text-[var(--ui-primary)] transition-colors">{{ Str::limit(Str::before($commit->message, "\n"), 80) }}</div>
                                <div class="text-xs text-[var(--ui-muted)] mt-0.5 d-flex items-center gap-1.5">
                                    <span>{{ $commit->author_login ?? $commit->author_name }}</span>
                                    <span>&middot;</span>
                                    <span>{{ $commit->repo->name ?? '' }}</span>
                                    <span>&middot;</span>
                                    <span class="font-mono px-1 py-px rounded bg-[var(--ui-muted-5)] text-[10px]">{{ Str::limit($commit->sha, 7, '') }}</span>
                                </div>
                            </div>
                            <div class="flex-shrink-0 text-xs text-[var(--ui-muted)] whitespace-nowrap opacity-0 group-hover:opacity-100 transition-opacity">
                                {{ $commit->committed_at?->diffForHumans() }}
                            </div>
                        </div>
                    @empty
                        <div class="p-10 text-center">
                            <div class="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-gradient-to-br from-[var(--ui-primary)]/10 to-[var(--ui-muted-5)] mb-3">
                                @svg('heroicon-o-code-bracket', 'w-7 h-7 text-[var(--ui-muted)]')
                            </div>
                            <p class="text-sm font-medium text-[var(--ui-secondary)] mb-1">Noch keine Commits</p>
                            <p class="text-xs text-[var(--ui-muted)]">Commits werden automatisch stuendlich synchronisiert.</p>
                        </div>
                    @endforelse
                </div>
            </div>

            {{-- Rechte Spalte: Open PRs + Packages --}}
            <div class="space-y-6">
                {{-- Open Pull Requests --}}
                <div class="bg-[var(--ui-surface)] rounded-xl border border-[var(--ui-border)]/60 overflow-hidden">
                    <div class="p-4 border-b border-[var(--ui-border)]/60 d-flex items-center gap-2">
                        @svg('heroicon-o-arrow-path', 'w-4 h-4 text-[var(--ui-success)]')
                        <h3 class="text-sm font-semibold text-[var(--ui-secondary)]">Offene Pull Requests</h3>
                        @if($openPullRequests->isNotEmpty())
                            <span class="text-xs px-1.5 py-0.5 rounded-full bg-[var(--ui-success)]/10 text-[var(--ui-success)] font-medium">{{ $openPullRequests->count() }}</span>
                        @endif
                    </div>
                    <div class="divide-y divide-[var(--ui-border)]/40">
                        @forelse($openPullRequests as $pr)
                            <div class="p-3 hover:bg-[var(--ui-muted-5)] transition-colors">
                                <div class="d-flex items-start gap-2">
                                    <div class="flex-shrink-0 mt-0.5">
                                        @if($pr->is_draft)
                                            <div class="w-4 h-4 rounded-full border-2 border-dashed border-[var(--ui-muted)] d-flex items-center justify-center">
                                                <div class="w-1.5 h-1.5 rounded-full bg-[var(--ui-muted)]"></div>
                                            </div>
                                        @else
                                            @svg('heroicon-o-arrow-path', 'w-4 h-4 text-[var(--ui-success)]')
                                        @endif
                                    </div>
                                    <div class="min-w-0">
                                        <div class="text-sm font-medium text-[var(--ui-secondary)] truncate">{{ $pr->title }}</div>
                                        <div class="text-xs text-[var(--ui-muted)] mt-0.5">
                                            #{{ $pr->number }} · {{ $pr->author_login }} · {{ $pr->repo->name ?? '' }}
                                            @if($pr->is_draft)
                                                · <span class="italic">Draft</span>
                                            @endif
                                        </div>
                                        @if($pr->head_ref)
                                            <div class="d-flex items-center gap-1.5 mt-1.5">
                                                <span class="font-mono px-1.5 py-0.5 rounded bg-[var(--ui-primary-5)] text-[var(--ui-primary)] text-[10px]">{{ $pr->head_ref }}</span>
                                                @svg('heroicon-o-arrow-right', 'w-3 h-3 text-[var(--ui-muted)]')
                                                <span class="font-mono px-1.5 py-0.5 rounded bg-[var(--ui-muted-5)] text-[var(--ui-muted)] text-[10px]">{{ $pr->base_ref }}</span>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="p-8 text-center">
                                <div class="inline-flex items-center justify-center w-10 h-10 rounded-xl bg-gradient-to-br from-[var(--ui-success)]/10 to-[var(--ui-muted-5)] mb-2">
                                    @svg('heroicon-o-check-circle', 'w-5 h-5 text-[var(--ui-success)]')
                                </div>
                                <p class="text-xs text-[var(--ui-muted)]">Keine offenen PRs.</p>
                            </div>
                        @endforelse
                    </div>
                </div>

                {{-- Packages --}}
                <div class="bg-[var(--ui-surface)] rounded-xl border border-[var(--ui-border)]/60 overflow-hidden">
                    <div class="p-4 border-b border-[var(--ui-border)]/60 d-flex items-center gap-2">
                        @svg('heroicon-o-cube', 'w-4 h-4 text-[var(--ui-primary)]')
                        <h3 class="text-sm font-semibold text-[var(--ui-secondary)]">Packages</h3>
                        @if($packages->isNotEmpty())
                            <span class="text-xs px-1.5 py-0.5 rounded-full bg-[var(--ui-muted-5)] text-[var(--ui-muted)] font-medium">{{ $packages->count() }}</span>
                        @endif
                    </div>
                    <div class="p-3 space-y-2">
                        @forelse($packages as $package)
                            <a href="{{ route('dev.packages.show', $package) }}"
                               wire:navigate
                               class="group block p-3 rounded-lg border border-[var(--ui-border)]/40 hover:border-[var(--ui-primary)]/40 hover:shadow-md transition-all duration-200">
                                <div class="d-flex items-center gap-3 mb-2">
                                    <div class="w-10 h-10 rounded-lg bg-gradient-to-br from-[var(--ui-primary)]/15 to-[var(--ui-primary)]/5 d-flex items-center justify-center flex-shrink-0 group-hover:scale-105 transition-transform">
                                        @svg($package->icon ?? 'heroicon-o-cube', 'w-5 h-5 text-[var(--ui-primary)]')
                                    </div>
                                    <div class="min-w-0 flex-grow-1">
                                        <span class="text-sm font-medium text-[var(--ui-secondary)] truncate block">{{ $package->name }}</span>
                                        @if($package->github_repo_full_name)
                                            <span class="text-xs text-[var(--ui-muted)] font-mono truncate block">{{ $package->github_repo_full_name }}</span>
                                        @endif
                                    </div>
                                    <div class="flex-shrink-0 opacity-0 group-hover:opacity-100 transition-opacity">
                                        @svg('heroicon-o-chevron-right', 'w-4 h-4 text-[var(--ui-muted)]')
                                    </div>
                                </div>
                                <div class="d-flex items-center gap-2 ml-13">
                                    @if(($packageStats[$package->id]['open_features'] ?? 0) > 0)
                                        <span class="inline-flex items-center gap-1 text-xs px-2 py-0.5 rounded-full bg-[var(--ui-primary-5)] text-[var(--ui-primary)]">
                                            @svg('heroicon-o-light-bulb', 'w-3 h-3')
                                            {{ $packageStats[$package->id]['open_features'] }}
                                        </span>
                                    @endif
                                    @if(($packageStats[$package->id]['open_bugs'] ?? 0) > 0)
                                        <span class="inline-flex items-center gap-1 text-xs px-2 py-0.5 rounded-full bg-red-500/10 text-[var(--ui-danger)]">
                                            @svg('heroicon-o-bug-ant', 'w-3 h-3')
                                            {{ $packageStats[$package->id]['open_bugs'] }}
                                        </span>
                                    @endif
                                    @if(($packageStats[$package->id]['open_features'] ?? 0) === 0 && ($packageStats[$package->id]['open_bugs'] ?? 0) === 0)
                                        <span class="inline-flex items-center gap-1 text-xs text-[var(--ui-success)]">
                                            @svg('heroicon-o-check-circle', 'w-3 h-3')
                                            All clear
                                        </span>
                                    @endif
                                </div>
                            </a>
                        @empty
                            <div class="p-8 text-center">
                                <div class="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-gradient-to-br from-[var(--ui-primary)]/10 to-[var(--ui-muted-5)] mb-3">
                                    @svg('heroicon-o-cube', 'w-7 h-7 text-[var(--ui-muted)]')
                                </div>
                                <p class="text-sm font-medium text-[var(--ui-secondary)] mb-1">Noch keine Packages</p>
                                <p class="text-xs text-[var(--ui-muted)] mb-3">Aktiviere dein erstes Package.</p>
                                <x-ui-button variant="primary" size="sm" wire:click="openActivateModal">
                                    @svg('heroicon-o-plus', 'w-4 h-4')
                                    <span>Package aktivieren</span>
                                </x-ui-button>
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>

        {{-- Zweite Reihe: Offene Issues + Zuletzt erledigt --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            {{-- Letzte offene Issues --}}
            <div class="bg-[var(--ui-surface)] rounded-xl border border-[var(--ui-border)]/60 overflow-hidden">
                <div class="p-4 border-b border-[var(--ui-border)]/60 d-flex items-center gap-2">
                    @svg('heroicon-o-clock', 'w-4 h-4 text-[var(--ui-warning)]')
                    <h3 class="text-sm font-semibold text-[var(--ui-secondary)]">Letzte offene Issues</h3>
                    @if($recentIssues->isNotEmpty())
                        <span class="text-xs px-1.5 py-0.5 rounded-full bg-[var(--ui-warning)]/10 text-[var(--ui-warning)] font-medium">{{ $recentIssues->count() }}</span>
                    @endif
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
                            <div class="inline-flex items-center justify-center w-10 h-10 rounded-xl bg-gradient-to-br from-[var(--ui-success)]/10 to-[var(--ui-muted-5)] mb-2">
                                @svg('heroicon-o-check-circle', 'w-5 h-5 text-[var(--ui-success)]')
                            </div>
                            <p class="text-sm text-[var(--ui-muted)]">Keine offenen Issues.</p>
                        </div>
                    @endforelse
                </div>
            </div>

            {{-- Zuletzt erledigt --}}
            <div class="bg-[var(--ui-surface)] rounded-xl border border-[var(--ui-border)]/60 overflow-hidden">
                <div class="p-4 border-b border-[var(--ui-border)]/60 d-flex items-center gap-2">
                    @svg('heroicon-o-check-circle', 'w-4 h-4 text-[var(--ui-success)]')
                    <h3 class="text-sm font-semibold text-[var(--ui-secondary)]">Zuletzt erledigt</h3>
                    @if($recentlyDone->isNotEmpty())
                        <span class="text-xs px-1.5 py-0.5 rounded-full bg-[var(--ui-success)]/10 text-[var(--ui-success)] font-medium">{{ $recentlyDone->count() }}</span>
                    @endif
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
                        <div class="p-8 text-center">
                            <div class="inline-flex items-center justify-center w-10 h-10 rounded-xl bg-gradient-to-br from-[var(--ui-muted-5)] to-[var(--ui-muted-5)] mb-2">
                                @svg('heroicon-o-clipboard-document-check', 'w-5 h-5 text-[var(--ui-muted)]')
                            </div>
                            <p class="text-sm text-[var(--ui-muted)]">Noch nichts erledigt.</p>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </x-ui-page-container>

    {{-- Error Tracking Modal --}}
    @if($showErrorTracking)
        <x-ui-modal wire:model="showErrorTracking" title="Error Tracking">
            <div class="space-y-6">
                {{-- Status --}}
                <div class="d-flex items-center gap-3 p-3 rounded-lg {{ $errorEndpointConfigured ? 'bg-[var(--ui-success)]/10 border border-[var(--ui-success)]/30' : 'bg-[var(--ui-warning)]/10 border border-[var(--ui-warning)]/30' }}">
                    @if($errorEndpointConfigured)
                        @svg('heroicon-s-check-circle', 'w-5 h-5 text-[var(--ui-success)] flex-shrink-0')
                        <span class="text-sm text-[var(--ui-success)]">Error Tracking ist aktiv auf dieser Instanz.</span>
                    @else
                        @svg('heroicon-s-exclamation-triangle', 'w-5 h-5 text-[var(--ui-warning)] flex-shrink-0')
                        <span class="text-sm text-[var(--ui-warning)]">DEV_ERROR_ENDPOINT ist nicht konfiguriert.</span>
                    @endif
                </div>

                {{-- Ingest URL --}}
                <div>
                    <h4 class="text-sm font-semibold text-[var(--ui-secondary)] mb-3">Ingest Endpoint</h4>
                    <p class="text-xs text-[var(--ui-muted)] mb-3">Dieser Endpoint empfaengt Fehler von allen Instanzen. Ein Token, ein Endpoint fuer das ganze Team.</p>

                    @if($ingestUrl)
                        <div class="space-y-3">
                            <div class="p-3 rounded-lg bg-[var(--ui-muted-5)] border border-[var(--ui-border)]/40">
                                <div class="text-xs text-[var(--ui-muted)] mb-1">Ingest URL</div>
                                <code class="text-xs text-[var(--ui-secondary)] break-all select-all">{{ $ingestUrl }}</code>
                            </div>
                            <div class="p-3 rounded-lg bg-[var(--ui-primary-5)] border border-[var(--ui-primary)]/20">
                                <div class="text-xs text-[var(--ui-muted)] mb-1">.env Variable (in jede sendende Instanz eintragen)</div>
                                <code class="text-xs text-[var(--ui-primary)] font-bold select-all">DEV_ERROR_ENDPOINT={{ $ingestUrl }}</code>
                            </div>
                            <p class="text-xs text-[var(--ui-muted)]">Nach dem Setzen: <code class="font-mono">php artisan config:cache</code> ausfuehren.</p>
                            <x-ui-button variant="danger-outline" size="sm" wire:click="regenerateTeamToken" wire:confirm="Token neu generieren? Alle Instanzen muessen dann die neue URL erhalten.">
                                @svg('heroicon-o-arrow-path', 'w-3.5 h-3.5')
                                <span>Token neu generieren</span>
                            </x-ui-button>
                        </div>
                    @else
                        <div class="text-center py-4">
                            <p class="text-sm text-[var(--ui-muted)] mb-3">Noch kein Ingest-Token generiert.</p>
                            <x-ui-button variant="primary" size="sm" wire:click="generateTeamToken">
                                @svg('heroicon-o-shield-check', 'w-4 h-4')
                                <span>Token generieren</span>
                            </x-ui-button>
                        </div>
                    @endif
                </div>

                {{-- How it works --}}
                <div class="p-3 rounded-lg bg-[var(--ui-muted-5)] border border-[var(--ui-border)]/40">
                    <h4 class="text-xs font-semibold text-[var(--ui-secondary)] mb-2">So funktioniert's</h4>
                    <ol class="text-xs text-[var(--ui-muted)] space-y-1 list-decimal list-inside">
                        <li>Token generieren (oben)</li>
                        <li><code class="font-mono">DEV_ERROR_ENDPOINT</code> in .env jeder Instanz eintragen</li>
                        <li><code class="font-mono">php artisan config:cache</code></li>
                        <li>Fehler werden automatisch dem richtigen Package zugeordnet</li>
                        <li>Per-Package Settings (welche Codes, auto-Issue, etc.) unter dem jeweiligen Package konfigurieren</li>
                    </ol>
                </div>
            </div>
            <x-slot name="footer">
                <x-ui-button variant="secondary-outline" wire:click="$set('showErrorTracking', false)">Schliessen</x-ui-button>
            </x-slot>
        </x-ui-modal>
    @endif

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
