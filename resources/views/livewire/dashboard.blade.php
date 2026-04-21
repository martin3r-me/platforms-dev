<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Dev" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Dev', 'icon' => 'code-bracket'],
        ]">
            <button wire:click="$set('showErrorTracking', true)"
                    class="inline-flex items-center gap-2 px-3 py-1.5 text-sm font-medium text-gray-700 bg-gray-50 hover:bg-gray-100 rounded-md border border-gray-300 transition-colors">
                @svg('heroicon-o-bug-ant', 'w-4 h-4')
                <span>Error Tracking</span>
            </button>
            <button wire:click="openActivateModal"
                    class="inline-flex items-center gap-2 px-3 py-1.5 text-sm font-medium text-white bg-green-600 hover:bg-green-700 rounded-md border border-green-700 transition-colors">
                @svg('heroicon-o-plus', 'w-4 h-4')
                <span>New package</span>
            </button>
        </x-ui-page-actionbar>
    </x-slot>

    <x-ui-page-container>
        {{-- Stats Counter Row --}}
        <div class="flex items-center gap-6 pb-4 mb-6 border-b border-gray-200">
            <div class="flex items-center gap-2 text-sm">
                <span class="text-gray-500">Packages</span>
                <span class="font-semibold text-gray-900">{{ $totalPackages }}</span>
            </div>
            <div class="flex items-center gap-2 text-sm">
                @svg('heroicon-o-clock', 'w-4 h-4 text-yellow-600')
                <span class="text-gray-500">Open</span>
                <span class="font-semibold text-gray-900">{{ $totalOpen }}</span>
            </div>
            <div class="flex items-center gap-2 text-sm">
                @svg('heroicon-o-fire', 'w-4 h-4 text-red-500')
                <span class="text-gray-500">High</span>
                <span class="font-semibold text-red-600">{{ $totalHighPriority }}</span>
            </div>
            <div class="flex items-center gap-2 text-sm">
                @svg('heroicon-o-exclamation-circle', 'w-4 h-4 text-red-500')
                <span class="text-gray-500">Overdue</span>
                <span class="font-semibold {{ $totalOverdue > 0 ? 'text-red-600' : 'text-gray-900' }}">{{ $totalOverdue }}</span>
            </div>
            <div class="flex items-center gap-2 text-sm">
                @svg('heroicon-o-check-circle', 'w-4 h-4 text-green-500')
                <span class="text-gray-500">Done</span>
                <span class="font-semibold text-green-600">{{ $totalDone }}</span>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
            {{-- Letzte Commits --}}
            <div class="lg:col-span-2 bg-white rounded-md border border-gray-200 overflow-hidden">
                <div class="flex items-center gap-2 px-4 py-3 border-b border-gray-200 bg-gray-50">
                    @svg('heroicon-o-code-bracket', 'w-4 h-4 text-gray-500')
                    <h3 class="text-sm font-semibold text-gray-900">Recent commits</h3>
                    @if($recentCommits->isNotEmpty())
                        <span class="px-2 py-0.5 text-xs font-medium rounded-full bg-gray-200 text-gray-600">{{ $recentCommits->count() }}</span>
                    @endif
                </div>

                <div>
                    @forelse($recentCommits as $commit)
                        <div class="d-flex items-start gap-3 px-4 py-2.5 hover:bg-gray-50 transition-colors group border-b border-gray-100 last:border-b-0">
                            {{-- Git Graph Dot --}}
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
                                    <span>{{ $commit->repo->name ?? '' }}</span>
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
                            @svg('heroicon-o-code-bracket', 'w-8 h-8 text-gray-300 mx-auto mb-3')
                            <p class="text-sm font-medium text-gray-900 mb-1">No commits yet</p>
                            <p class="text-xs text-gray-500">Commits sync automatically every hour.</p>
                        </div>
                    @endforelse
                </div>
            </div>

            {{-- Rechte Spalte: Open PRs + Packages --}}
            <div class="space-y-6">
                {{-- Open Pull Requests --}}
                <div class="bg-white rounded-md border border-gray-200 overflow-hidden">
                    <div class="flex items-center gap-2 px-4 py-3 border-b border-gray-200 bg-gray-50">
                        @svg('heroicon-o-arrow-path', 'w-4 h-4 text-green-600')
                        <h3 class="text-sm font-semibold text-gray-900">Open pull requests</h3>
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
                                            #{{ $pr->number }} &middot; {{ $pr->author_login }} &middot; {{ $pr->repo->name ?? '' }}
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

                {{-- Packages --}}
                <div class="bg-white rounded-md border border-gray-200 overflow-hidden">
                    <div class="flex items-center gap-2 px-4 py-3 border-b border-gray-200 bg-gray-50">
                        @svg('heroicon-o-cube', 'w-4 h-4 text-gray-500')
                        <h3 class="text-sm font-semibold text-gray-900">Repositories</h3>
                        @if($packages->isNotEmpty())
                            <span class="px-2 py-0.5 text-xs font-medium rounded-full bg-gray-200 text-gray-600">{{ $packages->count() }}</span>
                        @endif
                    </div>
                    <div>
                        @forelse($packages as $package)
                            <a href="{{ route('dev.packages.show', $package) }}"
                               wire:navigate
                               class="d-flex items-center gap-3 px-4 py-3 hover:bg-gray-50 transition-colors border-b border-gray-100 last:border-b-0">
                                <div class="flex-shrink-0 text-gray-400">
                                    @svg($package->icon ?? 'heroicon-o-cube', 'w-4 h-4')
                                </div>
                                <div class="min-w-0 flex-grow-1">
                                    <span class="text-sm font-medium text-blue-600 truncate block">{{ $package->name }}</span>
                                    @if($package->github_repo_full_name)
                                        <span class="text-xs text-gray-500 font-mono truncate block">{{ $package->github_repo_full_name }}</span>
                                    @endif
                                </div>
                                <div class="flex-shrink-0 d-flex items-center gap-2">
                                    @if(($packageStats[$package->id]['open_features'] ?? 0) > 0)
                                        <span class="inline-flex items-center gap-1 text-xs px-2 py-0.5 rounded-full bg-blue-50 text-blue-700 font-medium">
                                            @svg('heroicon-o-light-bulb', 'w-3 h-3')
                                            {{ $packageStats[$package->id]['open_features'] }}
                                        </span>
                                    @endif
                                    @if(($packageStats[$package->id]['open_bugs'] ?? 0) > 0)
                                        <span class="inline-flex items-center gap-1 text-xs px-2 py-0.5 rounded-full bg-red-50 text-red-700 font-medium">
                                            @svg('heroicon-o-bug-ant', 'w-3 h-3')
                                            {{ $packageStats[$package->id]['open_bugs'] }}
                                        </span>
                                    @endif
                                    @if(($packageStats[$package->id]['open_features'] ?? 0) === 0 && ($packageStats[$package->id]['open_bugs'] ?? 0) === 0)
                                        <span class="inline-flex items-center gap-1 text-xs text-green-600">
                                            @svg('heroicon-o-check-circle', 'w-3 h-3')
                                        </span>
                                    @endif
                                </div>
                            </a>
                        @empty
                            <div class="p-8 text-center">
                                @svg('heroicon-o-cube', 'w-8 h-8 text-gray-300 mx-auto mb-3')
                                <p class="text-sm font-medium text-gray-900 mb-1">No packages yet</p>
                                <p class="text-xs text-gray-500 mb-3">Activate your first package.</p>
                                <button wire:click="openActivateModal"
                                        class="inline-flex items-center gap-2 px-3 py-1.5 text-sm font-medium text-white bg-green-600 hover:bg-green-700 rounded-md border border-green-700 transition-colors">
                                    @svg('heroicon-o-plus', 'w-4 h-4')
                                    <span>Activate package</span>
                                </button>
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>

        {{-- Zweite Reihe: Offene Issues + Zuletzt erledigt --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
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
                        <a href="{{ route('dev.packages.issues.show', [$issue->board->package, $issue]) }}"
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
                                    {{ $issue->board->package->name }} &middot; {{ $issue->board->name }}
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
                        <a href="{{ route('dev.packages.issues.show', [$issue->board->package, $issue]) }}"
                           wire:navigate
                           class="d-flex items-center gap-3 px-4 py-3 hover:bg-gray-50 transition-colors border-b border-gray-100 last:border-b-0">
                            <div class="flex-shrink-0">
                                @svg('heroicon-o-check-circle', 'w-4 h-4 text-purple-500')
                            </div>
                            <div class="min-w-0 flex-grow-1">
                                <div class="text-sm text-gray-400 line-through truncate">{{ $issue->title }}</div>
                                <div class="text-xs text-gray-500">
                                    {{ $issue->board->package->name }}
                                    @if($issue->done_at)
                                        &middot; {{ $issue->done_at->diffForHumans() }}
                                    @endif
                                </div>
                            </div>
                        </a>
                    @empty
                        <div class="p-8 text-center">
                            @svg('heroicon-o-clipboard-document-check', 'w-6 h-6 text-gray-300 mx-auto mb-2')
                            <p class="text-sm text-gray-500">Nothing closed yet.</p>
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
                        <div class="w-10 h-10 bg-red-50 rounded-lg flex items-center justify-center">
                            @svg('heroicon-o-bug-ant', 'w-5 h-5 text-red-600')
                        </div>
                    </div>
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900">Error Tracking</h3>
                        <p class="text-sm text-gray-500">Configure error tracking</p>
                    </div>
                </div>
            </x-slot>

            <div class="space-y-6">
                {{-- Status --}}
                <div class="d-flex items-center gap-3 p-3 rounded-md {{ $errorEndpointConfigured ? 'bg-green-50 border border-green-200' : 'bg-yellow-50 border border-yellow-200' }}">
                    @if($errorEndpointConfigured)
                        @svg('heroicon-s-check-circle', 'w-5 h-5 text-green-600 flex-shrink-0')
                        <span class="text-sm text-green-700">Error Tracking ist aktiv auf dieser Instanz.</span>
                    @else
                        @svg('heroicon-s-exclamation-triangle', 'w-5 h-5 text-yellow-600 flex-shrink-0')
                        <span class="text-sm text-yellow-700">DEV_ERROR_ENDPOINT ist nicht konfiguriert.</span>
                    @endif
                </div>

                {{-- Ingest URL --}}
                <div>
                    <h4 class="text-sm font-semibold text-gray-900 mb-3">Ingest Endpoint</h4>
                    <p class="text-xs text-gray-500 mb-3">Dieser Endpoint empfaengt Fehler von allen Instanzen. Ein Token, ein Endpoint fuer das ganze Team.</p>

                    @if($ingestUrl)
                        <div class="space-y-3">
                            <div class="p-3 rounded-md bg-gray-50 border border-gray-200">
                                <div class="text-xs text-gray-500 mb-1">Ingest URL</div>
                                <code class="text-xs text-gray-900 break-all select-all font-mono">{{ $ingestUrl }}</code>
                            </div>
                            <div class="p-3 rounded-md bg-blue-50 border border-blue-200">
                                <div class="text-xs text-gray-500 mb-1">.env Variable (in jede sendende Instanz eintragen)</div>
                                <code class="text-xs text-blue-700 font-bold font-mono select-all">DEV_ERROR_ENDPOINT={{ $ingestUrl }}</code>
                            </div>
                            <p class="text-xs text-gray-500">Nach dem Setzen: <code class="font-mono text-gray-700">php artisan config:cache</code> ausfuehren.</p>
                            <button wire:click="regenerateTeamToken" wire:confirm="Token neu generieren? Alle Instanzen muessen dann die neue URL erhalten."
                                    class="inline-flex items-center gap-2 px-3 py-1.5 text-sm font-medium text-red-600 bg-red-50 hover:bg-red-100 rounded-md border border-red-200 transition-colors">
                                @svg('heroicon-o-arrow-path', 'w-3.5 h-3.5')
                                <span>Token neu generieren</span>
                            </button>
                        </div>
                    @else
                        <div class="text-center py-4">
                            <p class="text-sm text-gray-500 mb-3">Noch kein Ingest-Token generiert.</p>
                            <button wire:click="generateTeamToken"
                                    class="inline-flex items-center gap-2 px-3 py-1.5 text-sm font-medium text-white bg-green-600 hover:bg-green-700 rounded-md border border-green-700 transition-colors">
                                @svg('heroicon-o-shield-check', 'w-4 h-4')
                                <span>Token generieren</span>
                            </button>
                        </div>
                    @endif
                </div>

                {{-- How it works --}}
                <div class="p-3 rounded-md bg-gray-50 border border-gray-200">
                    <h4 class="text-xs font-semibold text-gray-900 mb-2">So funktioniert's</h4>
                    <ol class="text-xs text-gray-500 space-y-1 list-decimal list-inside">
                        <li>Token generieren (oben)</li>
                        <li><code class="font-mono text-gray-700">DEV_ERROR_ENDPOINT</code> in .env jeder Instanz eintragen</li>
                        <li><code class="font-mono text-gray-700">php artisan config:cache</code></li>
                        <li>Fehler werden automatisch dem richtigen Package zugeordnet</li>
                        <li>Per-Package Settings (welche Codes, auto-Issue, etc.) unter dem jeweiligen Package konfigurieren</li>
                    </ol>
                </div>
            </div>
            <x-slot name="footer">
                <div class="flex justify-end">
                    <button wire:click="$set('showErrorTracking', false)"
                            class="inline-flex items-center gap-2 px-3 py-1.5 text-sm font-medium text-gray-700 bg-gray-50 hover:bg-gray-100 rounded-md border border-gray-300 transition-colors">
                        Schliessen
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
                        <div class="w-10 h-10 bg-gray-100 rounded-lg flex items-center justify-center">
                            @svg('heroicon-o-cube', 'w-5 h-5 text-gray-600')
                        </div>
                    </div>
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900">Package aktivieren</h3>
                        <p class="text-sm text-gray-500">Neues Package aus GitHub-Repo erstellen</p>
                    </div>
                </div>
            </x-slot>

            <div class="space-y-6">
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
                    <div class="p-3 rounded-md bg-gray-50 border border-gray-200">
                        <p class="text-xs text-gray-500">Keine GitHub-Repositories verfuegbar. Verbinde GitHub unter Integrationen und synchronisiere deine Repos.</p>
                    </div>
                @endif
                <x-ui-input-text wire:model="activatePackageName" label="Name" placeholder="z.B. Platform Sheets" required />
                <x-ui-input-textarea wire:model="activatePackageDescription" label="Beschreibung" placeholder="Optional" />
            </div>

            <x-slot name="footer">
                <div class="flex justify-end gap-3">
                    <button wire:click="$set('showActivateModal', false)"
                            class="inline-flex items-center gap-2 px-3 py-1.5 text-sm font-medium text-gray-700 bg-gray-50 hover:bg-gray-100 rounded-md border border-gray-300 transition-colors">
                        Abbrechen
                    </button>
                    <button wire:click="activatePackage"
                            class="inline-flex items-center gap-2 px-3 py-1.5 text-sm font-medium text-white bg-green-600 hover:bg-green-700 rounded-md border border-green-700 transition-colors">
                        @svg('heroicon-o-check', 'w-4 h-4')
                        Aktivieren
                    </button>
                </div>
            </x-slot>
        </x-ui-modal>
    @endif
</x-ui-page>
