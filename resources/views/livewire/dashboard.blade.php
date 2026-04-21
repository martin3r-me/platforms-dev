<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Dev" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Dev', 'icon' => 'code-bracket'],
        ]">
            <button wire:click="$set('showErrorTracking', true)"
                    class="inline-flex items-center gap-1.5 px-3 py-[5px] text-xs font-medium text-gray-700 bg-gray-50 hover:bg-gray-100 rounded-md border border-gray-300 transition-colors">
                @svg('heroicon-o-bug-ant', 'w-3.5 h-3.5')
                <span>Error Tracking</span>
            </button>
            <button wire:click="openActivateModal"
                    class="inline-flex items-center gap-1.5 px-3 py-[5px] text-xs font-medium text-white bg-[#238636] hover:bg-[#2ea043] rounded-md border border-[#2ea043] transition-colors">
                @svg('heroicon-o-plus', 'w-3.5 h-3.5')
                <span>New package</span>
            </button>
        </x-ui-page-actionbar>
    </x-slot>

    <x-ui-page-container>
        {{-- Stats Counter Row --}}
        <div class="flex items-center gap-8 py-4 mb-8 border-b border-gray-200">
            <div class="flex items-baseline gap-1.5">
                <span class="text-2xl font-semibold text-gray-900 tabular-nums">{{ $totalPackages }}</span>
                <span class="text-xs text-gray-500">packages</span>
            </div>
            <div class="flex items-baseline gap-1.5">
                <span class="text-2xl font-semibold text-gray-900 tabular-nums">{{ $totalOpen }}</span>
                <span class="text-xs text-gray-500">open</span>
            </div>
            @if($totalHighPriority > 0)
                <div class="flex items-baseline gap-1.5">
                    <span class="text-2xl font-semibold text-red-600 tabular-nums">{{ $totalHighPriority }}</span>
                    <span class="text-xs text-gray-500">critical</span>
                </div>
            @endif
            @if($totalOverdue > 0)
                <div class="flex items-baseline gap-1.5">
                    <span class="text-2xl font-semibold text-red-600 tabular-nums">{{ $totalOverdue }}</span>
                    <span class="text-xs text-gray-500">overdue</span>
                </div>
            @endif
            <div class="flex items-baseline gap-1.5">
                <span class="text-2xl font-semibold text-green-600 tabular-nums">{{ $totalDone }}</span>
                <span class="text-xs text-gray-500">closed</span>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 mb-8">
            {{-- Letzte Commits --}}
            <div class="lg:col-span-2 rounded-md border border-gray-200 overflow-hidden">
                <div class="flex items-center gap-2 px-5 py-3 bg-gray-50 border-b border-gray-200">
                    @svg('heroicon-o-code-bracket', 'w-4 h-4 text-gray-500')
                    <h3 class="text-xs font-semibold text-gray-900">Recent commits</h3>
                    @if($recentCommits->isNotEmpty())
                        <span class="ml-auto px-2 py-0.5 text-[11px] font-medium rounded-full bg-neutral-200/80 text-gray-600 tabular-nums">{{ $recentCommits->count() }}</span>
                    @endif
                </div>

                <div>
                    @forelse($recentCommits as $commit)
                        <div class="flex items-start gap-3 px-5 py-3 hover:bg-gray-50 transition-colors group {{ !$loop->last ? 'border-b border-gray-100' : '' }}">
                            <div class="flex-shrink-0 flex flex-col items-center mt-1.5" style="width: 12px;">
                                <div class="w-2 h-2 rounded-full bg-green-500"></div>
                                @if(!$loop->last)
                                    <div class="w-px flex-1 bg-gray-200 mt-0.5" style="min-height: 24px;"></div>
                                @endif
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="text-xs text-gray-900 truncate group-hover:text-blue-600 transition-colors font-medium leading-relaxed">{{ Str::limit(Str::before($commit->message, "\n"), 80) }}</div>
                                <div class="text-[11px] text-gray-500 mt-1 flex items-center gap-1.5">
                                    <span class="font-medium text-gray-700">{{ $commit->author_login ?? $commit->author_name }}</span>
                                    <span class="text-gray-300">/</span>
                                    <span>{{ $commit->repo->name ?? '' }}</span>
                                    <span class="text-gray-300">/</span>
                                    <code class="px-1.5 py-0.5 text-[10px] font-mono bg-gray-100 text-gray-600 rounded">{{ Str::limit($commit->sha, 7, '') }}</code>
                                    <span class="ml-auto text-gray-400">{{ $commit->committed_at?->diffForHumans() }}</span>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="py-16 text-center">
                            @svg('heroicon-o-code-bracket', 'w-10 h-10 text-gray-200 mx-auto mb-4')
                            <p class="text-xs font-medium text-gray-700 mb-1">No commits yet</p>
                            <p class="text-[11px] text-gray-500">Commits sync automatically every hour.</p>
                        </div>
                    @endforelse
                </div>
            </div>

            {{-- Rechte Spalte: Open PRs + Packages --}}
            <div class="space-y-8">
                {{-- Open Pull Requests --}}
                <div class="rounded-md border border-gray-200 overflow-hidden">
                    <div class="flex items-center gap-2 px-5 py-3 bg-gray-50 border-b border-gray-200">
                        @svg('heroicon-o-arrow-path', 'w-4 h-4 text-green-600')
                        <h3 class="text-xs font-semibold text-gray-900">Pull requests</h3>
                        @if($openPullRequests->isNotEmpty())
                            <span class="ml-auto px-2 py-0.5 text-[11px] font-medium rounded-full bg-green-100 text-green-700 tabular-nums">{{ $openPullRequests->count() }}</span>
                        @endif
                    </div>
                    <div>
                        @forelse($openPullRequests as $pr)
                            <div class="px-5 py-3 hover:bg-gray-50 transition-colors {{ !$loop->last ? 'border-b border-gray-100' : '' }}">
                                <div class="flex items-start gap-2.5">
                                    <div class="flex-shrink-0 mt-0.5">
                                        @if($pr->is_draft)
                                            <div class="w-4 h-4 rounded-full border-[1.5px] border-dashed border-gray-400"></div>
                                        @else
                                            @svg('heroicon-o-arrow-path', 'w-4 h-4 text-green-600')
                                        @endif
                                    </div>
                                    <div class="min-w-0">
                                        <div class="text-xs font-medium text-gray-900 truncate leading-relaxed">{{ $pr->title }}</div>
                                        <div class="text-[11px] text-gray-500 mt-0.5">
                                            #{{ $pr->number }} &middot; {{ $pr->author_login }} &middot; {{ $pr->repo->name ?? '' }}
                                            @if($pr->is_draft)
                                                &middot; <span class="italic text-gray-400">Draft</span>
                                            @endif
                                        </div>
                                        @if($pr->head_ref)
                                            <div class="flex items-center gap-1.5 mt-2">
                                                <code class="px-1.5 py-0.5 text-[10px] font-mono bg-blue-50 text-blue-700 rounded border border-blue-100">{{ $pr->head_ref }}</code>
                                                <span class="text-gray-300">&rarr;</span>
                                                <code class="px-1.5 py-0.5 text-[10px] font-mono bg-gray-100 text-gray-600 rounded">{{ $pr->base_ref }}</code>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="py-10 text-center">
                                @svg('heroicon-o-check-circle', 'w-6 h-6 text-green-300 mx-auto mb-2')
                                <p class="text-[11px] text-gray-500">No open PRs</p>
                            </div>
                        @endforelse
                    </div>
                </div>

                {{-- Packages --}}
                <div class="rounded-md border border-gray-200 overflow-hidden">
                    <div class="flex items-center gap-2 px-5 py-3 bg-gray-50 border-b border-gray-200">
                        <svg class="w-4 h-4 text-gray-500" viewBox="0 0 16 16" fill="currentColor"><path d="M2 2.5A2.5 2.5 0 0 1 4.5 0h8.75a.75.75 0 0 1 .75.75v12.5a.75.75 0 0 1-.75.75h-2.5a.75.75 0 0 1 0-1.5h1.75v-2h-8a1 1 0 0 0-.714 1.7.75.75 0 1 1-1.072 1.05A2.495 2.495 0 0 1 2 11.5Zm10.5-1h-8a1 1 0 0 0-1 1v6.708A2.486 2.486 0 0 1 4.5 9h8ZM5 12.25a.25.25 0 0 1 .25-.25h3.5a.25.25 0 0 1 .25.25v3.25a.25.25 0 0 1-.4.2l-1.45-1.087a.25.25 0 0 0-.3 0L5.4 15.7a.25.25 0 0 1-.4-.2Z"/></svg>
                        <h3 class="text-xs font-semibold text-gray-900">Repositories</h3>
                        @if($packages->isNotEmpty())
                            <span class="ml-auto px-2 py-0.5 text-[11px] font-medium rounded-full bg-neutral-200/80 text-gray-600 tabular-nums">{{ $packages->count() }}</span>
                        @endif
                    </div>
                    <div>
                        @forelse($packages as $package)
                            <a href="{{ route('dev.packages.show', $package) }}"
                               wire:navigate
                               class="flex items-center gap-3 px-5 py-3 hover:bg-gray-50 transition-colors {{ !$loop->last ? 'border-b border-gray-100' : '' }}">
                                <div class="flex-shrink-0 text-gray-400">
                                    @svg($package->icon ?? 'heroicon-o-cube', 'w-4 h-4')
                                </div>
                                <div class="min-w-0 flex-1">
                                    <span class="text-xs font-medium text-blue-600 hover:underline truncate block leading-relaxed">{{ $package->name }}</span>
                                    @if($package->github_repo_full_name)
                                        <span class="text-[11px] text-gray-500 font-mono truncate block mt-0.5">{{ $package->github_repo_full_name }}</span>
                                    @endif
                                </div>
                                <div class="flex-shrink-0 flex items-center gap-1.5">
                                    @if(($packageStats[$package->id]['open_bugs'] ?? 0) > 0)
                                        <span class="inline-flex items-center gap-1 text-[11px] px-1.5 py-0.5 rounded-full bg-red-50 text-red-700 font-medium border border-red-100">
                                            {{ $packageStats[$package->id]['open_bugs'] }}
                                        </span>
                                    @endif
                                    @if(($packageStats[$package->id]['open_features'] ?? 0) > 0)
                                        <span class="inline-flex items-center gap-1 text-[11px] px-1.5 py-0.5 rounded-full bg-blue-50 text-blue-700 font-medium border border-blue-100">
                                            {{ $packageStats[$package->id]['open_features'] }}
                                        </span>
                                    @endif
                                </div>
                            </a>
                        @empty
                            <div class="py-12 text-center">
                                @svg('heroicon-o-cube', 'w-10 h-10 text-gray-200 mx-auto mb-4')
                                <p class="text-xs font-medium text-gray-700 mb-1">No packages yet</p>
                                <p class="text-[11px] text-gray-500 mb-4">Activate your first package to get started.</p>
                                <button wire:click="openActivateModal"
                                        class="inline-flex items-center gap-1.5 px-3 py-[5px] text-xs font-medium text-white bg-[#238636] hover:bg-[#2ea043] rounded-md border border-[#2ea043] transition-colors">
                                    @svg('heroicon-o-plus', 'w-3.5 h-3.5')
                                    <span>New package</span>
                                </button>
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>

        {{-- Zweite Reihe: Offene Issues + Zuletzt erledigt --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            {{-- Letzte offene Issues --}}
            <div class="rounded-md border border-gray-200 overflow-hidden">
                <div class="flex items-center gap-2 px-5 py-3 bg-gray-50 border-b border-gray-200">
                    <svg class="w-4 h-4 text-green-600" viewBox="0 0 16 16" fill="currentColor"><path d="M8 9.5a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3Z"/><path d="M8 0a8 8 0 1 1 0 16A8 8 0 0 1 8 0ZM1.5 8a6.5 6.5 0 1 0 13 0 6.5 6.5 0 0 0-13 0Z"/></svg>
                    <h3 class="text-xs font-semibold text-gray-900">Open issues</h3>
                    @if($recentIssues->isNotEmpty())
                        <span class="ml-auto px-2 py-0.5 text-[11px] font-medium rounded-full bg-neutral-200/80 text-gray-600 tabular-nums">{{ $recentIssues->count() }}</span>
                    @endif
                </div>
                <div>
                    @forelse($recentIssues as $issue)
                        <a href="{{ route('dev.packages.issues.show', [$issue->board->package, $issue]) }}"
                           wire:navigate
                           class="flex items-center gap-3 px-5 py-3 hover:bg-gray-50 transition-colors {{ !$loop->last ? 'border-b border-gray-100' : '' }}">
                            <div class="flex-shrink-0">
                                <svg class="w-4 h-4 text-green-600" viewBox="0 0 16 16" fill="currentColor"><path d="M8 9.5a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3Z"/><path d="M8 0a8 8 0 1 1 0 16A8 8 0 0 1 8 0ZM1.5 8a6.5 6.5 0 1 0 13 0 6.5 6.5 0 0 0-13 0Z"/></svg>
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="text-xs font-medium text-gray-900 truncate leading-relaxed">{{ $issue->title }}</div>
                                <div class="text-[11px] text-gray-500 mt-0.5">
                                    {{ $issue->board->package->name }} &middot; {{ $issue->board->name }}
                                    @if($issue->userInCharge)
                                        &middot; {{ $issue->userInCharge->name }}
                                    @endif
                                </div>
                            </div>
                            <div class="flex-shrink-0 text-[11px] text-gray-400">
                                {{ $issue->created_at->diffForHumans() }}
                            </div>
                        </a>
                    @empty
                        <div class="py-10 text-center">
                            @svg('heroicon-o-check-circle', 'w-6 h-6 text-green-300 mx-auto mb-2')
                            <p class="text-[11px] text-gray-500">No open issues</p>
                        </div>
                    @endforelse
                </div>
            </div>

            {{-- Zuletzt erledigt --}}
            <div class="rounded-md border border-gray-200 overflow-hidden">
                <div class="flex items-center gap-2 px-5 py-3 bg-gray-50 border-b border-gray-200">
                    <svg class="w-4 h-4 text-purple-600" viewBox="0 0 16 16" fill="currentColor"><path d="M13.78 4.22a.75.75 0 0 1 0 1.06l-7.25 7.25a.75.75 0 0 1-1.06 0L2.22 9.28a.751.751 0 0 1 .018-1.042.751.751 0 0 1 1.042-.018L6 10.94l6.72-6.72a.75.75 0 0 1 1.06 0Z"/></svg>
                    <h3 class="text-xs font-semibold text-gray-900">Closed</h3>
                    @if($recentlyDone->isNotEmpty())
                        <span class="ml-auto px-2 py-0.5 text-[11px] font-medium rounded-full bg-purple-100 text-purple-700 tabular-nums">{{ $recentlyDone->count() }}</span>
                    @endif
                </div>
                <div>
                    @forelse($recentlyDone as $issue)
                        <a href="{{ route('dev.packages.issues.show', [$issue->board->package, $issue]) }}"
                           wire:navigate
                           class="flex items-center gap-3 px-5 py-3 hover:bg-gray-50 transition-colors {{ !$loop->last ? 'border-b border-gray-100' : '' }}">
                            <div class="flex-shrink-0">
                                <svg class="w-4 h-4 text-purple-600" viewBox="0 0 16 16" fill="currentColor"><path d="M11.28 6.78a.75.75 0 0 0-1.06-1.06L7.25 8.69 5.78 7.22a.75.75 0 0 0-1.06 1.06l2 2a.75.75 0 0 0 1.06 0ZM16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0Zm-1.5 0a6.5 6.5 0 1 0-13 0 6.5 6.5 0 0 0 13 0Z"/></svg>
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="text-xs text-gray-400 line-through truncate">{{ $issue->title }}</div>
                                <div class="text-[11px] text-gray-500 mt-0.5">
                                    {{ $issue->board->package->name }}
                                    @if($issue->done_at)
                                        &middot; {{ $issue->done_at->diffForHumans() }}
                                    @endif
                                </div>
                            </div>
                        </a>
                    @empty
                        <div class="py-10 text-center">
                            @svg('heroicon-o-clipboard-document-check', 'w-6 h-6 text-gray-200 mx-auto mb-2')
                            <p class="text-[11px] text-gray-500">Nothing closed yet</p>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </x-ui-page-container>

    {{-- Error Tracking Modal --}}
    @if($showErrorTracking)
        <x-ui-modal wire:model="showErrorTracking" size="md" :backdropClosable="true" :escClosable="true">
            <x-slot name="header">
                <div class="flex items-center gap-3">
                    <div class="flex-shrink-0">
                        <div class="w-10 h-10 bg-red-50 rounded-lg flex items-center justify-center border border-red-100">
                            @svg('heroicon-o-bug-ant', 'w-5 h-5 text-red-600')
                        </div>
                    </div>
                    <div>
                        <h3 class="text-sm font-semibold text-gray-900">Error Tracking</h3>
                        <p class="text-xs text-gray-500 mt-0.5">Configure error ingestion</p>
                    </div>
                </div>
            </x-slot>

            <div class="space-y-5">
                <div class="flex items-center gap-3 p-4 rounded-md {{ $errorEndpointConfigured ? 'bg-green-50 border border-green-200' : 'bg-yellow-50 border border-yellow-200' }}">
                    @if($errorEndpointConfigured)
                        @svg('heroicon-s-check-circle', 'w-5 h-5 text-green-600 flex-shrink-0')
                        <span class="text-xs text-green-800">Error Tracking ist aktiv auf dieser Instanz.</span>
                    @else
                        @svg('heroicon-s-exclamation-triangle', 'w-5 h-5 text-yellow-600 flex-shrink-0')
                        <span class="text-xs text-yellow-800">DEV_ERROR_ENDPOINT ist nicht konfiguriert.</span>
                    @endif
                </div>

                <div>
                    <h4 class="text-xs font-semibold text-gray-900 mb-2">Ingest Endpoint</h4>
                    <p class="text-[11px] text-gray-500 mb-3">Ein Token, ein Endpoint fuer das ganze Team.</p>

                    @if($ingestUrl)
                        <div class="space-y-3">
                            <div class="p-3 rounded-md bg-gray-50 border border-gray-200">
                                <div class="text-[11px] text-gray-500 mb-1">Ingest URL</div>
                                <code class="text-[11px] text-gray-900 break-all select-all font-mono">{{ $ingestUrl }}</code>
                            </div>
                            <div class="p-3 rounded-md bg-blue-50 border border-blue-200">
                                <div class="text-[11px] text-gray-500 mb-1">.env Variable</div>
                                <code class="text-[11px] text-blue-800 font-bold font-mono select-all">DEV_ERROR_ENDPOINT={{ $ingestUrl }}</code>
                            </div>
                            <p class="text-[11px] text-gray-500">Nach dem Setzen: <code class="font-mono text-gray-700 bg-gray-100 px-1 py-0.5 rounded">php artisan config:cache</code></p>
                            <button wire:click="regenerateTeamToken" wire:confirm="Token neu generieren? Alle Instanzen muessen dann die neue URL erhalten."
                                    class="inline-flex items-center gap-1.5 px-3 py-[5px] text-xs font-medium text-red-700 bg-red-50 hover:bg-red-100 rounded-md border border-red-200 transition-colors">
                                @svg('heroicon-o-arrow-path', 'w-3.5 h-3.5')
                                <span>Token neu generieren</span>
                            </button>
                        </div>
                    @else
                        <div class="text-center py-4">
                            <p class="text-xs text-gray-500 mb-3">Noch kein Ingest-Token generiert.</p>
                            <button wire:click="generateTeamToken"
                                    class="inline-flex items-center gap-1.5 px-3 py-[5px] text-xs font-medium text-white bg-[#238636] hover:bg-[#2ea043] rounded-md border border-[#2ea043] transition-colors">
                                @svg('heroicon-o-shield-check', 'w-3.5 h-3.5')
                                <span>Token generieren</span>
                            </button>
                        </div>
                    @endif
                </div>

                <div class="p-3 rounded-md bg-gray-50 border border-gray-200">
                    <h4 class="text-[11px] font-semibold text-gray-900 mb-2">So funktioniert's</h4>
                    <ol class="text-[11px] text-gray-500 space-y-1 list-decimal list-inside">
                        <li>Token generieren</li>
                        <li><code class="font-mono text-gray-700 bg-gray-100 px-1 rounded">DEV_ERROR_ENDPOINT</code> in .env eintragen</li>
                        <li><code class="font-mono text-gray-700 bg-gray-100 px-1 rounded">php artisan config:cache</code></li>
                        <li>Fehler werden automatisch dem richtigen Package zugeordnet</li>
                    </ol>
                </div>
            </div>
            <x-slot name="footer">
                <div class="flex justify-end">
                    <button wire:click="$set('showErrorTracking', false)"
                            class="inline-flex items-center gap-1.5 px-3 py-[5px] text-xs font-medium text-gray-700 bg-gray-50 hover:bg-gray-100 rounded-md border border-gray-300 transition-colors">
                        Close
                    </button>
                </div>
            </x-slot>
        </x-ui-modal>
    @endif

    {{-- Activate Modal --}}
    @if($showActivateModal)
        <x-ui-modal wire:model="showActivateModal" size="md" :backdropClosable="true" :escClosable="true">
            <x-slot name="header">
                <div class="flex items-center gap-3">
                    <div class="flex-shrink-0">
                        <div class="w-10 h-10 bg-gray-100 rounded-lg flex items-center justify-center border border-gray-200">
                            @svg('heroicon-o-cube', 'w-5 h-5 text-gray-600')
                        </div>
                    </div>
                    <div>
                        <h3 class="text-sm font-semibold text-gray-900">New package</h3>
                        <p class="text-xs text-gray-500 mt-0.5">Create from GitHub repository</p>
                    </div>
                </div>
            </x-slot>

            <div class="space-y-5">
                @if($availableRepos->isNotEmpty())
                    <x-ui-input-select
                        name="selectedRepoId"
                        wire:model.live="selectedRepoId"
                        label="GitHub Repository"
                        :nullable="true"
                        nullLabel="-- Without repository --"
                        :options="$availableRepos->mapWithKeys(fn ($r) => [$r->id => $r->full_name . ($r->is_private ? ' (privat)' : '') . ($r->language ? ' · '.$r->language : '')])->toArray()"
                    />
                @else
                    <div class="p-4 rounded-md bg-gray-50 border border-gray-200">
                        <p class="text-[11px] text-gray-500">Keine GitHub-Repositories verfuegbar. Verbinde GitHub unter Integrationen.</p>
                    </div>
                @endif
                <x-ui-input-text wire:model="activatePackageName" label="Name" placeholder="z.B. Platform Sheets" required />
                <x-ui-input-textarea wire:model="activatePackageDescription" label="Beschreibung" placeholder="Optional" />
            </div>

            <x-slot name="footer">
                <div class="flex justify-end gap-2">
                    <button wire:click="$set('showActivateModal', false)"
                            class="inline-flex items-center gap-1.5 px-3 py-[5px] text-xs font-medium text-gray-700 bg-gray-50 hover:bg-gray-100 rounded-md border border-gray-300 transition-colors">
                        Cancel
                    </button>
                    <button wire:click="activatePackage"
                            class="inline-flex items-center gap-1.5 px-3 py-[5px] text-xs font-medium text-white bg-[#238636] hover:bg-[#2ea043] rounded-md border border-[#2ea043] transition-colors">
                        @svg('heroicon-o-check', 'w-3.5 h-3.5')
                        Create package
                    </button>
                </div>
            </x-slot>
        </x-ui-modal>
    @endif
</x-ui-page>
