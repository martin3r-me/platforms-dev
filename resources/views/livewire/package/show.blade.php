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
                <button wire:click="openErrorSettings"
                        class="inline-flex items-center gap-2 px-3 py-1.5 text-sm font-medium text-gray-700 bg-gray-50 hover:bg-gray-100 rounded-md border border-gray-300 transition-colors">
                    @svg('heroicon-o-bug-ant', 'w-4 h-4')
                    <span>Error Tracking</span>
                </button>
                <button wire:click="startEditingPackage"
                        class="inline-flex items-center gap-2 px-3 py-1.5 text-sm font-medium text-gray-700 bg-gray-50 hover:bg-gray-100 rounded-md border border-gray-300 transition-colors">
                    @svg('heroicon-o-pencil', 'w-4 h-4')
                    <span>Settings</span>
                </button>
            @endif
        </x-ui-page-actionbar>
    </x-slot>

    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Uebersicht" width="w-80" :defaultOpen="true" storeKey="sidebarOpen" side="left">
            <div class="p-6 space-y-6">
                {{-- Package Header --}}
                <div class="d-flex items-center gap-3">
                    <div class="w-10 h-10 rounded-md bg-gray-100 d-flex items-center justify-center flex-shrink-0">
                        @svg($package->icon ?? 'heroicon-o-cube', 'w-5 h-5 text-gray-600')
                    </div>
                    <div class="min-w-0">
                        <h3 class="text-base font-semibold text-gray-900">{{ $package->name }}</h3>
                        <span class="inline-flex items-center gap-1.5 text-xs font-medium {{ $package->status === 'active' ? 'text-green-700' : 'text-gray-500' }}">
                            <span class="w-1.5 h-1.5 rounded-full {{ $package->status === 'active' ? 'bg-green-500' : 'bg-gray-400' }}"></span>
                            {{ $package->status === 'active' ? 'Active' : 'Archived' }}
                        </span>
                    </div>
                </div>

                @if($package->description)
                    <p class="text-sm text-gray-500">{{ $package->description }}</p>
                @endif

                {{-- Health Progress Bar --}}
                @php
                    $totalIssues = $totalOpen + $totalDone;
                    $progressPct = $totalIssues > 0 ? round($totalDone / $totalIssues * 100) : 0;
                @endphp
                @if($totalIssues > 0)
                    <div class="px-3 py-2 rounded-md bg-gray-50 border border-gray-200">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-xs font-medium text-gray-700">Progress</span>
                            <span class="text-xs font-semibold {{ $progressPct === 100 ? 'text-green-600' : 'text-gray-900' }}">{{ $totalDone }}/{{ $totalIssues }}</span>
                        </div>
                        <div class="w-full h-2 rounded-full bg-gray-200 overflow-hidden">
                            <div class="h-full rounded-full bg-green-500 transition-all" style="width: {{ $progressPct }}%"></div>
                        </div>
                    </div>
                @endif

                {{-- Board Shortcuts --}}
                <div>
                    <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-3">Boards</h3>
                    <div class="space-y-1">
                        @foreach($boards as $board)
                            <a href="{{ route('dev.packages.boards.show', [$package, $board]) }}"
                               wire:navigate
                               class="d-flex items-center gap-2.5 py-2 px-3 rounded-md hover:bg-gray-50 transition-colors border border-transparent hover:border-gray-200">
                                @if($board->type === 'bug')
                                    @svg('heroicon-o-bug-ant', 'w-4 h-4 text-red-500 flex-shrink-0')
                                @elseif($board->type === 'feature')
                                    @svg('heroicon-o-light-bulb', 'w-4 h-4 text-blue-500 flex-shrink-0')
                                @else
                                    @svg('heroicon-o-view-columns', 'w-4 h-4 text-gray-400 flex-shrink-0')
                                @endif
                                <span class="text-sm text-gray-700 flex-grow-1">{{ $board->name }}</span>
                                <span class="px-2 py-0.5 text-xs font-medium rounded-full bg-gray-100 text-gray-600">{{ $board->open_issues_count }}</span>
                            </a>
                        @endforeach
                    </div>
                </div>

                {{-- Package Info --}}
                <div>
                    <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-3">About</h3>
                    <div class="space-y-2 text-sm">
                        @if($package->userInCharge)
                            <div class="flex justify-between">
                                <span class="text-gray-500">Owner</span>
                                <span class="font-medium text-gray-900">{{ $package->userInCharge->name }}</span>
                            </div>
                        @endif
                        @if($package->github_repo_full_name)
                            <div class="flex justify-between">
                                <span class="text-gray-500">Repository</span>
                                <code class="text-xs font-mono text-gray-700 truncate max-w-[10rem]">{{ $package->github_repo_full_name }}</code>
                            </div>
                        @endif
                        <div class="flex justify-between">
                            <span class="text-gray-500">Created</span>
                            <span class="font-medium text-gray-900">{{ $package->created_at->format('d.m.Y') }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">Errors</span>
                            <span class="font-medium {{ $errorSettingsEnabled ? 'text-green-600' : 'text-gray-400' }}">
                                {{ $errorSettingsEnabled ? 'Active' : 'Inactive' }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </x-ui-page-sidebar>
    </x-slot>

    <x-slot name="activity">
        <x-ui-page-sidebar title="Aktivitaeten" width="w-80" :defaultOpen="false" storeKey="activityOpen" side="right">
            <div class="p-6">
                <h3 class="text-xs font-semibold uppercase tracking-wider text-gray-500 mb-4">Recent activity</h3>
                <div class="space-y-3">
                    @forelse(($activities ?? []) as $activity)
                        <div class="px-3 py-2 rounded-md border border-gray-200 bg-gray-50 hover:bg-gray-100 transition-colors">
                            <div class="text-sm font-medium text-gray-900 leading-snug mb-1">
                                {{ $activity['title'] ?? 'Aktivitaet' }}
                            </div>
                            <div class="flex items-center gap-2 text-xs text-gray-500">
                                @svg('heroicon-o-clock', 'w-3 h-3')
                                <span>{{ $activity['time'] ?? '' }}</span>
                            </div>
                        </div>
                    @empty
                        <div class="py-8 text-center">
                            @svg('heroicon-o-clock', 'w-8 h-8 text-gray-300 mx-auto mb-3')
                            <p class="text-sm text-gray-500">No activity yet</p>
                        </div>
                    @endforelse
                </div>
            </div>
        </x-ui-page-sidebar>
    </x-slot>

    <x-ui-page-container>
        @if($editingPackage)
            {{-- Package Edit Modal --}}
            <x-ui-modal wire:model="editingPackage" size="md" :backdropClosable="true" :escClosable="true">
                <x-slot name="header">
                    <div class="flex items-center gap-3">
                        <div class="flex-shrink-0">
                            <div class="w-10 h-10 bg-gray-100 rounded-lg flex items-center justify-center">
                                @svg('heroicon-o-pencil-square', 'w-5 h-5 text-gray-600')
                            </div>
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900">Package bearbeiten</h3>
                            <p class="text-sm text-gray-500">Name, Icon und Verantwortlichen anpassen</p>
                        </div>
                    </div>
                </x-slot>
                <div class="space-y-6">
                    <x-ui-form-grid :cols="3" :gap="6">
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
                    <div class="flex justify-end gap-3">
                        <button wire:click="cancelEditPackage"
                                class="inline-flex items-center gap-2 px-3 py-1.5 text-sm font-medium text-gray-700 bg-gray-50 hover:bg-gray-100 rounded-md border border-gray-300 transition-colors">
                            Abbrechen
                        </button>
                        <button wire:click="savePackage"
                                class="inline-flex items-center gap-2 px-3 py-1.5 text-sm font-medium text-white bg-green-600 hover:bg-green-700 rounded-md border border-green-700 transition-colors">
                            @svg('heroicon-o-check', 'w-4 h-4')
                            Speichern
                        </button>
                    </div>
                </x-slot>
            </x-ui-modal>
        @endif

        {{-- Boards as Card Grid --}}
        <div class="mb-8">
            <div class="d-flex items-center justify-between mb-4">
                <div class="d-flex items-center gap-2">
                    @svg('heroicon-o-view-columns', 'w-4 h-4 text-gray-500')
                    <h3 class="text-sm font-semibold text-gray-900">Boards</h3>
                </div>
                {{-- Stats Pills --}}
                <div class="d-flex items-center gap-2">
                    @if($totalHighPriority > 0)
                        <span class="d-flex items-center gap-1 text-xs px-2 py-1 rounded-full bg-red-50 text-red-700 font-medium border border-red-200">
                            <span class="w-1.5 h-1.5 rounded-full bg-red-500"></span>
                            {{ $totalHighPriority }} high
                        </span>
                    @endif
                    @if($totalOverdue > 0)
                        <span class="d-flex items-center gap-1 text-xs px-2 py-1 rounded-full bg-yellow-50 text-yellow-700 font-medium border border-yellow-200">
                            @svg('heroicon-o-clock', 'w-3 h-3') {{ $totalOverdue }} overdue
                        </span>
                    @endif
                    <span class="text-xs text-gray-500 font-medium">
                        {{ $totalOpen }} open &middot; {{ $totalDone }} closed
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
                    @endphp
                    <a href="{{ route('dev.packages.boards.show', [$package, $board]) }}"
                       wire:navigate
                       class="group block p-4 rounded-md border border-gray-200 bg-white hover:border-gray-300 hover:bg-gray-50 transition-colors">
                        <div class="d-flex items-center gap-3 mb-3">
                            <div class="flex-shrink-0">
                                @if($isBug)
                                    @svg('heroicon-o-bug-ant', 'w-5 h-5 text-red-500')
                                @else
                                    @svg('heroicon-o-light-bulb', 'w-5 h-5 text-blue-500')
                                @endif
                            </div>
                            <div class="min-w-0 flex-grow-1">
                                <div class="text-sm font-medium text-gray-900 truncate group-hover:text-blue-600 transition-colors">{{ $board->name }}</div>
                                <div class="text-xs text-gray-500">{{ $board->open_issues_count }} open</div>
                            </div>
                        </div>
                        @if($boardTotal > 0)
                            <div class="d-flex items-center gap-2.5">
                                <div class="flex-grow-1 h-2 rounded-full bg-gray-200 overflow-hidden">
                                    <div class="h-full rounded-full {{ $boardPct === 100 ? 'bg-green-500' : ($isBug ? 'bg-red-400' : 'bg-blue-400') }} transition-all" style="width: {{ $boardPct }}%"></div>
                                </div>
                                <span class="text-xs font-semibold {{ $boardPct === 100 ? 'text-green-600' : 'text-gray-500' }} flex-shrink-0 w-8 text-right">{{ $boardPct }}%</span>
                            </div>
                        @endif
                    </a>
                @endforeach

                {{-- New Board Card --}}
                <button wire:click="$set('showCreateBoardModal', true)"
                        class="p-4 rounded-md border border-dashed border-gray-300 hover:border-green-400 hover:bg-green-50/50 transition-colors d-flex items-center justify-center gap-2 text-sm text-gray-500 hover:text-green-600">
                    @svg('heroicon-o-plus', 'w-4 h-4')
                    New board
                </button>
            </div>

            {{-- Archived Boards --}}
            @if($archivedBoards->isNotEmpty())
                <div x-data="{ showArchived: false }" class="mt-2">
                    <button @click="showArchived = !showArchived" class="text-xs text-gray-500 hover:text-gray-700 transition-colors d-flex items-center gap-1">
                        @svg('heroicon-o-archive-box', 'w-3 h-3')
                        {{ $archivedBoards->count() }} archived
                        <template x-if="!showArchived">@svg('heroicon-o-chevron-right', 'w-3 h-3')</template>
                        <template x-if="showArchived">@svg('heroicon-o-chevron-down', 'w-3 h-3')</template>
                    </button>
                    <div x-show="showArchived" x-collapse class="mt-2 d-flex items-center gap-2 flex-wrap">
                        @foreach($archivedBoards as $archivedBoard)
                            <div class="d-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-gray-100 text-xs text-gray-500 border border-gray-200">
                                @svg('heroicon-o-archive-box', 'w-3 h-3')
                                {{ $archivedBoard->name }}
                                <button wire:click="reactivateBoard({{ $archivedBoard->id }})" class="p-0.5 rounded-full hover:bg-green-50 hover:text-green-600 transition-colors" title="Reaktivieren">
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
            <div class="bg-white rounded-md border border-red-200 overflow-hidden mb-6">
                <div class="flex items-center justify-between px-4 py-3 border-b border-red-200 bg-red-50">
                    <div class="d-flex items-center gap-2">
                        <span class="relative flex h-2.5 w-2.5">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-500 opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-red-500"></span>
                        </span>
                        <h3 class="text-sm font-semibold text-red-800">Security alerts</h3>
                        <span class="px-2 py-0.5 text-xs font-medium rounded-full bg-red-100 text-red-700">{{ $errorOccurrences->count() }}</span>
                    </div>
                </div>
                <div>
                    @foreach($errorOccurrences as $occurrence)
                        <div class="px-4 py-3 d-flex items-start gap-3 group hover:bg-gray-50 transition-colors border-b border-gray-100 last:border-b-0">
                            <div class="flex-shrink-0 mt-0.5">
                                @if($occurrence->http_code >= 500)
                                    @svg('heroicon-s-exclamation-triangle', 'w-4 h-4 text-red-500')
                                @else
                                    @svg('heroicon-o-exclamation-circle', 'w-4 h-4 text-yellow-500')
                                @endif
                            </div>
                            <div class="min-w-0 flex-grow-1">
                                <div class="text-sm font-medium text-gray-900 truncate">
                                    @if($occurrence->http_code)
                                        <code class="text-xs px-1.5 py-0.5 font-mono bg-red-50 text-red-700 rounded mr-1">{{ $occurrence->http_code }}</code>
                                    @endif
                                    {{ $occurrence->getShortExceptionClass() }}
                                </div>
                                <div class="text-xs text-gray-500 mt-0.5 truncate">{{ Str::limit($occurrence->message, 100) }}</div>
                                <div class="text-xs text-gray-400 mt-0.5 font-mono">{{ Str::afterLast($occurrence->file ?? '', '/') }}:{{ $occurrence->line }}</div>
                            </div>
                            <div class="flex-shrink-0 text-right">
                                <div class="text-xs text-gray-400">{{ $occurrence->last_seen_at?->diffForHumans() }}</div>
                                @if($occurrence->occurrence_count > 1)
                                    <div class="text-xs font-medium text-red-600">{{ $occurrence->occurrence_count }}x</div>
                                @endif
                            </div>
                            <div class="flex-shrink-0 d-flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                <button wire:click="resolveOccurrence({{ $occurrence->id }})" class="p-1 rounded hover:bg-green-50 text-gray-400 hover:text-green-600 transition-colors" title="Resolve">
                                    @svg('heroicon-o-check-circle', 'w-4 h-4')
                                </button>
                                <button wire:click="ignoreOccurrence({{ $occurrence->id }})" class="p-1 rounded hover:bg-gray-100 text-gray-400 hover:text-gray-700 transition-colors" title="Ignorieren">
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
            <div class="lg:col-span-2 bg-white rounded-md border border-gray-200 overflow-hidden">
                <div class="flex items-center gap-2 px-4 py-3 border-b border-gray-200 bg-gray-50">
                    @svg('heroicon-o-code-bracket', 'w-4 h-4 text-gray-500')
                    <h3 class="text-sm font-semibold text-gray-900">Commits</h3>
                    @if($recentCommits->isNotEmpty())
                        <span class="px-2 py-0.5 text-xs font-medium rounded-full bg-gray-200 text-gray-600">{{ $recentCommits->count() }}</span>
                    @endif
                </div>
                <div>
                    @forelse($recentCommits as $commit)
                        <div class="d-flex items-start gap-3 px-4 py-2.5 hover:bg-gray-50 transition-colors group border-b border-gray-100 last:border-b-0">
                            <div class="flex-shrink-0 d-flex flex-col items-center mt-1" style="width: 12px;">
                                <div class="w-2.5 h-2.5 rounded-full border-2 border-green-500 bg-white"></div>
                                @if(!$loop->last)
                                    <div class="w-px flex-grow-1 bg-gray-200 mt-0.5" style="min-height: 20px;"></div>
                                @endif
                            </div>
                            <div class="min-w-0 flex-grow-1">
                                <div class="text-sm text-gray-900 truncate group-hover:text-blue-600 transition-colors">{{ Str::limit(Str::before($commit->message, "\n"), 80) }}</div>
                                <div class="text-xs text-gray-500 mt-0.5 d-flex items-center gap-1.5">
                                    <span class="font-medium text-gray-700">{{ $commit->author_login ?? $commit->author_name }}</span>
                                    <span>&middot;</span>
                                    <code class="px-1.5 py-0.5 text-[10px] font-mono bg-gray-100 text-gray-600 rounded">{{ Str::limit($commit->sha, 7, '') }}</code>
                                </div>
                            </div>
                            <div class="flex-shrink-0 text-xs text-gray-400 whitespace-nowrap">
                                {{ $commit->committed_at?->diffForHumans() }}
                            </div>
                        </div>
                    @empty
                        <div class="p-10 text-center">
                            @if($package->github_repo_full_name)
                                @svg('heroicon-o-code-bracket', 'w-8 h-8 text-gray-300 mx-auto mb-3')
                                <p class="text-sm font-medium text-gray-900 mb-1">No commits yet</p>
                                <p class="text-xs text-gray-500">Commits sync automatically every hour.</p>
                            @else
                                @svg('heroicon-o-link-slash', 'w-8 h-8 text-gray-300 mx-auto mb-3')
                                <p class="text-sm text-gray-500">No GitHub repository linked.</p>
                            @endif
                        </div>
                    @endforelse
                </div>
            </div>

            {{-- Open Pull Requests --}}
            <div class="bg-white rounded-md border border-gray-200 overflow-hidden">
                <div class="flex items-center gap-2 px-4 py-3 border-b border-gray-200 bg-gray-50">
                    @svg('heroicon-o-arrow-path', 'w-4 h-4 text-green-600')
                    <h3 class="text-sm font-semibold text-gray-900">Pull requests</h3>
                    @if($openPullRequests->isNotEmpty())
                        <span class="px-2 py-0.5 text-xs font-medium rounded-full bg-green-100 text-green-700">{{ $openPullRequests->count() }}</span>
                    @endif
                </div>
                <div>
                    @forelse($openPullRequests as $pr)
                        <div class="px-4 py-3 hover:bg-gray-50 transition-colors border-b border-gray-100 last:border-b-0">
                            <div class="d-flex items-start gap-2">
                                <div class="flex-shrink-0 mt-0.5">
                                    @if($pr->is_draft)
                                        <div class="w-4 h-4 rounded-full border-2 border-dashed border-gray-400 d-flex items-center justify-center">
                                            <div class="w-1.5 h-1.5 rounded-full bg-gray-400"></div>
                                        </div>
                                    @else
                                        @svg('heroicon-o-arrow-path', 'w-4 h-4 text-green-600')
                                    @endif
                                </div>
                                <div class="min-w-0">
                                    <div class="text-sm font-medium text-gray-900 truncate hover:text-blue-600">{{ $pr->title }}</div>
                                    <div class="text-xs text-gray-500 mt-0.5">
                                        #{{ $pr->number }} &middot; {{ $pr->author_login }}
                                        @if($pr->is_draft)
                                            &middot; <span class="italic text-gray-400">Draft</span>
                                        @endif
                                    </div>
                                    @if($pr->head_ref)
                                        <div class="d-flex items-center gap-1.5 mt-1.5">
                                            <code class="px-1.5 py-0.5 text-[10px] font-mono bg-blue-50 text-blue-700 rounded">{{ $pr->head_ref }}</code>
                                            @svg('heroicon-o-arrow-right', 'w-3 h-3 text-gray-400')
                                            <code class="px-1.5 py-0.5 text-[10px] font-mono bg-gray-100 text-gray-600 rounded">{{ $pr->base_ref }}</code>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="p-8 text-center">
                            @svg('heroicon-o-check-circle', 'w-6 h-6 text-green-400 mx-auto mb-2')
                            <p class="text-xs text-gray-500">No open PRs.</p>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- Issues + Discussions --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
            {{-- Letzte offene Issues --}}
            <div class="bg-white rounded-md border border-gray-200 overflow-hidden">
                <div class="flex items-center gap-2 px-4 py-3 border-b border-gray-200 bg-gray-50">
                    @svg('heroicon-o-exclamation-circle', 'w-4 h-4 text-green-600')
                    <h3 class="text-sm font-semibold text-gray-900">Open issues</h3>
                    @if($recentIssues->isNotEmpty())
                        <span class="px-2 py-0.5 text-xs font-medium rounded-full bg-gray-200 text-gray-600">{{ $recentIssues->count() }}</span>
                    @endif
                </div>
                <div>
                    @forelse($recentIssues as $issue)
                        <a href="{{ route('dev.packages.issues.show', [$package, $issue]) }}"
                           wire:navigate
                           class="d-flex items-center gap-3 px-4 py-3 hover:bg-gray-50 transition-colors border-b border-gray-100 last:border-b-0">
                            <div class="flex-shrink-0">
                                @if($issue->priority === 'high')
                                    <span class="w-2 h-2 rounded-full bg-red-500 inline-block"></span>
                                @else
                                    <span class="w-2 h-2 rounded-full bg-green-500 inline-block"></span>
                                @endif
                            </div>
                            <div class="min-w-0 flex-grow-1">
                                <div class="text-sm font-medium text-gray-900 truncate hover:text-blue-600">{{ $issue->title }}</div>
                                <div class="text-xs text-gray-500">
                                    {{ $issue->board->name }}
                                    @if($issue->userInCharge)
                                        &middot; {{ $issue->userInCharge->name }}
                                    @endif
                                </div>
                            </div>
                            <div class="flex-shrink-0 text-xs text-gray-400">
                                {{ $issue->created_at->diffForHumans() }}
                            </div>
                        </a>
                    @empty
                        <div class="p-8 text-center">
                            @svg('heroicon-o-check-circle', 'w-6 h-6 text-green-400 mx-auto mb-2')
                            <p class="text-sm text-gray-500">No open issues.</p>
                        </div>
                    @endforelse
                </div>
            </div>

            {{-- Zuletzt erledigt + Diskussionen --}}
            <div class="space-y-6">
                {{-- Zuletzt erledigt --}}
                <div class="bg-white rounded-md border border-gray-200 overflow-hidden">
                    <div class="flex items-center gap-2 px-4 py-3 border-b border-gray-200 bg-gray-50">
                        @svg('heroicon-o-check-circle', 'w-4 h-4 text-purple-500')
                        <h3 class="text-sm font-semibold text-gray-900">Recently closed</h3>
                        @if($recentlyDone->isNotEmpty())
                            <span class="px-2 py-0.5 text-xs font-medium rounded-full bg-purple-100 text-purple-700">{{ $recentlyDone->count() }}</span>
                        @endif
                    </div>
                    <div>
                        @forelse($recentlyDone as $issue)
                            <a href="{{ route('dev.packages.issues.show', [$package, $issue]) }}"
                               wire:navigate
                               class="d-flex items-center gap-3 px-4 py-3 hover:bg-gray-50 transition-colors border-b border-gray-100 last:border-b-0">
                                <div class="flex-shrink-0">
                                    @svg('heroicon-o-check-circle', 'w-4 h-4 text-purple-500')
                                </div>
                                <div class="min-w-0 flex-grow-1">
                                    <div class="text-sm text-gray-400 line-through truncate">{{ $issue->title }}</div>
                                    <div class="text-xs text-gray-500">
                                        {{ $issue->board->name }}
                                        @if($issue->done_at)
                                            &middot; {{ $issue->done_at->diffForHumans() }}
                                        @endif
                                    </div>
                                </div>
                            </a>
                        @empty
                            <div class="p-6 text-center">
                                <p class="text-xs text-gray-500">Nothing closed yet.</p>
                            </div>
                        @endforelse
                    </div>
                </div>

                {{-- Diskussionen --}}
                @if($recentDiscussions->isNotEmpty() || $discussionCount > 0)
                    <div class="bg-white rounded-md border border-gray-200 overflow-hidden">
                        <div class="flex items-center justify-between px-4 py-3 border-b border-gray-200 bg-gray-50">
                            <div class="d-flex items-center gap-2">
                                @svg('heroicon-o-chat-bubble-left-right', 'w-4 h-4 text-gray-500')
                                <h3 class="text-sm font-semibold text-gray-900">Discussions</h3>
                                @if($discussionCount > 0)
                                    <span class="px-2 py-0.5 text-xs font-medium rounded-full bg-gray-200 text-gray-600">{{ $discussionCount }}</span>
                                @endif
                            </div>
                            <a href="{{ route('dev.packages.discussions', $package) }}" wire:navigate class="text-xs text-blue-600 hover:underline font-medium">
                                View all
                            </a>
                        </div>
                        <div>
                            @foreach($recentDiscussions as $discussion)
                                <a href="{{ route('dev.packages.discussions', $package) }}"
                                   wire:navigate
                                   class="d-flex items-center gap-3 px-4 py-3 hover:bg-gray-50 transition-colors border-b border-gray-100 last:border-b-0">
                                    <div class="flex-shrink-0">
                                        @if($discussion->is_pinned)
                                            @svg('heroicon-s-bookmark', 'w-4 h-4 text-blue-500')
                                        @else
                                            @svg('heroicon-o-chat-bubble-left', 'w-4 h-4 text-gray-400')
                                        @endif
                                    </div>
                                    <div class="min-w-0 flex-grow-1">
                                        <div class="text-sm font-medium text-gray-900 truncate">{{ $discussion->title }}</div>
                                        <div class="text-xs text-gray-500">
                                            {{ $discussion->createdBy?->name }}
                                            &middot; {{ $discussion->replies_count }} {{ $discussion->replies_count === 1 ? 'reply' : 'replies' }}
                                        </div>
                                    </div>
                                    <div class="flex-shrink-0 text-xs text-gray-400">
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
                <div class="d-flex items-center justify-between mb-4">
                    <div class="d-flex items-center gap-2">
                        @svg('heroicon-o-book-open', 'w-4 h-4 text-gray-500')
                        <h3 class="text-sm font-semibold text-gray-900">Documentation</h3>
                        <span class="px-2 py-0.5 text-xs font-medium rounded-full bg-gray-200 text-gray-600">{{ $docPublishedCount }}/{{ $docPages->count() }}</span>
                    </div>
                    @php $docProgress = $docPages->count() > 0 ? round($docPublishedCount / $docPages->count() * 100) : 0; @endphp
                    <div class="d-flex items-center gap-3">
                        <div class="w-24 h-2 rounded-full bg-gray-200 overflow-hidden">
                            <div class="h-full rounded-full bg-green-500 transition-all" style="width: {{ $docProgress }}%"></div>
                        </div>
                        <span class="text-xs font-semibold {{ $docProgress === 100 ? 'text-green-600' : 'text-gray-500' }}">{{ $docProgress }}%</span>
                    </div>
                </div>

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
                        <div class="group p-3 rounded-md border transition-colors
                            {{ $isPublished
                                ? 'border-green-200 bg-green-50/50 hover:bg-green-50'
                                : 'border-gray-200 bg-white hover:bg-gray-50' }}">
                            <div class="d-flex items-start gap-3">
                                <div class="w-8 h-8 rounded-md d-flex items-center justify-center flex-shrink-0
                                    {{ $isPublished ? 'bg-green-100' : 'bg-gray-100' }}">
                                    @svg($icon, 'w-4 h-4 ' . ($isPublished ? 'text-green-600' : 'text-gray-400'))
                                </div>
                                <div class="min-w-0 flex-grow-1">
                                    <div class="d-flex items-center gap-1.5">
                                        <span class="text-sm font-medium text-gray-900 truncate">{{ $docPage->title }}</span>
                                        @if($isPublished)
                                            @svg('heroicon-s-check-circle', 'w-3.5 h-3.5 text-green-500 flex-shrink-0')
                                        @endif
                                    </div>
                                    <div class="d-flex items-center gap-1.5 mt-1 text-xs text-gray-500">
                                        @if($docPage->revisions_count > 0)
                                            <code class="px-1 py-px text-[10px] font-mono bg-gray-100 text-gray-600 rounded">v{{ $docPage->revisions_count }}</code>
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
        <x-ui-modal wire:model="showCreateBoardModal" size="md" :backdropClosable="true" :escClosable="true">
            <x-slot name="header">
                <div class="flex items-center gap-3">
                    <div class="flex-shrink-0">
                        <div class="w-10 h-10 bg-gray-100 rounded-lg flex items-center justify-center">
                            @svg('heroicon-o-view-columns', 'w-5 h-5 text-gray-600')
                        </div>
                    </div>
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900">Neues Board</h3>
                        <p class="text-sm text-gray-500">Feature Board erstellen</p>
                    </div>
                </div>
            </x-slot>

            <div class="space-y-6">
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
                <div class="flex justify-end gap-3">
                    <button wire:click="$set('showCreateBoardModal', false)"
                            class="inline-flex items-center gap-2 px-3 py-1.5 text-sm font-medium text-gray-700 bg-gray-50 hover:bg-gray-100 rounded-md border border-gray-300 transition-colors">
                        Abbrechen
                    </button>
                    <button wire:click="createBoard"
                            class="inline-flex items-center gap-2 px-3 py-1.5 text-sm font-medium text-white bg-green-600 hover:bg-green-700 rounded-md border border-green-700 transition-colors">
                        @svg('heroicon-o-plus', 'w-4 h-4')
                        Erstellen
                    </button>
                </div>
            </x-slot>
        </x-ui-modal>
    @endif

    {{-- Error Tracking Settings Modal --}}
    @if($showErrorSettings && $errorSettings)
        <x-ui-modal wire:model="showErrorSettings" size="md" :backdropClosable="true" :escClosable="true">
            <x-slot name="header">
                <div class="flex items-center gap-3">
                    <div class="flex-shrink-0">
                        <div class="w-10 h-10 bg-red-50 rounded-lg flex items-center justify-center">
                            @svg('heroicon-o-bug-ant', 'w-5 h-5 text-red-600')
                        </div>
                    </div>
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900">Error Tracking Settings</h3>
                        <p class="text-sm text-gray-500">Fehler-Erfassung fuer {{ $package->name }}</p>
                    </div>
                </div>
            </x-slot>

            <div class="space-y-6">
                {{-- Master Toggle --}}
                <label class="flex items-center gap-3 cursor-pointer">
                    <input type="checkbox" wire:model.live="errorSettings.enabled"
                           class="w-4 h-4 rounded border-gray-300 text-green-600 focus:ring-green-500 focus:ring-offset-0">
                    <span class="text-sm font-medium text-gray-900">Errors fuer dieses Package empfangen</span>
                </label>

                {{-- HTTP Codes --}}
                <div>
                    <h4 class="text-sm font-semibold text-gray-900 mb-3">HTTP Status Codes</h4>
                    <div class="flex items-center gap-2 flex-wrap">
                        @foreach($availableHttpCodes as $code)
                            <button type="button"
                                    wire:click="toggleHttpCode({{ $code }})"
                                    class="px-3 py-1.5 rounded-full text-xs font-medium transition-colors border {{ $this->isHttpCodeEnabled($code) ? 'bg-green-600 text-white border-green-700' : 'bg-gray-50 text-gray-600 border-gray-300 hover:bg-gray-100' }}">
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
                               class="w-4 h-4 rounded border-gray-300 text-green-600 focus:ring-green-500 focus:ring-offset-0">
                        <span class="text-sm text-gray-700">Console/Scheduler Errors erfassen</span>
                    </label>

                    <label class="flex items-center gap-3 cursor-pointer">
                        <input type="checkbox" wire:model.live="errorSettings.auto_create_issue"
                               class="w-4 h-4 rounded border-gray-300 text-green-600 focus:ring-green-500 focus:ring-offset-0">
                        <span class="text-sm text-gray-700">Issues automatisch erstellen (Bug-Board)</span>
                    </label>

                    <label class="flex items-center gap-3 cursor-pointer">
                        <input type="checkbox" wire:model.live="errorSettings.include_stack_trace"
                               class="w-4 h-4 rounded border-gray-300 text-green-600 focus:ring-green-500 focus:ring-offset-0">
                        <span class="text-sm text-gray-700">Stack Trace erfassen</span>
                    </label>

                    @if($errorSettings->include_stack_trace)
                        <x-ui-input-text wire:model="errorSettings.stack_trace_limit" label="Stack Trace Limit (Frames)" type="number" min="1" max="200" />
                    @endif
                </div>
            </div>
            <x-slot name="footer">
                <div class="flex justify-end gap-3">
                    <button wire:click="$set('showErrorSettings', false)"
                            class="inline-flex items-center gap-2 px-3 py-1.5 text-sm font-medium text-gray-700 bg-gray-50 hover:bg-gray-100 rounded-md border border-gray-300 transition-colors">
                        Abbrechen
                    </button>
                    <button wire:click="saveErrorSettings"
                            class="inline-flex items-center gap-2 px-3 py-1.5 text-sm font-medium text-white bg-green-600 hover:bg-green-700 rounded-md border border-green-700 transition-colors">
                        @svg('heroicon-o-check', 'w-4 h-4')
                        Speichern
                    </button>
                </div>
            </x-slot>
        </x-ui-modal>
    @endif
</x-ui-page>
