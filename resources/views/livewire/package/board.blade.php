<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="{{ $board->name }}" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Dev', 'href' => route('dev.dashboard'), 'icon' => 'code-bracket'],
            ['label' => $package->name, 'href' => route('dev.packages.show', $package)],
            ['label' => $board->name],
        ]">
            <x-slot name="left">
                {{-- Board Switcher Dropdown --}}
                <div x-data="{ open: false }" class="relative">
                    <button @click="open = !open" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium text-gray-700 border border-gray-300 rounded-md hover:bg-gray-50 transition-colors">
                        @if($board->type->value === 'bug')
                            @svg('heroicon-o-bug-ant', 'w-4 h-4 text-red-500')
                        @else
                            @svg('heroicon-o-light-bulb', 'w-4 h-4 text-blue-500')
                        @endif
                        {{ $board->name }}
                        @svg('heroicon-o-chevron-down', 'w-3 h-3 text-gray-400')
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
                        class="absolute left-0 mt-1 w-64 rounded-md bg-white border border-gray-200 z-50 py-1 top-full"
                        style="display: none;"
                    >
                        @foreach($allBoards as $b)
                            <a href="{{ route('dev.packages.boards.show', [$package, $b]) }}"
                               wire:navigate
                               @click="open = false"
                               class="flex items-center gap-2.5 px-3 py-2 text-sm transition-colors {{ $b->id === $board->id ? 'bg-blue-50 text-blue-700 font-medium' : 'text-gray-700 hover:bg-gray-50' }}">
                                @if($b->id === $board->id)
                                    @svg('heroicon-o-check', 'w-4 h-4 flex-shrink-0')
                                @elseif($b->type->value === 'bug')
                                    @svg('heroicon-o-bug-ant', 'w-4 h-4 text-red-500 flex-shrink-0')
                                @else
                                    @svg('heroicon-o-light-bulb', 'w-4 h-4 text-blue-500 flex-shrink-0')
                                @endif
                                <span class="flex-grow-1 truncate">{{ $b->name }}</span>
                                <span class="px-2 py-0.5 text-xs font-medium rounded-full bg-gray-100 text-gray-600">{{ $b->open_issues_count }}</span>
                            </a>
                        @endforeach
                    </div>
                </div>
            </x-slot>
            {{-- Board Actions --}}
            <div x-data="{ open: false }" class="relative inline-flex">
                <button wire:click="openBoardSettings"
                        class="inline-flex items-center px-2.5 py-1.5 text-sm text-gray-700 bg-gray-50 hover:bg-gray-100 rounded-l-md border border-gray-300 border-r-0 transition-colors">
                    @svg('heroicon-o-cog-6-tooth', 'w-4 h-4')
                </button>
                <button
                    @click="open = !open"
                    class="inline-flex items-center px-1.5 py-1.5 border border-gray-300 rounded-r-md hover:bg-gray-50 transition-colors"
                >
                    @svg('heroicon-o-chevron-down', 'w-3 h-3 text-gray-500')
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
                    class="absolute right-0 mt-1 w-48 rounded-md bg-white border border-gray-200 z-50 py-1 top-full"
                    style="display: none;"
                >
                    <button wire:click="openBoardSettings" @click="open = false"
                            class="flex items-center gap-2 w-full px-3 py-2 text-sm text-gray-700 hover:bg-gray-50 transition-colors text-left">
                        @svg('heroicon-o-pencil', 'w-4 h-4 text-gray-400')
                        Board umbenennen
                    </button>
                    <button wire:click="createBoardSlot" @click="open = false"
                            class="flex items-center gap-2 w-full px-3 py-2 text-sm text-gray-700 hover:bg-gray-50 transition-colors text-left">
                        @svg('heroicon-o-square-2-stack', 'w-4 h-4 text-gray-400')
                        Spalte hinzufuegen
                    </button>
                    <div class="border-t border-gray-200 my-1"></div>
                    <button wire:click="archiveBoard" wire:confirm="Board wirklich archivieren? Issues bleiben erhalten."
                            @click="open = false"
                            class="flex items-center gap-2 w-full px-3 py-2 text-sm text-red-600 hover:bg-red-50 transition-colors text-left">
                        @svg('heroicon-o-archive-box', 'w-4 h-4')
                        Board archivieren
                    </button>
                </div>
            </div>
        </x-ui-page-actionbar>
    </x-slot>

    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Board-Uebersicht" width="w-80" :defaultOpen="true" side="left">
            <div class="p-6 space-y-6">
                {{-- Board-Info --}}
                <div>
                    <h3 class="text-base font-semibold text-gray-900 mb-1">{{ $board->name }}</h3>
                    <div class="text-sm text-gray-500">{{ $board->description ?? 'Keine Beschreibung' }}</div>
                </div>

                {{-- Ansicht --}}
                <div>
                    <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-3">Ansicht</h3>
                    <div class="space-y-2">
                        <label class="flex items-center gap-3 cursor-pointer">
                            <input
                                type="checkbox"
                                wire:click="toggleShowDone"
                                @if($showDone) checked @endif
                                class="w-4 h-4 rounded border-gray-300 text-green-600 focus:ring-green-500 focus:ring-offset-0"
                            >
                            <span class="text-sm text-gray-700">Erledigte Issues anzeigen</span>
                        </label>
                    </div>
                </div>

                {{-- Statistiken --}}
                <div>
                    <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-3">Statistiken</h3>
                    <div class="grid grid-cols-2 gap-2">
                        @php
                            $statsOpen = $groups->filter(fn($g) => !($g->isDoneGroup ?? false))->sum(fn($g) => $g->tasks->count());
                            $statsTotal = $groups->flatMap(fn($g) => $g->tasks)->count();
                            $statsDone = $groups->filter(fn($g) => $g->isDoneGroup ?? false)->sum(fn($g) => $g->tasks->count());
                            $statsNoDue = $groups->flatMap(fn($g) => $g->tasks)->filter(fn($t) => !$t->due_date)->count();
                            $statsHigh = $groups->flatMap(fn($g) => $g->tasks)->filter(fn($t) => ($t->priority instanceof \BackedEnum ? $t->priority->value : $t->priority) === 'high')->count();
                            $statsOverdue = $groups->flatMap(fn($g) => $g->tasks)->filter(fn($t) => $t->due_date && $t->due_date->isPast() && !$t->is_done)->count();
                        @endphp
                        <div class="px-3 py-2 rounded-md bg-gray-50 border border-gray-200">
                            <div class="text-xs text-gray-500">Open</div>
                            <div class="text-lg font-semibold text-gray-900">{{ $statsOpen }}</div>
                        </div>
                        <div class="px-3 py-2 rounded-md bg-gray-50 border border-gray-200">
                            <div class="text-xs text-gray-500">Total</div>
                            <div class="text-lg font-semibold text-gray-900">{{ $statsTotal }}</div>
                        </div>
                        <div class="px-3 py-2 rounded-md bg-green-50 border border-green-200">
                            <div class="text-xs text-gray-500">Done</div>
                            <div class="text-lg font-semibold text-green-600">{{ $statsDone }}</div>
                        </div>
                        <div class="px-3 py-2 rounded-md bg-gray-50 border border-gray-200">
                            <div class="text-xs text-gray-500">No due</div>
                            <div class="text-lg font-semibold text-gray-900">{{ $statsNoDue }}</div>
                        </div>
                        @if($statsHigh > 0)
                        <div class="px-3 py-2 rounded-md bg-red-50 border border-red-200">
                            <div class="text-xs text-gray-500">High</div>
                            <div class="text-lg font-semibold text-red-600">{{ $statsHigh }}</div>
                        </div>
                        @endif
                        @if($statsOverdue > 0)
                        <div class="px-3 py-2 rounded-md bg-red-50 border border-red-200">
                            <div class="text-xs text-gray-500">Overdue</div>
                            <div class="text-lg font-semibold text-red-600">{{ $statsOverdue }}</div>
                        </div>
                        @endif
                    </div>
                </div>

                {{-- Erledigte Issues --}}
                @php $completedIssues = $groups->filter(fn($g) => $g->isDoneGroup ?? false)->flatMap(fn($g) => $g->tasks); @endphp
                @if($completedIssues->count() > 0)
                    <div>
                        <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-3">Closed ({{ $completedIssues->count() }})</h4>
                        <div class="space-y-1 max-h-60 overflow-y-auto">
                            @foreach($completedIssues->take(10) as $issue)
                                <a href="{{ route('dev.packages.issues.show', [$package, $issue]) }}" class="block px-3 py-2 rounded-md text-sm border border-gray-200 bg-gray-50 hover:bg-blue-50 hover:border-blue-200 transition" wire:navigate>
                                    <div class="d-flex items-center gap-2">
                                        @svg('heroicon-o-check-circle', 'w-4 h-4 text-purple-500')
                                        <span class="truncate text-gray-700">{{ $issue->title }}</span>
                                    </div>
                                </a>
                            @endforeach
                            @if($completedIssues->count() > 10)
                                <div class="text-xs text-gray-400 italic text-center">+{{ $completedIssues->count() - 10 }} more</div>
                            @endif
                        </div>
                    </div>
                @else
                    <div class="text-sm text-gray-400 italic">No closed issues yet</div>
                @endif
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

    <!-- Kanban-Board -->
    <x-ui-kanban-container class="h-full" sortable="updateSlotOrder" sortable-group="updateIssueOrder">
        @foreach($groups->filter(fn ($g) => !($g->isDoneGroup ?? false)) as $column)
            <x-ui-kanban-column :sortable-id="$column->id" :scrollable="true">
                <x-slot name="title">
                    <span class="flex items-center gap-1.5">
                        {{ $column->label ?? $column->name ?? 'Spalte' }}
                        <span class="px-2 py-0.5 text-xs font-medium rounded-full bg-gray-200 text-gray-600">({{ $column->tasks->count() }})</span>
                    </span>
                </x-slot>
                <x-slot name="headerActions">
                    <button
                        wire:click="createIssue('{{ $column->id }}')"
                        class="p-1 rounded bg-green-50 text-green-600 hover:bg-green-100 transition-colors"
                        title="Neues Issue">
                        @svg('heroicon-o-plus', 'w-4 h-4')
                    </button>
                    <button
                        wire:click="openSlotSettings({{ $column->id }})"
                        class="p-1 rounded text-gray-400 hover:text-gray-700 opacity-0 group-hover:opacity-100 transition-all"
                        title="Spalte bearbeiten">
                        @svg('heroicon-o-cog-6-tooth', 'w-4 h-4')
                    </button>
                </x-slot>

                @foreach($column->tasks as $issue)
                    @include('dev::livewire.package.partials.issue-card', ['issue' => $issue])
                @endforeach
            </x-ui-kanban-column>
        @endforeach

        {{-- ERLEDIGT Spalte --}}
        @if($showDone)
            @php $doneGroup = $groups->first(fn($g) => ($g->isDoneGroup ?? false)); @endphp
            @if($doneGroup)
                <x-ui-kanban-column :title="($doneGroup->label ?? 'Erledigt')" :sortable-id="null" :scrollable="true" :muted="true">
                    @foreach($doneGroup->tasks as $issue)
                        @include('dev::livewire.package.partials.issue-card', ['issue' => $issue])
                    @endforeach
                </x-ui-kanban-column>
            @endif
        @endif
    </x-ui-kanban-container>

    {{-- Board Settings Modal --}}
    @if($showBoardSettings)
        <x-ui-modal wire:model="showBoardSettings" size="md" :backdropClosable="true" :escClosable="true">
            <x-slot name="header">
                <div class="flex items-center gap-3">
                    <div class="flex-shrink-0">
                        <div class="w-10 h-10 bg-gray-100 rounded-lg flex items-center justify-center">
                            @svg('heroicon-o-pencil-square', 'w-5 h-5 text-gray-600')
                        </div>
                    </div>
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900">Board bearbeiten</h3>
                        <p class="text-sm text-gray-500">Name und Beschreibung anpassen</p>
                    </div>
                </div>
            </x-slot>

            <div class="space-y-6">
                <x-ui-input-text
                    name="editBoardName"
                    wire:model.live="editBoardName"
                    label="Name"
                    required
                />
                <x-ui-input-textarea
                    name="editBoardDescription"
                    wire:model.live="editBoardDescription"
                    label="Beschreibung"
                    rows="3"
                />
            </div>

            <x-slot name="footer">
                <div class="flex justify-end gap-3">
                    <button wire:click="$set('showBoardSettings', false)"
                            class="inline-flex items-center gap-2 px-3 py-1.5 text-sm font-medium text-gray-700 bg-gray-50 hover:bg-gray-100 rounded-md border border-gray-300 transition-colors">
                        Abbrechen
                    </button>
                    <button wire:click="saveBoardSettings"
                            class="inline-flex items-center gap-2 px-3 py-1.5 text-sm font-medium text-white bg-green-600 hover:bg-green-700 rounded-md border border-green-700 transition-colors">
                        @svg('heroicon-o-check', 'w-4 h-4')
                        Speichern
                    </button>
                </div>
            </x-slot>
        </x-ui-modal>
    @endif

    {{-- Slot Settings Modal --}}
    @if($showSlotSettings)
        <x-ui-modal wire:model="showSlotSettings" size="md" :backdropClosable="true" :escClosable="true">
            <x-slot name="header">
                <div class="flex items-center gap-3">
                    <div class="flex-shrink-0">
                        <div class="w-10 h-10 bg-gray-100 rounded-lg flex items-center justify-center">
                            @svg('heroicon-o-view-columns', 'w-5 h-5 text-gray-600')
                        </div>
                    </div>
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900">Spalte bearbeiten</h3>
                        <p class="text-sm text-gray-500">Name aendern oder Spalte loeschen</p>
                    </div>
                </div>
            </x-slot>

            <div class="space-y-6">
                <x-ui-input-text wire:model="editSlotName" label="Name" required />
            </div>

            <x-slot name="footer">
                <div class="flex items-center justify-between w-full">
                    <button wire:click="deleteSlot" wire:confirm="Spalte wirklich loeschen? Issues werden in den Backlog verschoben."
                            class="inline-flex items-center gap-2 px-3 py-1.5 text-sm font-medium text-red-600 bg-red-50 hover:bg-red-100 rounded-md border border-red-200 transition-colors">
                        @svg('heroicon-o-trash', 'w-4 h-4')
                        Loeschen
                    </button>
                    <div class="flex items-center gap-3">
                        <button wire:click="$set('showSlotSettings', false)"
                                class="inline-flex items-center gap-2 px-3 py-1.5 text-sm font-medium text-gray-700 bg-gray-50 hover:bg-gray-100 rounded-md border border-gray-300 transition-colors">
                            Abbrechen
                        </button>
                        <button wire:click="saveSlotSettings"
                                class="inline-flex items-center gap-2 px-3 py-1.5 text-sm font-medium text-white bg-green-600 hover:bg-green-700 rounded-md border border-green-700 transition-colors">
                            @svg('heroicon-o-check', 'w-4 h-4')
                            Speichern
                        </button>
                    </div>
                </div>
            </x-slot>
        </x-ui-modal>
    @endif
</x-ui-page>
