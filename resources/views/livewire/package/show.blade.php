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
            @if(!$editingPackage)
                <x-ui-button variant="secondary-outline" size="sm" wire:click="startEditingPackage">
                    @svg('heroicon-o-pencil', 'w-4 h-4')
                    <span>Bearbeiten</span>
                </x-ui-button>
            @endif
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
        @if($editingPackage)
            {{-- Package Edit Form --}}
            <div class="bg-[var(--ui-surface)] rounded-xl border border-[var(--ui-border)]/60 overflow-hidden mb-6">
                <div class="p-5 space-y-6">
                    <h4 class="text-sm font-semibold text-[var(--ui-muted)] uppercase tracking-wider">Package bearbeiten</h4>
                    <x-ui-form-grid :cols="3" :gap="6">
                        <div class="col-span-2">
                            <x-ui-input-text wire:model="editPackageName" label="Name" required />
                        </div>
                        <x-ui-input-text wire:model="editPackageIcon" label="Icon" placeholder="heroicon-o-cube" />
                    </x-ui-form-grid>

                    <x-ui-form-grid :cols="2" :gap="6">
                        <x-ui-input-select
                            name="editPackageUserInChargeId"
                            wire:model="editPackageUserInChargeId"
                            label="Verantwortlich"
                            :options="$teamUsers"
                            optionValue="id"
                            optionLabel="name"
                            :nullable="true"
                            nullLabel="– Niemand zugewiesen –"
                        />
                    </x-ui-form-grid>

                    <x-ui-input-textarea wire:model="editPackageDescription" label="Beschreibung" rows="3" />

                    <div class="d-flex items-center gap-2">
                        <x-ui-button variant="primary" size="sm" wire:click="savePackage">
                            @svg('heroicon-o-check', 'w-4 h-4')
                            <span>Speichern</span>
                        </x-ui-button>
                        <x-ui-button variant="secondary-outline" size="sm" wire:click="cancelEditPackage">Abbrechen</x-ui-button>
                    </div>
                </div>
            </div>
        @endif

        {{-- Stats --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <x-ui-dashboard-tile title="Offene Issues" :count="$totalOpen" icon="clock" variant="warning" size="lg" />
            <x-ui-dashboard-tile title="Hohe Priorität" :count="$totalHighPriority" icon="fire" variant="danger" size="lg" />
            <x-ui-dashboard-tile title="Überfällig" :count="$totalOverdue" icon="exclamation-circle" variant="danger" size="lg" />
            <x-ui-dashboard-tile title="Erledigt" :count="$totalDone" icon="check-circle" variant="success" size="lg" />
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
            {{-- Letzte Commits --}}
            <div class="lg:col-span-2 bg-[var(--ui-surface)] rounded-lg border border-[var(--ui-border)]/60">
                <div class="p-4 border-b border-[var(--ui-border)]/60">
                    <h3 class="text-sm font-semibold text-[var(--ui-secondary)]">Letzte Commits</h3>
                </div>

                <div class="divide-y divide-[var(--ui-border)]/40">
                    @forelse($recentCommits as $commit)
                        <div class="d-flex items-start gap-3 p-3">
                            <div class="flex-shrink-0 mt-0.5">
                                @svg('heroicon-o-code-bracket', 'w-4 h-4 text-[var(--ui-primary)]')
                            </div>
                            <div class="min-w-0 flex-grow-1">
                                <div class="text-sm text-[var(--ui-secondary)] truncate">{{ Str::limit(Str::before($commit->message, "\n"), 80) }}</div>
                                <div class="text-xs text-[var(--ui-muted)] mt-0.5">
                                    {{ $commit->author_login ?? $commit->author_name }}
                                    · <span class="font-mono">{{ Str::limit($commit->sha, 7, '') }}</span>
                                </div>
                            </div>
                            <div class="flex-shrink-0 text-xs text-[var(--ui-muted)] whitespace-nowrap">
                                {{ $commit->committed_at?->diffForHumans() }}
                            </div>
                        </div>
                    @empty
                        <div class="p-8 text-center">
                            @if($package->github_repo_full_name)
                                <div class="inline-flex items-center justify-center w-12 h-12 rounded-xl bg-[var(--ui-muted-5)] mb-3">
                                    @svg('heroicon-o-code-bracket', 'w-6 h-6 text-[var(--ui-muted)]')
                                </div>
                                <p class="text-sm text-[var(--ui-muted)]">Noch keine Commits synchronisiert.</p>
                                <p class="text-xs text-[var(--ui-muted)] mt-1">Commits werden automatisch stündlich geholt.</p>
                            @else
                                <p class="text-sm text-[var(--ui-muted)]">Kein GitHub Repository verknüpft.</p>
                            @endif
                        </div>
                    @endforelse
                </div>
            </div>

            {{-- Open Pull Requests --}}
            <div class="bg-[var(--ui-surface)] rounded-lg border border-[var(--ui-border)]/60">
                <div class="p-4 border-b border-[var(--ui-border)]/60">
                    <h3 class="text-sm font-semibold text-[var(--ui-secondary)]">Offene Pull Requests</h3>
                </div>
                <div class="divide-y divide-[var(--ui-border)]/40">
                    @forelse($openPullRequests as $pr)
                        <div class="p-3">
                            <div class="d-flex items-start gap-2">
                                <div class="flex-shrink-0 mt-0.5">
                                    @if($pr->is_draft)
                                        @svg('heroicon-o-document', 'w-4 h-4 text-[var(--ui-muted)]')
                                    @else
                                        @svg('heroicon-o-arrow-path', 'w-4 h-4 text-[var(--ui-success)]')
                                    @endif
                                </div>
                                <div class="min-w-0">
                                    <div class="text-sm font-medium text-[var(--ui-secondary)] truncate">{{ $pr->title }}</div>
                                    <div class="text-xs text-[var(--ui-muted)] mt-0.5">
                                        #{{ $pr->number }} · {{ $pr->author_login }}
                                        @if($pr->is_draft)
                                            · <span class="text-[var(--ui-muted)]">Draft</span>
                                        @endif
                                    </div>
                                    @if($pr->head_ref)
                                        <div class="text-xs mt-1">
                                            <span class="font-mono px-1 py-0.5 rounded bg-[var(--ui-muted-5)] text-[var(--ui-muted)]">{{ $pr->head_ref }}</span>
                                            <span class="text-[var(--ui-muted)] mx-1">&rarr;</span>
                                            <span class="font-mono px-1 py-0.5 rounded bg-[var(--ui-muted-5)] text-[var(--ui-muted)]">{{ $pr->base_ref }}</span>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="p-4 text-center">
                            <p class="text-xs text-[var(--ui-muted)]">Keine offenen PRs.</p>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- Issues --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            {{-- Letzte offene Issues --}}
            <div class="bg-[var(--ui-surface)] rounded-lg border border-[var(--ui-border)]/60">
                <div class="p-4 border-b border-[var(--ui-border)]/60">
                    <h3 class="text-sm font-semibold text-[var(--ui-secondary)]">Letzte offene Issues</h3>
                </div>
                <div class="divide-y divide-[var(--ui-border)]/40">
                    @forelse($recentIssues as $issue)
                        <a href="{{ route('dev.packages.issues.show', [$package, $issue]) }}"
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
                                    {{ $issue->board->name }}
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
                        <div class="p-6 text-center">
                            <p class="text-sm text-[var(--ui-muted)]">Keine offenen Issues.</p>
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
                        <a href="{{ route('dev.packages.issues.show', [$package, $issue]) }}"
                           wire:navigate
                           class="d-flex items-center gap-3 p-3 hover:bg-[var(--ui-muted-5)] transition-colors">
                            <div class="flex-shrink-0">
                                @svg('heroicon-o-check-circle', 'w-4 h-4 text-[var(--ui-success)]')
                            </div>
                            <div class="min-w-0 flex-grow-1">
                                <div class="text-sm text-[var(--ui-muted)] line-through truncate">{{ $issue->title }}</div>
                                <div class="text-xs text-[var(--ui-muted)]">
                                    {{ $issue->board->name }}
                                    @if($issue->done_at)
                                        · {{ $issue->done_at->diffForHumans() }}
                                    @endif
                                </div>
                            </div>
                        </a>
                    @empty
                        <div class="p-6 text-center">
                            <p class="text-sm text-[var(--ui-muted)]">Noch nichts erledigt.</p>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </x-ui-page-container>
</x-ui-page>
