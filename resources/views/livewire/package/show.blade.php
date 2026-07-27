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
                {{-- Health-Pille — Klick-Einstieg zur Package-Health-Detail-Sicht --}}
                @if($latestSnapshot)
                    @php
                        $hc = $latestSnapshot->health_color ?? 'gray';
                        $hs = $latestSnapshot->health_score;
                        $healthTones = [
                            'green'  => ['border' => 'border-emerald-300', 'bg' => 'bg-emerald-50',  'hover' => 'hover:bg-emerald-100', 'fg' => 'text-emerald-700', 'dot' => 'bg-emerald-500', 'label' => 'Stabil'],
                            'yellow' => ['border' => 'border-amber-300',   'bg' => 'bg-amber-50',    'hover' => 'hover:bg-amber-100',   'fg' => 'text-amber-700',   'dot' => 'bg-amber-500',   'label' => 'Achtung'],
                            'red'    => ['border' => 'border-rose-300',    'bg' => 'bg-rose-50',     'hover' => 'hover:bg-rose-100',    'fg' => 'text-rose-700',    'dot' => 'bg-rose-500',    'label' => 'Brennt'],
                            'gray'   => ['border' => 'border-zinc-300',    'bg' => 'bg-zinc-50',     'hover' => 'hover:bg-zinc-100',    'fg' => 'text-zinc-600',    'dot' => 'bg-zinc-400',    'label' => 'Keine Daten'],
                        ];
                        $t = $healthTones[$hc] ?? $healthTones['gray'];
                        $delta = $latestSnapshot->delta_health_score;
                        $trendArrow = $delta === null || $delta === 0 ? null : ($delta > 0 ? '↑' : '↓');
                        $worstAxisLabel = match($latestSnapshot->worst_axis) {
                            'bug_pressure' => 'Bug-Druck', 'feature_velocity' => 'Feature-Velocity',
                            'production_health' => 'Production', 'doc_coverage' => 'Doku',
                            default => null,
                        };
                    @endphp
                    <a href="{{ route('dev.packages.health', $package) }}"
                       wire:navigate
                       title="Snapshot {{ optional($latestSnapshot->taken_on)->format('d.m.Y') }} · Health {{ $hs ?? '–' }} ({{ $hc }}) · Confidence {{ $latestSnapshot->confidence_score }}%"
                       class="group inline-flex items-stretch h-9 rounded-lg border {{ $t['border'] }} {{ $t['bg'] }} {{ $t['hover'] }} text-[12px] {{ $t['fg'] }} font-medium overflow-hidden shadow-sm transition-all hover:shadow-md">
                        <span class="flex items-center gap-2 px-3 border-r {{ $t['border'] }}/70">
                            <span class="w-2 h-2 rounded-full {{ $t['dot'] }} animate-pulse"></span>
                            <span class="text-base font-bold tabular-nums leading-none">{{ $hs ?? '–' }}</span>
                        </span>
                        <span class="flex items-center gap-1.5 px-3">
                            <span class="text-[10px] uppercase tracking-wider opacity-70">{{ $worstAxisLabel ?? $t['label'] }}</span>
                            @if($trendArrow)
                                <span class="text-[11px] tabular-nums opacity-80">{{ $trendArrow }}{{ abs($delta) }}</span>
                            @endif
                            @svg('heroicon-o-arrow-top-right-on-square', 'w-3 h-3 opacity-50 group-hover:opacity-100 transition-opacity')
                        </span>
                    </a>
                @else
                    <a href="{{ route('dev.packages.health', $package) }}"
                       wire:navigate
                       title="Noch kein Snapshot vorhanden"
                       class="inline-flex items-center gap-1.5 px-3 h-9 rounded-lg border border-dashed border-gray-300 bg-white hover:bg-gray-50 text-[12px] text-gray-500 hover:text-gray-700 transition-colors">
                        @svg('heroicon-o-heart', 'w-4 h-4')
                        <span class="font-medium">Health</span>
                        @svg('heroicon-o-arrow-right', 'w-3 h-3 opacity-50')
                    </a>
                @endif

                <button wire:click="openErrorSettings"
                        class="inline-flex items-center gap-1.5 px-3 py-[5px] text-xs font-medium text-gray-700 bg-white hover:bg-gray-50 rounded-md border border-gray-300 transition-colors">
                    @svg('heroicon-o-bug-ant', 'w-3.5 h-3.5')
                    <span>Error Tracking</span>
                </button>
                <button wire:click="startEditingPackage"
                        class="inline-flex items-center gap-1.5 px-3 py-[5px] text-xs font-medium text-gray-700 bg-white hover:bg-gray-50 rounded-md border border-gray-300 transition-colors">
                    @svg('heroicon-o-pencil', 'w-3.5 h-3.5')
                    <span>Settings</span>
                </button>
            @endif
        </x-ui-page-actionbar>
    </x-slot>

    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Uebersicht" width="w-80" :defaultOpen="true" storeKey="sidebarOpen" side="left">
            <div class="p-5 space-y-5">
                {{-- Package Header --}}
                <div class="d-flex items-center gap-3">
                    <div class="w-9 h-9 rounded-md bg-gray-100 d-flex items-center justify-center flex-shrink-0 border border-gray-200">
                        @svg(app('safe-svg')->resolve($package->icon) ?? 'heroicon-o-cube', 'w-4 h-4 text-gray-600')
                    </div>
                    <div class="min-w-0">
                        <h3 class="text-xs font-semibold text-gray-900">{{ $package->name }}</h3>
                        <span class="inline-flex items-center gap-1.5 text-[11px] {{ $package->status === 'active' ? 'text-green-700' : 'text-gray-500' }}">
                            <span class="w-1.5 h-1.5 rounded-full {{ $package->status === 'active' ? 'bg-[#238636]' : 'bg-gray-400' }}"></span>
                            {{ $package->status === 'active' ? 'Active' : 'Archived' }}
                        </span>
                    </div>
                </div>

                @if($package->description)
                    <p class="text-xs text-gray-500 leading-relaxed">{{ $package->description }}</p>
                @endif

                {{-- Health Progress Bar --}}
                @php
                    $totalIssues = $totalOpen + $totalDone;
                    $progressPct = $totalIssues > 0 ? round($totalDone / $totalIssues * 100) : 0;
                @endphp
                @if($totalIssues > 0)
                    <div class="px-3 py-2.5 rounded-md bg-gray-50 border border-gray-200">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-[11px] font-medium text-gray-600">Progress</span>
                            <span class="text-[11px] font-semibold tabular-nums {{ $progressPct === 100 ? 'text-[#238636]' : 'text-gray-900' }}">{{ $totalDone }}/{{ $totalIssues }}</span>
                        </div>
                        <div class="w-full h-2 rounded-full bg-gray-200 overflow-hidden">
                            <div class="h-full rounded-full bg-[#238636] transition-all" style="width: {{ $progressPct }}%"></div>
                        </div>
                    </div>
                @endif

                {{-- Package Info --}}
                <div>
                    <h3 class="text-[11px] font-semibold text-gray-500 uppercase tracking-wider mb-2.5">About</h3>
                    <div class="space-y-2.5 text-xs">
                        @if($package->userInCharge)
                            <div class="flex justify-between">
                                <span class="text-gray-500">Owner</span>
                                <span class="font-medium text-gray-900">{{ $package->userInCharge->name }}</span>
                            </div>
                        @endif
                        @if($package->github_repo_full_name)
                            <div class="flex justify-between">
                                <span class="text-gray-500">Repository</span>
                                <code class="text-[11px] font-mono text-gray-700 truncate max-w-[10rem]">{{ $package->github_repo_full_name }}</code>
                            </div>
                        @endif
                        <div class="flex justify-between">
                            <span class="text-gray-500">Created</span>
                            <span class="font-medium text-gray-900 tabular-nums">{{ $package->created_at->format('d.m.Y') }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">Errors</span>
                            <span class="font-medium {{ $errorSettingsEnabled ? 'text-[#238636]' : 'text-gray-400' }}">
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
            <div class="p-5">
                <h3 class="text-[11px] font-semibold uppercase tracking-wider text-gray-500 mb-4">Recent activity</h3>
                <div class="space-y-2">
                    @forelse(($activities ?? []) as $activity)
                        <div class="px-3 py-2.5 rounded-md border border-gray-200 bg-gray-50 hover:bg-gray-100 transition-colors">
                            <div class="text-xs font-medium text-gray-900 leading-snug mb-1">
                                {{ $activity['title'] ?? 'Aktivitaet' }}
                            </div>
                            <div class="flex items-center gap-1.5 text-[11px] text-gray-500">
                                @svg('heroicon-o-clock', 'w-3 h-3')
                                <span>{{ $activity['time'] ?? '' }}</span>
                            </div>
                        </div>
                    @empty
                        <div class="py-10 text-center">
                            @svg('heroicon-o-clock', 'w-8 h-8 text-gray-300 mx-auto mb-3')
                            <p class="text-xs text-gray-500">No activity yet</p>
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
                            <h3 class="text-sm font-semibold text-gray-900">Package bearbeiten</h3>
                            <p class="text-xs text-gray-500">Name, Icon und Verantwortlichen anpassen</p>
                        </div>
                    </div>
                </x-slot>
                <div class="space-y-5">
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

                    <label class="flex items-start gap-3 cursor-pointer rounded-md border border-gray-200 p-3 hover:bg-gray-50 transition-colors">
                        <input type="checkbox" wire:model="editPackageAgentEnabled"
                               class="mt-0.5 h-4 w-4 rounded border-gray-300 text-[#238636] focus:ring-[#238636]">
                        <span>
                            <span class="block text-sm font-medium text-gray-800">🤖 Worker-tauglich</span>
                            <span class="block text-xs text-gray-500">Der autonome Worker darf Issues dieses Packages ziehen und bearbeiten.</span>
                        </span>
                    </label>
                </div>
                <x-slot name="footer">
                    <div class="flex justify-end gap-3">
                        <button wire:click="cancelEditPackage"
                                class="inline-flex items-center gap-1.5 px-3 py-[5px] text-xs font-medium text-gray-700 bg-white hover:bg-gray-50 rounded-md border border-gray-300 transition-colors">
                            Abbrechen
                        </button>
                        <button wire:click="savePackage"
                                class="inline-flex items-center gap-1.5 px-3 py-[5px] text-xs font-medium text-white bg-[#238636] hover:bg-[#2ea043] rounded-md border border-[#2ea043] transition-colors">
                            @svg('heroicon-o-check', 'w-3.5 h-3.5')
                            Speichern
                        </button>
                    </div>
                </x-slot>
            </x-ui-modal>
        @endif

        <div class="max-w-5xl mx-auto px-6 py-6">
            {{-- Lock Banner --}}
            @if($package->isLocked())
                <div class="mb-5 rounded-md border border-amber-300 bg-amber-50 px-5 py-3 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        @svg('heroicon-s-lock-closed', 'w-5 h-5 text-amber-600 flex-shrink-0')
                        <div>
                            <span class="text-xs font-semibold text-amber-900">
                                {{ $package->lockedByUser?->name ?? 'Unbekannt' }} arbeitet daran
                            </span>
                            @if($package->lock_reason)
                                <span class="text-xs text-amber-700"> &mdash; {{ $package->lock_reason }}</span>
                            @endif
                            <div class="text-[11px] text-amber-600 mt-0.5">
                                Seit {{ $package->locked_at?->diffForHumans() }}
                            </div>
                        </div>
                    </div>
                    @if($package->locked_by_user_id === auth()->id())
                        <button wire:click="unlockPackage"
                                class="inline-flex items-center gap-1.5 px-3 py-[5px] text-xs font-medium text-amber-800 bg-amber-100 hover:bg-amber-200 rounded-md border border-amber-300 transition-colors">
                            @svg('heroicon-o-lock-open', 'w-3.5 h-3.5')
                            Freigeben
                        </button>
                    @endif
                </div>
            @endif

            {{-- Repository Header --}}
            <div class="mb-5">
                <div class="d-flex items-center gap-3 mb-3">
                    <svg class="w-5 h-5 text-gray-500" viewBox="0 0 16 16" fill="currentColor"><path d="M2 2.5A2.5 2.5 0 0 1 4.5 0h8.75a.75.75 0 0 1 .75.75v12.5a.75.75 0 0 1-.75.75h-2.5a.75.75 0 0 1 0-1.5h1.75v-2h-8a1 1 0 0 0-.714 1.7.75.75 0 1 1-1.072 1.05A2.495 2.495 0 0 1 2 11.5Zm10.5-1h-8a1 1 0 0 0-1 1v6.708A2.486 2.486 0 0 1 4.5 9h8ZM5 12.25a.25.25 0 0 1 .25-.25h3.5a.25.25 0 0 1 .25.25v3.25a.25.25 0 0 1-.4.2l-1.45-1.087a.25.25 0 0 0-.3 0L5.4 15.7a.25.25 0 0 1-.4-.2Z"/></svg>
                    <h1 class="text-xl font-semibold text-gray-900">{{ $package->name }}</h1>
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium border {{ $package->status === 'active' ? 'border-gray-300 text-gray-600' : 'border-gray-200 text-gray-400' }}">
                        {{ $package->status === 'active' ? 'Public' : 'Archived' }}
                    </span>
                </div>

                {{-- Owner + Lock Button --}}
                <div class="flex items-center gap-3 mb-3">
                    @if($package->userInCharge)
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-blue-50 border border-blue-200 text-xs font-medium text-blue-800">
                            <span class="w-5 h-5 rounded-full bg-blue-200 flex items-center justify-center text-[10px] font-bold text-blue-700 flex-shrink-0">
                                {{ strtoupper(mb_substr($package->userInCharge->name, 0, 1)) }}
                            </span>
                            {{ $package->userInCharge->name }}
                            <span class="text-blue-500 font-normal">&middot; Verantwortlich</span>
                        </span>
                    @endif

                    @if(!$package->isLocked())
                        <button wire:click="lockPackage"
                                class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium text-gray-600 bg-gray-100 hover:bg-amber-50 hover:text-amber-700 border border-gray-200 hover:border-amber-300 transition-colors">
                            @svg('heroicon-o-lock-closed', 'w-3.5 h-3.5')
                            Ich arbeite daran
                        </button>
                    @endif
                </div>

                @if($package->description)
                    <p class="text-sm text-gray-600 mb-4 max-w-2xl">{{ $package->description }}</p>
                @endif

                {{-- GitHub-style Tab Navigation --}}
                <div class="border-b border-gray-200 -mx-6 px-6">
                    <nav class="flex items-center gap-1 -mb-px">
                        <a href="{{ route('dev.packages.show', $package) }}"
                           class="inline-flex items-center gap-2 px-4 py-2.5 text-xs font-medium border-b-2 border-[#f78166] text-gray-900 transition-colors">
                            <svg class="w-4 h-4" viewBox="0 0 16 16" fill="currentColor"><path d="M0 1.75A.75.75 0 0 1 .75 1h4.253c1.227 0 2.317.59 3 1.501A3.744 3.744 0 0 1 11.006 1h4.245a.75.75 0 0 1 .75.75v10.5a.75.75 0 0 1-.75.75h-4.507a2.25 2.25 0 0 0-1.591.659l-.622.621a.75.75 0 0 1-1.06 0l-.622-.621A2.25 2.25 0 0 0 5.258 13H.75a.75.75 0 0 1-.75-.75Zm7.251 10.324.004-5.073-.002-2.253A2.25 2.25 0 0 0 5.003 2.5H1.5v9h3.757a3.75 3.75 0 0 1 1.994.574ZM8.755 4.75l-.004 7.322a3.752 3.752 0 0 1 1.992-.572H14.5v-9h-3.495a2.25 2.25 0 0 0-2.25 2.25Z"/></svg>
                            Overview
                        </a>
                        @foreach($boards as $board)
                            <a href="{{ route('dev.packages.boards.show', [$package, $board]) }}"
                               wire:navigate
                               class="inline-flex items-center gap-2 px-4 py-2.5 text-xs font-medium border-b-2 border-transparent text-gray-600 hover:text-gray-900 hover:border-gray-300 transition-colors">
                                @if($board->type->value === 'bug')
                                    @svg('heroicon-o-bug-ant', 'w-4 h-4 text-red-500')
                                @elseif($board->type->value === 'feature')
                                    @svg('heroicon-o-light-bulb', 'w-4 h-4 text-blue-500')
                                @else
                                    @svg('heroicon-o-view-columns', 'w-4 h-4')
                                @endif
                                {{ $board->name }}
                                @if($board->open_issues_count > 0)
                                    <span class="px-1.5 py-0.5 text-[10px] font-medium rounded-full bg-neutral-200/80 text-gray-600 tabular-nums leading-none">{{ $board->open_issues_count }}</span>
                                @endif
                            </a>
                        @endforeach
                        <a href="{{ route('dev.packages.docs', $package) }}"
                           wire:navigate
                           class="inline-flex items-center gap-2 px-4 py-2.5 text-xs font-medium border-b-2 border-transparent text-gray-600 hover:text-gray-900 hover:border-gray-300 transition-colors">
                            @svg('heroicon-o-book-open', 'w-4 h-4')
                            Docs
                            @if($docPageCount > 0)
                                <span class="px-1.5 py-0.5 text-[10px] font-medium rounded-full bg-neutral-200/80 text-gray-600 tabular-nums leading-none">{{ $docPageCount }}</span>
                            @endif
                        </a>
                    </nav>
                </div>
            </div>

            {{-- Error Occurrences --}}
            @if($errorSettingsEnabled && $errorOccurrences->count() > 0)
                <div class="bg-white rounded-md border border-red-200 overflow-hidden mb-5">
                    <div class="flex items-center justify-between px-5 py-3 border-b border-red-200 bg-red-50">
                        <div class="d-flex items-center gap-2">
                            <span class="relative flex h-2 w-2">
                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-500 opacity-75"></span>
                                <span class="relative inline-flex rounded-full h-2 w-2 bg-red-500"></span>
                            </span>
                            <h3 class="text-xs font-semibold text-red-800">Security alerts</h3>
                            <span class="px-2 py-0.5 text-[11px] font-medium rounded-full bg-red-100 text-red-700 tabular-nums">{{ $errorOccurrences->count() }}</span>
                        </div>
                    </div>
                    <div>
                        @foreach($errorOccurrences as $occurrence)
                            <div class="px-5 py-3 d-flex items-start gap-3 group hover:bg-gray-50 transition-colors border-b border-gray-100 last:border-b-0">
                                <div class="flex-shrink-0 mt-0.5">
                                    @if($occurrence->http_code >= 500)
                                        @svg('heroicon-s-exclamation-triangle', 'w-4 h-4 text-red-500')
                                    @else
                                        @svg('heroicon-o-exclamation-circle', 'w-4 h-4 text-yellow-500')
                                    @endif
                                </div>
                                <div class="min-w-0 flex-grow-1">
                                    <div class="text-xs font-medium text-gray-900 truncate">
                                        @if($occurrence->http_code)
                                            <code class="text-[11px] px-1.5 py-0.5 font-mono bg-red-50 text-red-700 rounded mr-1">{{ $occurrence->http_code }}</code>
                                        @endif
                                        {{ $occurrence->getShortExceptionClass() }}
                                    </div>
                                    <div class="text-[11px] text-gray-500 mt-0.5 truncate">{{ Str::limit($occurrence->message, 100) }}</div>
                                    <div class="text-[11px] text-gray-400 mt-0.5 font-mono">{{ Str::afterLast($occurrence->file ?? '', '/') }}:{{ $occurrence->line }}</div>
                                </div>
                                <div class="flex-shrink-0 text-right">
                                    <div class="text-[11px] text-gray-400">{{ $occurrence->last_seen_at?->diffForHumans() }}</div>
                                    @if($occurrence->occurrence_count > 1)
                                        <div class="text-[11px] font-medium text-red-600 tabular-nums">{{ $occurrence->occurrence_count }}x</div>
                                    @endif
                                </div>
                                <div class="flex-shrink-0 d-flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                    <button wire:click="resolveOccurrence({{ $occurrence->id }})" class="p-1 rounded hover:bg-green-50 text-gray-400 hover:text-[#238636] transition-colors" title="Resolve">
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

            {{-- Stats Row --}}
            <div class="d-flex items-center gap-6 mb-6 pb-6 border-b border-gray-200">
                <div class="d-flex items-center gap-2">
                    <svg class="w-4 h-4 text-[#238636]" viewBox="0 0 16 16" fill="currentColor"><path d="M8 9.5a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3Z"/><path d="M8 0a8 8 0 1 1 0 16A8 8 0 0 1 8 0ZM1.5 8a6.5 6.5 0 1 0 13 0 6.5 6.5 0 0 0-13 0Z"/></svg>
                    <span class="text-xs font-medium text-gray-900 tabular-nums">{{ $totalOpen }} open</span>
                </div>
                <div class="d-flex items-center gap-2">
                    <svg class="w-4 h-4 text-purple-500" viewBox="0 0 16 16" fill="currentColor"><path d="M11.28 6.78a.75.75 0 0 0-1.06-1.06L7.25 8.69 5.78 7.22a.75.75 0 0 0-1.06 1.06l2 2a.75.75 0 0 0 1.06 0l3.5-3.5Z"/><path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0Zm-1.5 0a6.5 6.5 0 1 0-13 0 6.5 6.5 0 0 0 13 0Z"/></svg>
                    <span class="text-xs font-medium text-gray-900 tabular-nums">{{ $totalDone }} closed</span>
                </div>
                @if($totalHighPriority > 0)
                    <div class="d-flex items-center gap-1.5">
                        <span class="w-2 h-2 rounded-full bg-red-500"></span>
                        <span class="text-xs font-medium text-red-700 tabular-nums">{{ $totalHighPriority }} high priority</span>
                    </div>
                @endif
                @if($totalOverdue > 0)
                    <div class="d-flex items-center gap-1.5">
                        @svg('heroicon-o-clock', 'w-3.5 h-3.5 text-yellow-600')
                        <span class="text-xs font-medium text-yellow-700 tabular-nums">{{ $totalOverdue }} overdue</span>
                    </div>
                @endif
            </div>

            {{-- Boards Grid --}}
            <div class="mb-6">
                <div class="d-flex items-center justify-between mb-4">
                    <h2 class="text-sm font-semibold text-gray-900">Boards</h2>
                    <button wire:click="$set('showCreateBoardModal', true)"
                            class="inline-flex items-center gap-1.5 px-3 py-[5px] text-xs font-medium text-white bg-[#238636] hover:bg-[#2ea043] rounded-md border border-[#2ea043] transition-colors">
                        @svg('heroicon-o-plus', 'w-3.5 h-3.5')
                        New board
                    </button>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    @foreach($boards as $board)
                        @php
                            $boardDone = $board->done_issues_count;
                            $boardTotal = $board->open_issues_count + $boardDone;
                            $boardPct = $boardTotal > 0 ? round($boardDone / $boardTotal * 100) : 0;
                            $isBug = $board->type->value === 'bug';
                            $isFeature = $board->type->value === 'feature';
                        @endphp
                        <a href="{{ route('dev.packages.boards.show', [$package, $board]) }}"
                           wire:navigate
                           class="group block p-5 rounded-md border border-gray-200 bg-white hover:border-gray-300 transition-colors">
                            <div class="d-flex items-center gap-3 mb-1">
                                <div class="flex-shrink-0 w-8 h-8 rounded-md d-flex items-center justify-center {{ $isBug ? 'bg-red-50' : ($isFeature ? 'bg-blue-50' : 'bg-gray-100') }}">
                                    @if($isBug)
                                        @svg('heroicon-o-bug-ant', 'w-4 h-4 text-red-500')
                                    @elseif($isFeature)
                                        @svg('heroicon-o-light-bulb', 'w-4 h-4 text-blue-500')
                                    @else
                                        @svg('heroicon-o-view-columns', 'w-4 h-4 text-gray-500')
                                    @endif
                                </div>
                                <div class="min-w-0 flex-grow-1">
                                    <div class="text-xs font-semibold text-gray-900 truncate group-hover:text-blue-600 transition-colors">{{ $board->name }}</div>
                                    <div class="text-[11px] text-gray-500">
                                        <span class="capitalize {{ $isBug ? 'text-red-600' : ($isFeature ? 'text-blue-600' : '') }}">{{ $board->type->value }}</span>
                                        &middot; {{ $board->open_issues_count }} open
                                    </div>
                                </div>
                            </div>
                            @if($boardTotal > 0)
                                <div class="d-flex items-center gap-2.5 mt-3">
                                    <div class="flex-grow-1 h-[6px] rounded-full bg-gray-200 overflow-hidden">
                                        <div class="h-full rounded-full {{ $boardPct === 100 ? 'bg-[#238636]' : ($isBug ? 'bg-red-400' : 'bg-blue-400') }} transition-all" style="width: {{ $boardPct }}%"></div>
                                    </div>
                                    <span class="text-[11px] font-semibold tabular-nums {{ $boardPct === 100 ? 'text-[#238636]' : 'text-gray-500' }} flex-shrink-0 w-8 text-right">{{ $boardPct }}%</span>
                                </div>
                            @endif
                        </a>
                    @endforeach
                </div>

                {{-- Archived Boards --}}
                @if($archivedBoards->isNotEmpty())
                    <div x-data="{ showArchived: false }" class="mt-3">
                        <button @click="showArchived = !showArchived" class="text-[11px] text-gray-500 hover:text-gray-700 transition-colors d-flex items-center gap-1">
                            @svg('heroicon-o-archive-box', 'w-3 h-3')
                            {{ $archivedBoards->count() }} archived
                            <template x-if="!showArchived">@svg('heroicon-o-chevron-right', 'w-3 h-3')</template>
                            <template x-if="showArchived">@svg('heroicon-o-chevron-down', 'w-3 h-3')</template>
                        </button>
                        <div x-show="showArchived" x-collapse class="mt-2 d-flex items-center gap-2 flex-wrap">
                            @foreach($archivedBoards as $archivedBoard)
                                <div class="d-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-gray-100 text-[11px] text-gray-500 border border-gray-200">
                                    @svg('heroicon-o-archive-box', 'w-3 h-3')
                                    {{ $archivedBoard->name }}
                                    <button wire:click="reactivateBoard({{ $archivedBoard->id }})" class="p-0.5 rounded-full hover:bg-green-50 hover:text-[#238636] transition-colors" title="Reaktivieren">
                                        @svg('heroicon-o-arrow-path', 'w-3 h-3')
                                    </button>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            {{-- Commits & PRs --}}
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
                {{-- Letzte Commits --}}
                <div class="lg:col-span-2 bg-white rounded-md border border-gray-200 overflow-hidden">
                    <div class="flex items-center gap-2 px-5 py-3 border-b border-gray-200 bg-gray-50">
                        <svg class="w-4 h-4 text-gray-500" viewBox="0 0 16 16" fill="currentColor"><path d="M11.93 8.5a4.002 4.002 0 0 1-7.86 0H.75a.75.75 0 0 1 0-1.5h3.32a4.002 4.002 0 0 1 7.86 0h3.32a.75.75 0 0 1 0 1.5Zm-1.43-.75a2.5 2.5 0 1 0-5 0 2.5 2.5 0 0 0 5 0Z"/></svg>
                        <h3 class="text-xs font-semibold text-gray-900">Commits</h3>
                        @if($recentCommits->isNotEmpty())
                            <span class="px-2 py-0.5 text-[11px] font-medium rounded-full bg-neutral-200/80 text-gray-600 tabular-nums">{{ $recentCommits->count() }}</span>
                        @endif
                    </div>
                    <div>
                        @forelse($recentCommits as $commit)
                            <div class="d-flex items-start gap-3 px-5 py-3 hover:bg-gray-50 transition-colors group border-b border-gray-100 last:border-b-0">
                                <div class="flex-shrink-0 d-flex flex-col items-center mt-1" style="width: 12px;">
                                    <div class="w-2.5 h-2.5 rounded-full border-2 border-[#238636] bg-white"></div>
                                    @if(!$loop->last)
                                        <div class="w-px flex-grow-1 bg-gray-200 mt-0.5" style="min-height: 20px;"></div>
                                    @endif
                                </div>
                                <div class="min-w-0 flex-grow-1">
                                    <div class="text-xs text-gray-900 truncate group-hover:text-blue-600 transition-colors">{{ Str::limit(Str::before($commit->message, "\n"), 80) }}</div>
                                    <div class="text-[11px] text-gray-500 mt-0.5 d-flex items-center gap-1.5">
                                        <span class="font-medium text-gray-700">{{ $commit->author_login ?? $commit->author_name }}</span>
                                        <span>&middot;</span>
                                        <code class="px-1.5 py-0.5 text-[10px] font-mono bg-gray-100 text-gray-600 rounded tabular-nums">{{ Str::limit($commit->sha, 7, '') }}</code>
                                    </div>
                                </div>
                                <div class="flex-shrink-0 text-[11px] text-gray-400 whitespace-nowrap">
                                    {{ $commit->committed_at?->diffForHumans() }}
                                </div>
                            </div>
                        @empty
                            <div class="p-12 text-center">
                                @if($package->github_repo_full_name)
                                    <svg class="w-8 h-8 text-gray-300 mx-auto mb-3" viewBox="0 0 16 16" fill="currentColor"><path d="M11.93 8.5a4.002 4.002 0 0 1-7.86 0H.75a.75.75 0 0 1 0-1.5h3.32a4.002 4.002 0 0 1 7.86 0h3.32a.75.75 0 0 1 0 1.5Zm-1.43-.75a2.5 2.5 0 1 0-5 0 2.5 2.5 0 0 0 5 0Z"/></svg>
                                    <p class="text-xs font-medium text-gray-900 mb-1">No commits yet</p>
                                    <p class="text-[11px] text-gray-500">Commits sync automatically every hour.</p>
                                @else
                                    @svg('heroicon-o-link-slash', 'w-8 h-8 text-gray-300 mx-auto mb-3')
                                    <p class="text-xs text-gray-500">No GitHub repository linked.</p>
                                @endif
                            </div>
                        @endforelse
                    </div>
                </div>

                {{-- Open Pull Requests --}}
                <div class="bg-white rounded-md border border-gray-200 overflow-hidden">
                    <div class="flex items-center gap-2 px-5 py-3 border-b border-gray-200 bg-gray-50">
                        <svg class="w-4 h-4 text-[#238636]" viewBox="0 0 16 16" fill="currentColor"><path d="M1.5 3.25a2.25 2.25 0 1 1 3 2.122v5.256a2.251 2.251 0 1 1-1.5 0V5.372A2.25 2.25 0 0 1 1.5 3.25Zm5.677-.177L9.573.677A.25.25 0 0 1 10 .854V2.5h1A2.5 2.5 0 0 1 13.5 5v5.628a2.251 2.251 0 1 1-1.5 0V5a1 1 0 0 0-1-1h-1v1.646a.25.25 0 0 1-.427.177L7.177 3.427a.25.25 0 0 1 0-.354ZM3.75 2.5a.75.75 0 1 0 0 1.5.75.75 0 0 0 0-1.5Zm0 9.5a.75.75 0 1 0 0 1.5.75.75 0 0 0 0-1.5Zm8.25.75a.75.75 0 1 0 1.5 0 .75.75 0 0 0-1.5 0Z"/></svg>
                        <h3 class="text-xs font-semibold text-gray-900">Pull requests</h3>
                        @if($openPullRequests->isNotEmpty())
                            <span class="px-2 py-0.5 text-[11px] font-medium rounded-full bg-green-100 text-green-700 tabular-nums">{{ $openPullRequests->count() }}</span>
                        @endif
                    </div>
                    <div>
                        @forelse($openPullRequests as $pr)
                            <div class="px-5 py-3 hover:bg-gray-50 transition-colors border-b border-gray-100 last:border-b-0">
                                <div class="d-flex items-start gap-2.5">
                                    <div class="flex-shrink-0 mt-0.5">
                                        @if($pr->is_draft)
                                            <div class="w-4 h-4 rounded-full border-2 border-dashed border-gray-400 d-flex items-center justify-center">
                                                <div class="w-1.5 h-1.5 rounded-full bg-gray-400"></div>
                                            </div>
                                        @else
                                            <svg class="w-4 h-4 text-[#238636]" viewBox="0 0 16 16" fill="currentColor"><path d="M1.5 3.25a2.25 2.25 0 1 1 3 2.122v5.256a2.251 2.251 0 1 1-1.5 0V5.372A2.25 2.25 0 0 1 1.5 3.25Zm5.677-.177L9.573.677A.25.25 0 0 1 10 .854V2.5h1A2.5 2.5 0 0 1 13.5 5v5.628a2.251 2.251 0 1 1-1.5 0V5a1 1 0 0 0-1-1h-1v1.646a.25.25 0 0 1-.427.177L7.177 3.427a.25.25 0 0 1 0-.354ZM3.75 2.5a.75.75 0 1 0 0 1.5.75.75 0 0 0 0-1.5Zm0 9.5a.75.75 0 1 0 0 1.5.75.75 0 0 0 0-1.5Zm8.25.75a.75.75 0 1 0 1.5 0 .75.75 0 0 0-1.5 0Z"/></svg>
                                        @endif
                                    </div>
                                    <div class="min-w-0">
                                        <div class="text-xs font-medium text-gray-900 truncate hover:text-blue-600">{{ $pr->title }}</div>
                                        <div class="text-[11px] text-gray-500 mt-0.5">
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
                            <div class="p-10 text-center">
                                @svg('heroicon-o-check-circle', 'w-6 h-6 text-[#238636] mx-auto mb-2')
                                <p class="text-[11px] text-gray-500">No open PRs.</p>
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>

            {{-- Issues + Recently Closed --}}
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                {{-- Open Issues --}}
                <div class="bg-white rounded-md border border-gray-200 overflow-hidden">
                    <div class="flex items-center gap-2 px-5 py-3 border-b border-gray-200 bg-gray-50">
                        <svg class="w-4 h-4 text-[#238636]" viewBox="0 0 16 16" fill="currentColor"><path d="M8 9.5a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3Z"/><path d="M8 0a8 8 0 1 1 0 16A8 8 0 0 1 8 0ZM1.5 8a6.5 6.5 0 1 0 13 0 6.5 6.5 0 0 0-13 0Z"/></svg>
                        <h3 class="text-xs font-semibold text-gray-900">Open issues</h3>
                        @if($recentIssues->isNotEmpty())
                            <span class="px-2 py-0.5 text-[11px] font-medium rounded-full bg-neutral-200/80 text-gray-600 tabular-nums">{{ $recentIssues->count() }}</span>
                        @endif
                    </div>
                    <div>
                        @forelse($recentIssues as $issue)
                            <a href="{{ route('dev.packages.issues.show', [$package, $issue]) }}"
                               wire:navigate
                               class="flex items-center gap-3 px-5 py-4 hover:bg-gray-50 transition-colors border-b border-gray-100 last:border-b-0">
                                <div class="flex-shrink-0">
                                    @if($issue->priority === 'high')
                                        <span class="w-2 h-2 rounded-full bg-red-500 inline-block"></span>
                                    @else
                                        <svg class="w-4 h-4 text-[#238636]" viewBox="0 0 16 16" fill="currentColor"><path d="M8 9.5a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3Z"/><path d="M8 0a8 8 0 1 1 0 16A8 8 0 0 1 8 0ZM1.5 8a6.5 6.5 0 1 0 13 0 6.5 6.5 0 0 0-13 0Z"/></svg>
                                    @endif
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="text-xs font-medium text-gray-900 truncate hover:text-blue-600">{{ $issue->title }}</div>
                                    <div class="text-[11px] text-gray-500 mt-0.5">
                                        {{ $issue->board->name }}
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
                            <div class="p-10 text-center">
                                @svg('heroicon-o-check-circle', 'w-6 h-6 text-[#238636] mx-auto mb-2')
                                <p class="text-xs text-gray-500">No open issues.</p>
                            </div>
                        @endforelse
                    </div>
                </div>

                {{-- Recently Closed --}}
                <div class="bg-white rounded-md border border-gray-200 overflow-hidden">
                    <div class="flex items-center gap-2 px-5 py-3 border-b border-gray-200 bg-gray-50">
                        <svg class="w-4 h-4 text-purple-500" viewBox="0 0 16 16" fill="currentColor"><path d="M11.28 6.78a.75.75 0 0 0-1.06-1.06L7.25 8.69 5.78 7.22a.75.75 0 0 0-1.06 1.06l2 2a.75.75 0 0 0 1.06 0l3.5-3.5Z"/><path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0Zm-1.5 0a6.5 6.5 0 1 0-13 0 6.5 6.5 0 0 0 13 0Z"/></svg>
                        <h3 class="text-xs font-semibold text-gray-900">Recently closed</h3>
                        @if($recentlyDone->isNotEmpty())
                            <span class="px-2 py-0.5 text-[11px] font-medium rounded-full bg-purple-100 text-purple-700 tabular-nums">{{ $recentlyDone->count() }}</span>
                        @endif
                    </div>
                    <div>
                        @forelse($recentlyDone as $issue)
                            <a href="{{ route('dev.packages.issues.show', [$package, $issue]) }}"
                               wire:navigate
                               class="flex items-center gap-3 px-5 py-4 hover:bg-gray-50 transition-colors border-b border-gray-100 last:border-b-0">
                                <div class="flex-shrink-0">
                                    <svg class="w-4 h-4 text-purple-500" viewBox="0 0 16 16" fill="currentColor"><path d="M11.28 6.78a.75.75 0 0 0-1.06-1.06L7.25 8.69 5.78 7.22a.75.75 0 0 0-1.06 1.06l2 2a.75.75 0 0 0 1.06 0l3.5-3.5Z"/><path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0Zm-1.5 0a6.5 6.5 0 1 0-13 0 6.5 6.5 0 0 0 13 0Z"/></svg>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="text-xs text-gray-400 line-through truncate">{{ $issue->title }}</div>
                                    <div class="text-[11px] text-gray-500 mt-0.5">
                                        {{ $issue->board->name }}
                                        @if($issue->done_at)
                                            &middot; {{ $issue->done_at->diffForHumans() }}
                                        @endif
                                    </div>
                                </div>
                            </a>
                        @empty
                            <div class="p-8 text-center">
                                <p class="text-[11px] text-gray-500">Nothing closed yet.</p>
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>

        </div>
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
                        <h3 class="text-sm font-semibold text-gray-900">Neues Board</h3>
                        <p class="text-xs text-gray-500">Feature Board erstellen</p>
                    </div>
                </div>
            </x-slot>

            <div class="space-y-5">
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
                            class="inline-flex items-center gap-1.5 px-3 py-[5px] text-xs font-medium text-gray-700 bg-white hover:bg-gray-50 rounded-md border border-gray-300 transition-colors">
                        Abbrechen
                    </button>
                    <button wire:click="createBoard"
                            class="inline-flex items-center gap-1.5 px-3 py-[5px] text-xs font-medium text-white bg-[#238636] hover:bg-[#2ea043] rounded-md border border-[#2ea043] transition-colors">
                        @svg('heroicon-o-plus', 'w-3.5 h-3.5')
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
                        <h3 class="text-sm font-semibold text-gray-900">Error Tracking Settings</h3>
                        <p class="text-xs text-gray-500">Fehler-Erfassung fuer {{ $package->name }}</p>
                    </div>
                </div>
            </x-slot>

            <div class="space-y-5">
                {{-- Master Toggle --}}
                <label class="flex items-center gap-3 cursor-pointer">
                    <input type="checkbox" wire:model.live="errorSettings.enabled"
                           class="w-4 h-4 rounded border-gray-300 text-[#238636] focus:ring-green-500 focus:ring-offset-0">
                    <span class="text-xs font-medium text-gray-900">Errors fuer dieses Package empfangen</span>
                </label>

                {{-- HTTP Codes --}}
                <div>
                    <h4 class="text-xs font-semibold text-gray-900 mb-3">HTTP Status Codes</h4>
                    <div class="flex items-center gap-2 flex-wrap">
                        @foreach($availableHttpCodes as $code)
                            <button type="button"
                                    wire:click="toggleHttpCode({{ $code }})"
                                    class="px-2.5 py-1 rounded-full text-[11px] font-medium transition-colors border {{ $this->isHttpCodeEnabled($code) ? 'bg-[#238636] text-white border-[#2ea043]' : 'bg-gray-50 text-gray-600 border-gray-300 hover:bg-gray-100' }}">
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
                               class="w-4 h-4 rounded border-gray-300 text-[#238636] focus:ring-green-500 focus:ring-offset-0">
                        <span class="text-xs text-gray-700">Console/Scheduler Errors erfassen</span>
                    </label>

                    <label class="flex items-center gap-3 cursor-pointer">
                        <input type="checkbox" wire:model.live="errorSettings.auto_create_issue"
                               class="w-4 h-4 rounded border-gray-300 text-[#238636] focus:ring-green-500 focus:ring-offset-0">
                        <span class="text-xs text-gray-700">Issues automatisch erstellen (Bug-Board)</span>
                    </label>

                    <label class="flex items-center gap-3 cursor-pointer">
                        <input type="checkbox" wire:model.live="errorSettings.include_stack_trace"
                               class="w-4 h-4 rounded border-gray-300 text-[#238636] focus:ring-green-500 focus:ring-offset-0">
                        <span class="text-xs text-gray-700">Stack Trace erfassen</span>
                    </label>

                    @if($errorSettings->include_stack_trace)
                        <x-ui-input-text wire:model="errorSettings.stack_trace_limit" label="Stack Trace Limit (Frames)" type="number" min="1" max="200" />
                    @endif
                </div>
            </div>
            <x-slot name="footer">
                <div class="flex justify-end gap-3">
                    <button wire:click="$set('showErrorSettings', false)"
                            class="inline-flex items-center gap-1.5 px-3 py-[5px] text-xs font-medium text-gray-700 bg-white hover:bg-gray-50 rounded-md border border-gray-300 transition-colors">
                        Abbrechen
                    </button>
                    <button wire:click="saveErrorSettings"
                            class="inline-flex items-center gap-1.5 px-3 py-[5px] text-xs font-medium text-white bg-[#238636] hover:bg-[#2ea043] rounded-md border border-[#2ea043] transition-colors">
                        @svg('heroicon-o-check', 'w-3.5 h-3.5')
                        Speichern
                    </button>
                </div>
            </x-slot>
        </x-ui-modal>
    @endif
</x-ui-page>
