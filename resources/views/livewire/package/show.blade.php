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
                {{-- Boards Dropdown --}}
                <div x-data="{ open: false }" class="relative">
                    <button @click="open = !open" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium text-[var(--ui-secondary)] border border-[var(--ui-border)] rounded-md hover:bg-[var(--ui-muted-5)] transition-colors">
                        @svg('heroicon-o-view-columns', 'w-4 h-4 text-[var(--ui-muted)]')
                        Boards
                        <span class="text-xs px-1.5 py-0.5 rounded-full bg-[var(--ui-muted-5)] text-[var(--ui-muted)] font-medium">{{ $boards->count() }}</span>
                        @svg('heroicon-o-chevron-down', 'w-3 h-3 text-[var(--ui-muted)]')
                    </button>
                    <div
                        x-show="open"
                        x-transition:enter="transition ease-out duration-100"
                        x-transition:enter-start="opacity-0 scale-95"
                        x-transition:enter-end="opacity-100 scale-100"
                        x-transition:leave="transition ease-in duration-75"
                        x-transition:leave-start="opacity-100 scale-100"
                        x-transition:leave-end="opacity-0 scale-95"
                        @click.outside="open = false"
                        class="absolute left-0 mt-1 w-64 rounded-lg bg-[var(--ui-surface)] shadow-lg ring-1 ring-[var(--ui-border)] z-50 py-1 top-full"
                        style="display: none;"
                    >
                        @foreach($boards as $board)
                            <a href="{{ route('dev.packages.boards.show', [$package, $board]) }}"
                               wire:navigate
                               @click="open = false"
                               class="flex items-center gap-2.5 px-3 py-2 text-sm text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)] transition-colors">
                                @if($board->type->value === 'bug')
                                    @svg('heroicon-o-bug-ant', 'w-4 h-4 text-[var(--ui-danger)] flex-shrink-0')
                                @else
                                    @svg('heroicon-o-light-bulb', 'w-4 h-4 text-[var(--ui-primary)] flex-shrink-0')
                                @endif
                                <span class="flex-grow-1 truncate">{{ $board->name }}</span>
                                <span class="text-xs px-1.5 py-0.5 rounded-full bg-[var(--ui-muted-5)] text-[var(--ui-muted)] font-medium">{{ $board->open_issues_count }}</span>
                            </a>
                        @endforeach
                        @if($archivedBoards->isNotEmpty())
                            <div class="border-t border-[var(--ui-border)]/40 my-1"></div>
                            <div class="px-3 py-1.5 text-xs font-medium text-[var(--ui-muted)] uppercase tracking-wider">Archiviert</div>
                            @foreach($archivedBoards as $archivedBoard)
                                <div class="flex items-center gap-2.5 px-3 py-2 text-sm text-[var(--ui-muted)]">
                                    @svg('heroicon-o-archive-box', 'w-4 h-4 flex-shrink-0')
                                    <span class="flex-grow-1 truncate">{{ $archivedBoard->name }}</span>
                                    <button wire:click="reactivateBoard({{ $archivedBoard->id }})"
                                            @click="open = false"
                                            class="p-0.5 rounded text-[var(--ui-muted)] hover:text-[var(--ui-primary)] transition-colors"
                                            title="Reaktivieren">
                                        @svg('heroicon-o-arrow-path', 'w-3.5 h-3.5')
                                    </button>
                                </div>
                            @endforeach
                        @endif
                        <div class="border-t border-[var(--ui-border)]/40 my-1"></div>
                        <button wire:click="$set('showCreateBoardModal', true)"
                                @click="open = false"
                                class="flex items-center gap-2.5 w-full px-3 py-2 text-sm text-[var(--ui-primary)] hover:bg-[var(--ui-muted-5)] transition-colors text-left">
                            @svg('heroicon-o-plus', 'w-4 h-4')
                            Neues Feature Board
                        </button>
                    </div>
                </div>
            </x-slot>
            @if(!$editingPackage)
                <x-ui-button variant="secondary-outline" size="sm" wire:click="openErrorSettings">
                    @svg('heroicon-o-bug-ant', 'w-4 h-4')
                    <span>Error Tracking</span>
                </x-ui-button>
                <x-ui-button variant="secondary-outline" size="sm" wire:click="startEditingPackage">
                    @svg('heroicon-o-pencil', 'w-4 h-4')
                    <span>Bearbeiten</span>
                </x-ui-button>
            @endif
        </x-ui-page-actionbar>
    </x-slot>

    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Uebersicht" width="w-80" :defaultOpen="true" storeKey="sidebarOpen" side="left">
            <div class="p-6 space-y-6">
                {{-- Package Header --}}
                <div class="d-flex items-center gap-3">
                    <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-[var(--ui-primary)]/20 to-[var(--ui-primary)]/5 d-flex items-center justify-center flex-shrink-0">
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

                {{-- Health Progress Bar --}}
                @php
                    $totalIssues = $totalOpen + $totalDone;
                    $progressPct = $totalIssues > 0 ? round($totalDone / $totalIssues * 100) : 0;
                @endphp
                @if($totalIssues > 0)
                    <div class="py-2 px-3 rounded-lg bg-[var(--ui-muted-5)] border border-[var(--ui-border)]/40">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-xs font-medium text-[var(--ui-secondary)]">Fortschritt</span>
                            <span class="text-xs font-semibold {{ $progressPct === 100 ? 'text-[var(--ui-success)]' : 'text-[var(--ui-secondary)]' }}">{{ $totalDone }}/{{ $totalIssues }}</span>
                        </div>
                        <div class="w-full h-1.5 rounded-full bg-[var(--ui-border)]/40 overflow-hidden">
                            <div class="h-full rounded-full bg-gradient-to-r from-[var(--ui-primary)] to-[var(--ui-success)] transition-all" style="width: {{ $progressPct }}%"></div>
                        </div>
                    </div>
                @endif

                {{-- Board Shortcuts --}}
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-3">Boards</h3>
                    <div class="space-y-1.5">
                        @foreach($boards as $board)
                            <a href="{{ route('dev.packages.boards.show', [$package, $board]) }}"
                               wire:navigate
                               class="d-flex items-center gap-2.5 py-2 px-3 rounded-lg hover:bg-[var(--ui-muted-5)] transition-colors">
                                @if($board->type === 'bug')
                                    @svg('heroicon-o-bug-ant', 'w-4 h-4 text-[var(--ui-danger)] flex-shrink-0')
                                @elseif($board->type === 'feature')
                                    @svg('heroicon-o-light-bulb', 'w-4 h-4 text-[var(--ui-primary)] flex-shrink-0')
                                @else
                                    @svg('heroicon-o-view-columns', 'w-4 h-4 text-[var(--ui-muted)] flex-shrink-0')
                                @endif
                                <span class="text-sm text-[var(--ui-secondary)] flex-grow-1">{{ $board->name }}</span>
                                <span class="text-xs px-1.5 py-0.5 rounded-full bg-[var(--ui-muted-5)] text-[var(--ui-muted)] font-medium">{{ $board->open_issues_count }}</span>
                            </a>
                        @endforeach
                    </div>
                </div>

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
                        <div class="flex justify-between">
                            <span class="text-[var(--ui-muted)]">Error Tracking:</span>
                            <span class="font-medium {{ $errorSettingsEnabled ? 'text-[var(--ui-success)]' : 'text-[var(--ui-muted)]' }}">
                                {{ $errorSettingsEnabled ? 'Aktiv' : 'Inaktiv' }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </x-ui-page-sidebar>
    </x-slot>

    <x-slot name="activity">
        <x-ui-page-sidebar title="Aktivitaeten" width="w-80" :defaultOpen="false" storeKey="activityOpen" side="right">
            <div class="p-4 space-y-4">
                <div class="text-sm text-[var(--ui-muted)]">Letzte Aktivitaeten</div>
                <div class="space-y-3 text-sm">
                    @foreach(($activities ?? []) as $activity)
                        <div class="p-2 rounded border border-[var(--ui-border)]/60 bg-[var(--ui-muted-5)]">
                            <div class="font-medium text-[var(--ui-secondary)] truncate">{{ $activity['title'] ?? 'Aktivitaet' }}</div>
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
            <x-ui-dashboard-tile title="Hohe Prioritaet" :count="$totalHighPriority" icon="fire" variant="danger" size="lg" />
            <x-ui-dashboard-tile title="Ueberfaellig" :count="$totalOverdue" icon="exclamation-circle" variant="danger" size="lg" />
            <x-ui-dashboard-tile title="Erledigt" :count="$totalDone" icon="check-circle" variant="success" size="lg" />
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
                                    <span class="font-mono px-1 py-px rounded bg-[var(--ui-muted-5)] text-[10px]">{{ Str::limit($commit->sha, 7, '') }}</span>
                                </div>
                            </div>
                            <div class="flex-shrink-0 text-xs text-[var(--ui-muted)] whitespace-nowrap opacity-0 group-hover:opacity-100 transition-opacity">
                                {{ $commit->committed_at?->diffForHumans() }}
                            </div>
                        </div>
                    @empty
                        <div class="p-10 text-center">
                            @if($package->github_repo_full_name)
                                <div class="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-gradient-to-br from-[var(--ui-primary)]/10 to-[var(--ui-muted-5)] mb-3">
                                    @svg('heroicon-o-code-bracket', 'w-7 h-7 text-[var(--ui-muted)]')
                                </div>
                                <p class="text-sm font-medium text-[var(--ui-secondary)] mb-1">Noch keine Commits</p>
                                <p class="text-xs text-[var(--ui-muted)]">Commits werden automatisch stuendlich synchronisiert.</p>
                            @else
                                <div class="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-gradient-to-br from-[var(--ui-muted-5)] to-[var(--ui-muted-5)] mb-3">
                                    @svg('heroicon-o-link-slash', 'w-7 h-7 text-[var(--ui-muted)]')
                                </div>
                                <p class="text-sm text-[var(--ui-muted)]">Kein GitHub Repository verknuepft.</p>
                            @endif
                        </div>
                    @endforelse
                </div>
            </div>

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
                                        #{{ $pr->number }} · {{ $pr->author_login }}
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
        </div>

        {{-- Error Occurrences --}}
        @if($errorSettingsEnabled && $errorOccurrences->count() > 0)
            <div class="bg-[var(--ui-surface)] rounded-xl border border-[var(--ui-danger)]/30 overflow-hidden mb-6">
                <div class="p-4 border-b border-[var(--ui-border)]/60 d-flex items-center justify-between">
                    <div class="d-flex items-center gap-2">
                        <span class="relative flex h-3 w-3">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-[var(--ui-danger)] opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-3 w-3 bg-[var(--ui-danger)]"></span>
                        </span>
                        <h3 class="text-sm font-semibold text-[var(--ui-secondary)]">Offene Errors</h3>
                        <span class="text-xs px-1.5 py-0.5 rounded-full bg-[var(--ui-danger)]/10 text-[var(--ui-danger)] font-medium">{{ $errorOccurrences->count() }}</span>
                    </div>
                </div>
                <div class="divide-y divide-[var(--ui-border)]/40">
                    @foreach($errorOccurrences as $occurrence)
                        <div class="p-3 d-flex items-start gap-3 group border-l-3 {{ $occurrence->http_code >= 500 ? 'border-l-[var(--ui-danger)]' : 'border-l-[var(--ui-warning)]' }}">
                            <div class="flex-shrink-0 mt-0.5">
                                @if($occurrence->http_code >= 500)
                                    @svg('heroicon-s-exclamation-triangle', 'w-4 h-4 text-[var(--ui-danger)]')
                                @else
                                    @svg('heroicon-o-exclamation-circle', 'w-4 h-4 text-[var(--ui-warning)]')
                                @endif
                            </div>
                            <div class="min-w-0 flex-grow-1">
                                <div class="text-sm font-medium text-[var(--ui-secondary)] truncate">
                                    @if($occurrence->http_code)
                                        <span class="font-mono text-xs px-1 py-0.5 rounded bg-[var(--ui-danger)]/10 text-[var(--ui-danger)] mr-1">{{ $occurrence->http_code }}</span>
                                    @endif
                                    {{ $occurrence->getShortExceptionClass() }}
                                </div>
                                <div class="text-xs text-[var(--ui-muted)] mt-0.5 truncate">{{ Str::limit($occurrence->message, 100) }}</div>
                                <div class="text-xs text-[var(--ui-muted)] mt-0.5 font-mono">{{ Str::afterLast($occurrence->file ?? '', '/') }}:{{ $occurrence->line }}</div>
                            </div>
                            <div class="flex-shrink-0 text-right">
                                <div class="text-xs text-[var(--ui-muted)]">{{ $occurrence->last_seen_at?->diffForHumans() }}</div>
                                @if($occurrence->occurrence_count > 1)
                                    <div class="text-xs font-medium text-[var(--ui-danger)]">{{ $occurrence->occurrence_count }}x</div>
                                @endif
                            </div>
                            <div class="flex-shrink-0 d-flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                <button wire:click="resolveOccurrence({{ $occurrence->id }})" class="p-1 rounded hover:bg-[var(--ui-success)]/10 text-[var(--ui-muted)] hover:text-[var(--ui-success)] transition-colors" title="Resolve">
                                    @svg('heroicon-o-check-circle', 'w-4 h-4')
                                </button>
                                <button wire:click="ignoreOccurrence({{ $occurrence->id }})" class="p-1 rounded hover:bg-[var(--ui-muted-5)] text-[var(--ui-muted)] hover:text-[var(--ui-secondary)] transition-colors" title="Ignorieren">
                                    @svg('heroicon-o-eye-slash', 'w-4 h-4')
                                </button>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Issues + Discussions --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
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

        {{-- Recent Discussions --}}
        @if($recentDiscussions->isNotEmpty() || $discussionCount > 0)
            <div class="bg-[var(--ui-surface)] rounded-xl border border-[var(--ui-border)]/60 overflow-hidden">
                <div class="p-4 border-b border-[var(--ui-border)]/60 d-flex items-center justify-between">
                    <div class="d-flex items-center gap-2">
                        @svg('heroicon-o-chat-bubble-left-right', 'w-4 h-4 text-[var(--ui-primary)]')
                        <h3 class="text-sm font-semibold text-[var(--ui-secondary)]">Diskussionen</h3>
                        @if($discussionCount > 0)
                            <span class="text-xs px-1.5 py-0.5 rounded-full bg-[var(--ui-muted-5)] text-[var(--ui-muted)] font-medium">{{ $discussionCount }}</span>
                        @endif
                    </div>
                    <a href="{{ route('dev.packages.discussions', $package) }}" wire:navigate class="text-xs text-[var(--ui-primary)] hover:underline">
                        Alle anzeigen
                    </a>
                </div>
                <div class="divide-y divide-[var(--ui-border)]/40">
                    @foreach($recentDiscussions as $discussion)
                        <a href="{{ route('dev.packages.discussions', $package) }}"
                           wire:navigate
                           class="d-flex items-center gap-3 p-3 hover:bg-[var(--ui-muted-5)] transition-colors">
                            <div class="flex-shrink-0">
                                @if($discussion->is_pinned)
                                    @svg('heroicon-s-bookmark', 'w-4 h-4 text-[var(--ui-primary)]')
                                @else
                                    @svg('heroicon-o-chat-bubble-left', 'w-4 h-4 text-[var(--ui-muted)]')
                                @endif
                            </div>
                            <div class="min-w-0 flex-grow-1">
                                <div class="text-sm font-medium text-[var(--ui-secondary)] truncate">{{ $discussion->title }}</div>
                                <div class="text-xs text-[var(--ui-muted)]">
                                    {{ $discussion->createdBy?->name }}
                                    · {{ $discussion->replies_count }} {{ $discussion->replies_count === 1 ? 'Antwort' : 'Antworten' }}
                                </div>
                            </div>
                            <div class="flex-shrink-0 text-xs text-[var(--ui-muted)]">
                                {{ $discussion->created_at->diffForHumans() }}
                            </div>
                        </a>
                    @endforeach
                </div>
            </div>
        @endif
        {{-- Documentation --}}
        @if($docPages->isNotEmpty())
            <div class="bg-[var(--ui-surface)] rounded-xl border border-[var(--ui-border)]/60 overflow-hidden mt-6">
                <div class="p-4 border-b border-[var(--ui-border)]/60 d-flex items-center justify-between">
                    <div class="d-flex items-center gap-2">
                        @svg('heroicon-o-document-text', 'w-4 h-4 text-[var(--ui-primary)]')
                        <h3 class="text-sm font-semibold text-[var(--ui-secondary)]">Dokumentation</h3>
                        <span class="text-xs px-1.5 py-0.5 rounded-full bg-[var(--ui-muted-5)] text-[var(--ui-muted)] font-medium">{{ $docPages->count() }}</span>
                    </div>
                </div>
                <div class="divide-y divide-[var(--ui-border)]/40">
                    @foreach($docPages as $docPage)
                        <div class="d-flex items-center gap-3 p-3 hover:bg-[var(--ui-muted-5)] transition-colors">
                            <div class="flex-shrink-0">
                                @if($docPage->type->value === 'overview')
                                    @svg('heroicon-o-home', 'w-4 h-4 text-[var(--ui-primary)]')
                                @elseif($docPage->type->value === 'architecture')
                                    @svg('heroicon-o-cube-transparent', 'w-4 h-4 text-[var(--ui-primary)]')
                                @elseif($docPage->type->value === 'setup')
                                    @svg('heroicon-o-cog-6-tooth', 'w-4 h-4 text-[var(--ui-primary)]')
                                @elseif($docPage->type->value === 'api')
                                    @svg('heroicon-o-code-bracket', 'w-4 h-4 text-[var(--ui-primary)]')
                                @elseif($docPage->type->value === 'data_model')
                                    @svg('heroicon-o-circle-stack', 'w-4 h-4 text-[var(--ui-primary)]')
                                @elseif($docPage->type->value === 'testing')
                                    @svg('heroicon-o-beaker', 'w-4 h-4 text-[var(--ui-primary)]')
                                @elseif($docPage->type->value === 'deployment')
                                    @svg('heroicon-o-rocket-launch', 'w-4 h-4 text-[var(--ui-primary)]')
                                @elseif($docPage->type->value === 'changelog')
                                    @svg('heroicon-o-clipboard-document-list', 'w-4 h-4 text-[var(--ui-primary)]')
                                @elseif($docPage->type->value === 'contributing')
                                    @svg('heroicon-o-user-group', 'w-4 h-4 text-[var(--ui-primary)]')
                                @elseif($docPage->type->value === 'troubleshooting')
                                    @svg('heroicon-o-wrench-screwdriver', 'w-4 h-4 text-[var(--ui-primary)]')
                                @else
                                    @svg('heroicon-o-document-text', 'w-4 h-4 text-[var(--ui-muted)]')
                                @endif
                            </div>
                            <div class="min-w-0 flex-grow-1">
                                <div class="d-flex items-center gap-2">
                                    <span class="text-sm font-medium text-[var(--ui-secondary)]">{{ $docPage->title }}</span>
                                    <x-ui-badge :variant="$docPage->status === 'published' ? 'success' : 'secondary'" size="xs">
                                        {{ $docPage->status === 'published' ? 'Published' : 'Draft' }}
                                    </x-ui-badge>
                                </div>
                                @if($docPage->excerpt)
                                    <div class="text-xs text-[var(--ui-muted)] truncate mt-0.5">{{ $docPage->excerpt }}</div>
                                @endif
                            </div>
                            <div class="flex-shrink-0 text-right">
                                @if($docPage->updated_at)
                                    <div class="text-xs text-[var(--ui-muted)]">{{ $docPage->updated_at->diffForHumans() }}</div>
                                @endif
                                @if($docPage->revisions_count > 0)
                                    <div class="text-xs text-[var(--ui-muted)]">v{{ $docPage->revisions_count }}</div>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </x-ui-page-container>

    {{-- Create Board Modal --}}
    @if($showCreateBoardModal)
        <x-ui-modal wire:model="showCreateBoardModal" size="lg">
            <x-slot name="header">
                Neues Feature Board
            </x-slot>

            <div class="space-y-4">
                <x-ui-input-text
                    name="newBoardName"
                    wire:model.live="newBoardName"
                    label="Name"
                    required
                    placeholder="z.B. Sprint 3, Auth Refactoring..."
                />
                <x-ui-input-textarea
                    name="newBoardDescription"
                    wire:model.live="newBoardDescription"
                    label="Beschreibung"
                    rows="2"
                    placeholder="Optionale Beschreibung..."
                />
            </div>

            <x-slot name="footer">
                <div class="d-flex justify-end gap-2">
                    <x-ui-button variant="secondary-outline" wire:click="$set('showCreateBoardModal', false)">
                        Abbrechen
                    </x-ui-button>
                    <x-ui-button variant="primary" wire:click="createBoard">
                        @svg('heroicon-o-plus', 'w-4 h-4 mr-2')
                        Erstellen
                    </x-ui-button>
                </div>
            </x-slot>
        </x-ui-modal>
    @endif

    {{-- Error Tracking Settings Modal --}}
    @if($showErrorSettings && $errorSettings)
        <x-ui-modal wire:model="showErrorSettings" title="Error Tracking Settings">
            <div class="space-y-6">
                {{-- Master Toggle --}}
                <label class="flex items-center gap-3 cursor-pointer">
                    <input type="checkbox" wire:model.live="errorSettings.enabled"
                           class="w-4 h-4 rounded border-[var(--ui-border)] text-[var(--ui-primary)] focus:ring-[var(--ui-primary)] focus:ring-offset-0">
                    <span class="text-sm font-medium text-[var(--ui-secondary)]">Errors fuer dieses Package empfangen</span>
                </label>

                {{-- HTTP Codes --}}
                <div>
                    <h4 class="text-sm font-semibold text-[var(--ui-secondary)] mb-3">HTTP Status Codes</h4>
                    <div class="d-flex items-center gap-2 flex-wrap">
                        @foreach($availableHttpCodes as $code)
                            <button type="button"
                                    wire:click="toggleHttpCode({{ $code }})"
                                    class="px-3 py-1.5 rounded-full text-xs font-medium transition-colors {{ $this->isHttpCodeEnabled($code) ? 'bg-[var(--ui-primary)] text-white' : 'bg-[var(--ui-muted-5)] text-[var(--ui-muted)] border border-[var(--ui-border)]/40' }}">
                                {{ $code }}
                            </button>
                        @endforeach
                    </div>
                </div>

                {{-- Dedupe Window --}}
                <x-ui-input-text wire:model="errorSettings.dedupe_window_hours" label="Deduplizierung (Stunden)" type="number" min="1" max="720" />

                {{-- Options --}}
                <div class="space-y-3">
                    <label class="flex items-center gap-3 cursor-pointer">
                        <input type="checkbox" wire:model.live="errorSettings.capture_console_errors"
                               class="w-4 h-4 rounded border-[var(--ui-border)] text-[var(--ui-primary)] focus:ring-[var(--ui-primary)] focus:ring-offset-0">
                        <span class="text-sm text-[var(--ui-secondary)]">Console/Scheduler Errors erfassen</span>
                    </label>

                    <label class="flex items-center gap-3 cursor-pointer">
                        <input type="checkbox" wire:model.live="errorSettings.auto_create_issue"
                               class="w-4 h-4 rounded border-[var(--ui-border)] text-[var(--ui-primary)] focus:ring-[var(--ui-primary)] focus:ring-offset-0">
                        <span class="text-sm text-[var(--ui-secondary)]">Issues automatisch erstellen (Bug-Board)</span>
                    </label>

                    <label class="flex items-center gap-3 cursor-pointer">
                        <input type="checkbox" wire:model.live="errorSettings.include_stack_trace"
                               class="w-4 h-4 rounded border-[var(--ui-border)] text-[var(--ui-primary)] focus:ring-[var(--ui-primary)] focus:ring-offset-0">
                        <span class="text-sm text-[var(--ui-secondary)]">Stack Trace erfassen</span>
                    </label>

                    @if($errorSettings->include_stack_trace)
                        <x-ui-input-text wire:model="errorSettings.stack_trace_limit" label="Stack Trace Limit (Frames)" type="number" min="1" max="200" />
                    @endif
                </div>
            </div>
            <x-slot name="footer">
                <div class="d-flex items-center justify-end gap-2">
                    <x-ui-button variant="secondary-outline" wire:click="$set('showErrorSettings', false)">Abbrechen</x-ui-button>
                    <x-ui-button variant="primary" wire:click="saveErrorSettings">Speichern</x-ui-button>
                </div>
            </x-slot>
        </x-ui-modal>
    @endif
</x-ui-page>
