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

        {{-- Error Occurrences --}}
        @if($errorSettingsEnabled && $errorOccurrences->count() > 0)
            <div class="bg-[var(--ui-surface)] rounded-lg border border-[var(--ui-danger)]/30 mb-6">
                <div class="p-4 border-b border-[var(--ui-border)]/60 d-flex items-center justify-between">
                    <div class="d-flex items-center gap-2">
                        @svg('heroicon-o-bug-ant', 'w-4 h-4 text-[var(--ui-danger)]')
                        <h3 class="text-sm font-semibold text-[var(--ui-secondary)]">Offene Errors</h3>
                        <span class="text-xs px-1.5 py-0.5 rounded bg-[var(--ui-danger)]/10 text-[var(--ui-danger)] font-medium">{{ $errorOccurrences->count() }}</span>
                    </div>
                </div>
                <div class="divide-y divide-[var(--ui-border)]/40">
                    @foreach($errorOccurrences as $occurrence)
                        <div class="p-3 d-flex items-start gap-3 group">
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

    {{-- Error Tracking Settings Modal --}}
    @if($showErrorSettings && $errorSettings)
        <x-ui-modal wire:model="showErrorSettings" title="Error Tracking Settings">
            <div class="space-y-6">
                {{-- Master Toggle --}}
                <label class="flex items-center gap-3 cursor-pointer">
                    <input type="checkbox" wire:model.live="errorSettings.enabled"
                           class="w-4 h-4 rounded border-[var(--ui-border)] text-[var(--ui-primary)] focus:ring-[var(--ui-primary)] focus:ring-offset-0">
                    <span class="text-sm font-medium text-[var(--ui-secondary)]">Error Tracking aktivieren</span>
                </label>

                {{-- Ingest Endpoint --}}
                <div>
                    <h4 class="text-sm font-semibold text-[var(--ui-secondary)] mb-3">Ingest Endpoint</h4>
                    @if($errorSettings->ingest_token)
                        <div class="space-y-2">
                            <div class="p-3 rounded-lg bg-[var(--ui-muted-5)] border border-[var(--ui-border)]/40">
                                <div class="text-xs text-[var(--ui-muted)] mb-1">URL (in .env der Deployment-Instanzen eintragen)</div>
                                <code class="text-xs text-[var(--ui-secondary)] break-all select-all">{{ $errorSettings->getIngestUrl() }}</code>
                            </div>
                            <div class="p-3 rounded-lg bg-[var(--ui-primary-5)] border border-[var(--ui-primary)]/20">
                                <div class="text-xs text-[var(--ui-muted)] mb-1">ENV Variable</div>
                                <code class="text-xs text-[var(--ui-primary)] font-bold select-all">DEV_ERROR_ENDPOINT_{{ strtoupper(str_replace('-', '_', Str::kebab(class_basename($package->name)))) }}={{ $errorSettings->getIngestUrl() }}</code>
                            </div>
                            <x-ui-button variant="danger-outline" size="sm" wire:click="generateIngestToken" wire:confirm="Token neu generieren? Alle Instanzen müssen dann aktualisiert werden.">
                                @svg('heroicon-o-arrow-path', 'w-3.5 h-3.5')
                                <span>Token neu generieren</span>
                            </x-ui-button>
                        </div>
                    @else
                        <div class="text-center py-4">
                            <p class="text-sm text-[var(--ui-muted)] mb-3">Noch kein Ingest-Token generiert. Klicke auf "Abonnieren" um eine URL zu erhalten.</p>
                            <x-ui-button variant="primary" size="sm" wire:click="generateIngestToken">
                                @svg('heroicon-o-bell', 'w-4 h-4')
                                <span>Abonnieren</span>
                            </x-ui-button>
                        </div>
                    @endif
                </div>

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
