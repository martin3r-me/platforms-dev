<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="{{ $package->name }}" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Dev', 'href' => route('dev.dashboard'), 'icon' => 'code-bracket'],
            ['label' => $package->name],
        ]">
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
            {{-- Package Edit Modal --}}
            <x-ui-modal wire:model="editingPackage" size="lg">
                <x-slot name="header">Package bearbeiten</x-slot>
                <div class="space-y-4">
                    <x-ui-form-grid :cols="3" :gap="4">
                        <div class="col-span-2">
                            <x-ui-input-text wire:model="editPackageName" label="Name" required />
                        </div>
                        <x-ui-input-text wire:model="editPackageIcon" label="Icon" placeholder="heroicon-o-cube" />
                    </x-ui-form-grid>
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
                    <x-ui-input-textarea wire:model="editPackageDescription" label="Beschreibung" rows="3" />
                </div>
                <x-slot name="footer">
                    <div class="d-flex justify-end gap-2">
                        <x-ui-button variant="secondary-outline" wire:click="cancelEditPackage">Abbrechen</x-ui-button>
                        <x-ui-button variant="primary" wire:click="savePackage">Speichern</x-ui-button>
                    </div>
                </x-slot>
            </x-ui-modal>
        @endif

        {{-- Boards as Card Grid --}}
        <div class="mb-8">
            <div class="d-flex items-center justify-between mb-4">
                <div class="d-flex items-center gap-2.5">
                    @svg('heroicon-o-view-columns', 'w-5 h-5 text-[var(--ui-primary)]')
                    <h3 class="text-sm font-semibold text-[var(--ui-secondary)] uppercase tracking-wider">Boards</h3>
                </div>
                {{-- Stats Pills --}}
                <div class="d-flex items-center gap-2">
                    @if($totalHighPriority > 0)
                        <span class="d-flex items-center gap-1 text-xs px-2 py-1 rounded-full bg-[var(--ui-danger)]/10 text-[var(--ui-danger)] font-medium">
                            @svg('heroicon-s-fire', 'w-3 h-3') {{ $totalHighPriority }}
                        </span>
                    @endif
                    @if($totalOverdue > 0)
                        <span class="d-flex items-center gap-1 text-xs px-2 py-1 rounded-full bg-[var(--ui-warning)]/10 text-[var(--ui-warning)] font-medium">
                            @svg('heroicon-o-clock', 'w-3 h-3') {{ $totalOverdue }}
                        </span>
                    @endif
                    <span class="d-flex items-center gap-1 text-xs px-2 py-1 rounded-full bg-[var(--ui-muted-5)] text-[var(--ui-muted)] font-medium">
                        {{ $totalOpen }} offen &middot; {{ $totalDone }} erledigt
                    </span>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                @foreach($boards as $board)
                    @php
                        $boardTotal = $board->open_issues_count + ($board->issues()->where('is_done', true)->count());
                        $boardDone = $boardTotal - $board->open_issues_count;
                        $boardPct = $boardTotal > 0 ? round($boardDone / $boardTotal * 100) : 0;
                        $isBug = $board->type->value === 'bug';
                        $color = $isBug ? 'danger' : 'primary';
                    @endphp
                    <a href="{{ route('dev.packages.boards.show', [$package, $board]) }}"
                       wire:navigate
                       class="group block p-4 rounded-lg border border-[var(--ui-border)]/60 bg-[var(--ui-surface)] hover:border-[var(--ui-{{ $color }})]/40 hover:bg-[var(--ui-{{ $color }})]/[0.03] transition-colors">
                        <div class="d-flex items-center gap-3 mb-3">
                            <div class="w-9 h-9 rounded-lg d-flex items-center justify-center flex-shrink-0 bg-[var(--ui-{{ $color }})]/10">
                                @if($isBug)
                                    @svg('heroicon-o-bug-ant', 'w-4.5 h-4.5 text-[var(--ui-danger)]')
                                @else
                                    @svg('heroicon-o-light-bulb', 'w-4.5 h-4.5 text-[var(--ui-primary)]')
                                @endif
                            </div>
                            <div class="min-w-0 flex-grow-1">
                                <div class="text-sm font-medium text-[var(--ui-secondary)] truncate group-hover:text-[var(--ui-{{ $color }})] transition-colors">{{ $board->name }}</div>
                                <div class="text-xs text-[var(--ui-muted)]">{{ $board->open_issues_count }} offen</div>
                            </div>
                        </div>
                        @if($boardTotal > 0)
                            <div class="d-flex items-center gap-2.5">
                                <div class="flex-grow-1 h-1.5 rounded-full bg-[var(--ui-border)]/40 overflow-hidden">
                                    <div class="h-full rounded-full bg-[var(--ui-{{ $boardPct === 100 ? 'success' : $color }})] transition-all" style="width: {{ $boardPct }}%"></div>
                                </div>
                                <span class="text-xs font-semibold text-[var(--ui-{{ $boardPct === 100 ? 'success' : 'muted' }})] flex-shrink-0 w-8 text-right">{{ $boardPct }}%</span>
                            </div>
                        @endif
                    </a>
                @endforeach

                {{-- New Board Card --}}
                <button wire:click="$set('showCreateBoardModal', true)"
                        class="p-4 rounded-lg border border-dashed border-[var(--ui-border)]/60 hover:border-[var(--ui-primary)]/40 hover:bg-[var(--ui-primary)]/[0.03] transition-colors d-flex items-center justify-center gap-2 text-sm text-[var(--ui-muted)] hover:text-[var(--ui-primary)]">
                    @svg('heroicon-o-plus', 'w-4 h-4')
                    Neues Board
                </button>
            </div>

            {{-- Archived Boards --}}
            @if($archivedBoards->isNotEmpty())
                <div x-data="{ showArchived: false }" class="mt-2">
                    <button @click="showArchived = !showArchived" class="text-xs text-[var(--ui-muted)] hover:text-[var(--ui-secondary)] transition-colors d-flex items-center gap-1">
                        @svg('heroicon-o-archive-box', 'w-3 h-3')
                        {{ $archivedBoards->count() }} archiviert
                        <template x-if="!showArchived">@svg('heroicon-o-chevron-right', 'w-3 h-3')</template>
                        <template x-if="showArchived">@svg('heroicon-o-chevron-down', 'w-3 h-3')</template>
                    </button>
                    <div x-show="showArchived" x-collapse class="mt-2 d-flex items-center gap-2 flex-wrap">
                        @foreach($archivedBoards as $archivedBoard)
                            <div class="d-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-[var(--ui-muted-5)] text-xs text-[var(--ui-muted)]">
                                @svg('heroicon-o-archive-box', 'w-3 h-3')
                                {{ $archivedBoard->name }}
                                <button wire:click="reactivateBoard({{ $archivedBoard->id }})" class="p-0.5 rounded-full hover:bg-[var(--ui-primary)]/10 hover:text-[var(--ui-primary)] transition-colors" title="Reaktivieren">
                                    @svg('heroicon-o-arrow-path', 'w-3 h-3')
                                </button>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
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

            {{-- Zuletzt erledigt + Diskussionen --}}
            <div class="space-y-6">
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
                            <div class="p-6 text-center">
                                <p class="text-xs text-[var(--ui-muted)]">Noch nichts erledigt.</p>
                            </div>
                        @endforelse
                    </div>
                </div>

                {{-- Diskussionen --}}
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
            </div>
        </div>
        {{-- Documentation --}}
        @if($docPages->isNotEmpty())
            <div class="mt-6">
                {{-- Section Header with Progress --}}
                <div class="d-flex items-center justify-between mb-4">
                    <div class="d-flex items-center gap-2.5">
                        @svg('heroicon-o-book-open', 'w-5 h-5 text-[var(--ui-primary)]')
                        <h3 class="text-sm font-semibold text-[var(--ui-secondary)] uppercase tracking-wider">Dokumentation</h3>
                        <span class="text-xs px-1.5 py-0.5 rounded-full bg-[var(--ui-muted-5)] text-[var(--ui-muted)] font-medium">{{ $docPublishedCount }}/{{ $docPages->count() }}</span>
                    </div>
                    @php $docProgress = $docPages->count() > 0 ? round($docPublishedCount / $docPages->count() * 100) : 0; @endphp
                    <div class="d-flex items-center gap-3">
                        <div class="w-24 h-1.5 rounded-full bg-[var(--ui-border)]/40 overflow-hidden">
                            <div class="h-full rounded-full bg-gradient-to-r from-[var(--ui-primary)] to-[var(--ui-success)] transition-all" style="width: {{ $docProgress }}%"></div>
                        </div>
                        <span class="text-xs font-semibold {{ $docProgress === 100 ? 'text-[var(--ui-success)]' : 'text-[var(--ui-muted)]' }}">{{ $docProgress }}%</span>
                    </div>
                </div>

                {{-- Card Grid --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                    @foreach($docPages as $docPage)
                        @php
                            $iconMap = [
                                'overview' => 'heroicon-o-home',
                                'architecture' => 'heroicon-o-cube-transparent',
                                'setup' => 'heroicon-o-cog-6-tooth',
                                'api' => 'heroicon-o-code-bracket',
                                'data_model' => 'heroicon-o-circle-stack',
                                'testing' => 'heroicon-o-beaker',
                                'deployment' => 'heroicon-o-rocket-launch',
                                'changelog' => 'heroicon-o-clipboard-document-list',
                                'contributing' => 'heroicon-o-user-group',
                                'troubleshooting' => 'heroicon-o-wrench-screwdriver',
                                'custom' => 'heroicon-o-document-text',
                            ];
                            $icon = $iconMap[$docPage->type->value] ?? 'heroicon-o-document-text';
                            $isPublished = $docPage->status === 'published';
                            $hasContent = $docPage->content && trim($docPage->content) !== '' && trim($docPage->content) !== $docPage->type->defaultContent();
                        @endphp
                        <div class="group p-3 rounded-lg border transition-colors
                            {{ $isPublished
                                ? 'border-[var(--ui-success)]/30 bg-[var(--ui-success)]/[0.03] hover:bg-[var(--ui-success)]/[0.06]'
                                : 'border-[var(--ui-border)]/60 bg-[var(--ui-surface)] hover:bg-[var(--ui-muted-5)]' }}">
                            <div class="d-flex items-start gap-3">
                                <div class="w-8 h-8 rounded-lg d-flex items-center justify-center flex-shrink-0
                                    {{ $isPublished
                                        ? 'bg-[var(--ui-success)]/10'
                                        : 'bg-[var(--ui-muted-5)]' }}">
                                    @svg($icon, 'w-4 h-4 ' . ($isPublished ? 'text-[var(--ui-success)]' : 'text-[var(--ui-muted)]'))
                                </div>
                                <div class="min-w-0 flex-grow-1">
                                    <div class="d-flex items-center gap-1.5">
                                        <span class="text-sm font-medium text-[var(--ui-secondary)] truncate">{{ $docPage->title }}</span>
                                        @if($isPublished)
                                            @svg('heroicon-s-check-circle', 'w-3.5 h-3.5 text-[var(--ui-success)] flex-shrink-0')
                                        @endif
                                    </div>
                                    <div class="d-flex items-center gap-1.5 mt-1 text-xs text-[var(--ui-muted)]">
                                        @if($docPage->revisions_count > 0)
                                            <span class="font-mono px-1 py-px rounded bg-[var(--ui-muted-5)] text-[10px]">v{{ $docPage->revisions_count }}</span>
                                        @endif
                                        @if($docPage->lastEditedBy)
                                            <span>{{ $docPage->lastEditedBy->name }}</span>
                                            <span>&middot;</span>
                                        @endif
                                        @if($docPage->updated_at)
                                            <span>{{ $docPage->updated_at->diffForHumans() }}</span>
                                        @endif
                                    </div>
                                </div>
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
