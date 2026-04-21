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
                    <button @click="open = !open" class="inline-flex items-center gap-1.5 px-3 py-[5px] text-xs font-medium text-gray-700 border border-gray-300 rounded-md hover:bg-gray-50 transition-colors">
                        @if($board->type->value === 'bug')
                            @svg('heroicon-o-bug-ant', 'w-3.5 h-3.5 text-red-500')
                        @else
                            @svg('heroicon-o-light-bulb', 'w-3.5 h-3.5 text-blue-500')
                        @endif
                        <span>{{ $board->name }}</span>
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
                        class="absolute left-0 mt-1 w-64 rounded-md bg-white border border-gray-200 z-50 py-1 top-full shadow-lg"
                        style="display: none;"
                    >
                        @foreach($allBoards as $b)
                            <a href="{{ route('dev.packages.boards.show', [$package, $b]) }}"
                               wire:navigate
                               @click="open = false"
                               class="flex items-center gap-2.5 px-4 py-2 text-xs transition-colors {{ $b->id === $board->id ? 'bg-blue-50 text-blue-700 font-medium' : 'text-gray-700 hover:bg-gray-50' }}">
                                @if($b->id === $board->id)
                                    @svg('heroicon-o-check', 'w-3.5 h-3.5 flex-shrink-0')
                                @elseif($b->type->value === 'bug')
                                    @svg('heroicon-o-bug-ant', 'w-3.5 h-3.5 text-red-500 flex-shrink-0')
                                @else
                                    @svg('heroicon-o-light-bulb', 'w-3.5 h-3.5 text-blue-500 flex-shrink-0')
                                @endif
                                <span class="flex-grow-1 truncate">{{ $b->name }}</span>
                                <span class="px-1.5 py-0.5 text-[10px] font-medium rounded-full bg-neutral-200/80 text-gray-600 tabular-nums">{{ $b->open_issues_count }}</span>
                            </a>
                        @endforeach
                    </div>
                </div>
            </x-slot>
            {{-- Board Actions --}}
            <div x-data="{ open: false }" class="relative inline-flex">
                <button wire:click="openBoardSettings"
                        class="inline-flex items-center px-2 py-[5px] text-xs text-gray-600 hover:bg-gray-100 rounded-l-md border border-gray-300 border-r-0 transition-colors">
                    @svg('heroicon-o-cog-6-tooth', 'w-3.5 h-3.5')
                </button>
                <button
                    @click="open = !open"
                    class="inline-flex items-center px-1.5 py-[5px] border border-gray-300 rounded-r-md hover:bg-gray-50 transition-colors"
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
                    class="absolute right-0 mt-1 w-48 rounded-md bg-white border border-gray-200 z-50 py-1 top-full shadow-lg"
                    style="display: none;"
                >
                    <button wire:click="openBoardSettings" @click="open = false"
                            class="flex items-center gap-2 w-full px-4 py-2 text-xs text-gray-700 hover:bg-gray-50 transition-colors text-left">
                        @svg('heroicon-o-pencil', 'w-3.5 h-3.5 text-gray-400')
                        Rename board
                    </button>
                    <button wire:click="createBoardSlot" @click="open = false"
                            class="flex items-center gap-2 w-full px-4 py-2 text-xs text-gray-700 hover:bg-gray-50 transition-colors text-left">
                        @svg('heroicon-o-square-2-stack', 'w-3.5 h-3.5 text-gray-400')
                        Add column
                    </button>
                    <div class="border-t border-gray-100 my-1"></div>
                    <button wire:click="archiveBoard" wire:confirm="Board wirklich archivieren? Issues bleiben erhalten."
                            @click="open = false"
                            class="flex items-center gap-2 w-full px-4 py-2 text-xs text-red-600 hover:bg-red-50 transition-colors text-left">
                        @svg('heroicon-o-archive-box', 'w-3.5 h-3.5')
                        Archive board
                    </button>
                </div>
            </div>
        </x-ui-page-actionbar>
    </x-slot>

    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Board" width="w-80" :defaultOpen="true" side="left">
            <div class="p-5 space-y-6">
                {{-- Board-Info --}}
                <div>
                    <h3 class="text-sm font-semibold text-gray-900 mb-1">{{ $board->name }}</h3>
                    <div class="text-xs text-gray-500 leading-relaxed">{{ $board->description ?? 'No description' }}</div>
                </div>

                {{-- Ansicht --}}
                <div class="pt-4 border-t border-gray-200">
                    <label class="flex items-center gap-2.5 cursor-pointer">
                        <input
                            type="checkbox"
                            wire:click="toggleShowDone"
                            @if($showDone) checked @endif
                            class="w-3.5 h-3.5 rounded border-gray-300 text-[#238636] focus:ring-[#238636] focus:ring-offset-0"
                        >
                        <span class="text-xs text-gray-700">Show closed issues</span>
                    </label>
                </div>

                {{-- Statistiken --}}
                <div class="pt-4 border-t border-gray-200">
                    <h3 class="text-[11px] font-semibold text-gray-500 uppercase tracking-wider mb-3">Stats</h3>
                    @php
                        $statsOpen = $groups->filter(fn($g) => !($g->isDoneGroup ?? false))->sum(fn($g) => $g->tasks->count());
                        $statsTotal = $groups->flatMap(fn($g) => $g->tasks)->count();
                        $statsDone = $groups->filter(fn($g) => $g->isDoneGroup ?? false)->sum(fn($g) => $g->tasks->count());
                        $statsHigh = $groups->flatMap(fn($g) => $g->tasks)->filter(fn($t) => ($t->priority instanceof \BackedEnum ? $t->priority->value : $t->priority) === 'high')->count();
                        $statsOverdue = $groups->flatMap(fn($g) => $g->tasks)->filter(fn($t) => $t->due_date && $t->due_date->isPast() && !$t->is_done)->count();
                    @endphp
                    <div class="space-y-2">
                        <div class="flex items-center justify-between text-xs">
                            <span class="text-gray-500">Open</span>
                            <span class="font-medium text-gray-900 tabular-nums">{{ $statsOpen }}</span>
                        </div>
                        <div class="flex items-center justify-between text-xs">
                            <span class="text-gray-500">Closed</span>
                            <span class="font-medium text-green-600 tabular-nums">{{ $statsDone }}</span>
                        </div>
                        <div class="flex items-center justify-between text-xs">
                            <span class="text-gray-500">Total</span>
                            <span class="font-medium text-gray-900 tabular-nums">{{ $statsTotal }}</span>
                        </div>
                        @if($statsHigh > 0)
                            <div class="flex items-center justify-between text-xs">
                                <span class="text-gray-500">High priority</span>
                                <span class="font-medium text-red-600 tabular-nums">{{ $statsHigh }}</span>
                            </div>
                        @endif
                        @if($statsOverdue > 0)
                            <div class="flex items-center justify-between text-xs">
                                <span class="text-gray-500">Overdue</span>
                                <span class="font-medium text-red-600 tabular-nums">{{ $statsOverdue }}</span>
                            </div>
                        @endif
                    </div>

                    {{-- Progress bar --}}
                    @if($statsTotal > 0)
                        <div class="mt-4">
                            <div class="flex items-center justify-between mb-1.5">
                                <span class="text-[11px] text-gray-500">Progress</span>
                                <span class="text-[11px] font-medium text-gray-700 tabular-nums">{{ $statsTotal > 0 ? round($statsDone / $statsTotal * 100) : 0 }}%</span>
                            </div>
                            <div class="w-full h-2 rounded-full bg-gray-100 overflow-hidden">
                                <div class="h-full rounded-full bg-[#238636] transition-all" style="width: {{ $statsTotal > 0 ? round($statsDone / $statsTotal * 100) : 0 }}%"></div>
                            </div>
                        </div>
                    @endif
                </div>

                {{-- Erledigte Issues --}}
                @php $completedIssues = $groups->filter(fn($g) => $g->isDoneGroup ?? false)->flatMap(fn($g) => $g->tasks); @endphp
                @if($completedIssues->count() > 0)
                    <div class="pt-4 border-t border-gray-200">
                        <h4 class="text-[11px] font-semibold text-gray-500 uppercase tracking-wider mb-3">Recently closed</h4>
                        <div class="space-y-1 max-h-48 overflow-y-auto">
                            @foreach($completedIssues->take(8) as $issue)
                                <a href="{{ route('dev.packages.issues.show', [$package, $issue]) }}" class="d-flex items-center gap-2 px-2 py-1.5 rounded text-xs hover:bg-gray-50 transition" wire:navigate>
                                    <svg class="w-3.5 h-3.5 text-purple-500 flex-shrink-0" viewBox="0 0 16 16" fill="currentColor"><path d="M11.28 6.78a.75.75 0 0 0-1.06-1.06L7.25 8.69 5.78 7.22a.75.75 0 0 0-1.06 1.06l2 2a.75.75 0 0 0 1.06 0ZM16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0Zm-1.5 0a6.5 6.5 0 1 0-13 0 6.5 6.5 0 0 0 13 0Z"/></svg>
                                    <span class="truncate text-gray-500 line-through">{{ $issue->title }}</span>
                                </a>
                            @endforeach
                            @if($completedIssues->count() > 8)
                                <div class="text-[11px] text-gray-400 text-center py-1">+{{ $completedIssues->count() - 8 }} more</div>
                            @endif
                        </div>
                    </div>
                @endif
            </div>
        </x-ui-page-sidebar>
    </x-slot>

    <x-slot name="activity">
        <x-ui-page-sidebar title="Activity" width="w-80" :defaultOpen="false" storeKey="activityOpen" side="right">
            <div class="p-5">
                <h3 class="text-[11px] font-semibold uppercase tracking-wider text-gray-500 mb-4">Recent activity</h3>
                <div class="space-y-2">
                    @forelse(($activities ?? []) as $activity)
                        <div class="px-3 py-2 rounded-md border border-gray-100 bg-gray-50 text-xs">
                            <div class="font-medium text-gray-900 mb-0.5">{{ $activity['title'] ?? 'Activity' }}</div>
                            <div class="text-[11px] text-gray-400">{{ $activity['time'] ?? '' }}</div>
                        </div>
                    @empty
                        <div class="py-8 text-center">
                            @svg('heroicon-o-clock', 'w-6 h-6 text-gray-200 mx-auto mb-2')
                            <p class="text-[11px] text-gray-500">No activity yet</p>
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
                    <span class="flex items-center gap-1.5 text-xs">
                        <span class="font-medium text-gray-700">{{ $column->label ?? $column->name ?? 'Column' }}</span>
                        <span class="px-1.5 py-0.5 text-[10px] font-medium rounded-full bg-neutral-200/80 text-gray-600 tabular-nums">{{ $column->tasks->count() }}</span>
                    </span>
                </x-slot>
                <x-slot name="headerActions">
                    <button
                        wire:click="createIssue('{{ $column->id }}')"
                        class="p-1 rounded text-gray-400 hover:text-[#238636] hover:bg-green-50 transition-colors"
                        title="New issue">
                        @svg('heroicon-o-plus', 'w-3.5 h-3.5')
                    </button>
                    <button
                        wire:click="openSlotSettings({{ $column->id }})"
                        class="p-1 rounded text-gray-400 hover:text-gray-700 opacity-0 group-hover:opacity-100 transition-all"
                        title="Edit column">
                        @svg('heroicon-o-ellipsis-horizontal', 'w-3.5 h-3.5')
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
                <x-ui-kanban-column :title="($doneGroup->label ?? 'Done')" :sortable-id="null" :scrollable="true" :muted="true">
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
                <h3 class="text-sm font-semibold text-gray-900">Edit board</h3>
            </x-slot>
            <div class="space-y-5">
                <x-ui-input-text name="editBoardName" wire:model.live="editBoardName" label="Name" required />
                <x-ui-input-textarea name="editBoardDescription" wire:model.live="editBoardDescription" label="Description" rows="3" />
            </div>
            <x-slot name="footer">
                <div class="flex justify-end gap-2">
                    <button wire:click="$set('showBoardSettings', false)" class="inline-flex items-center gap-1.5 px-3 py-[5px] text-xs font-medium text-gray-700 bg-gray-50 hover:bg-gray-100 rounded-md border border-gray-300 transition-colors">Cancel</button>
                    <button wire:click="saveBoardSettings" class="inline-flex items-center gap-1.5 px-3 py-[5px] text-xs font-medium text-white bg-[#238636] hover:bg-[#2ea043] rounded-md border border-[#2ea043] transition-colors">Save changes</button>
                </div>
            </x-slot>
        </x-ui-modal>
    @endif

    {{-- Slot Settings Modal --}}
    @if($showSlotSettings)
        <x-ui-modal wire:model="showSlotSettings" size="md" :backdropClosable="true" :escClosable="true">
            <x-slot name="header">
                <h3 class="text-sm font-semibold text-gray-900">Edit column</h3>
            </x-slot>
            <div class="space-y-5">
                <x-ui-input-text wire:model="editSlotName" label="Name" required />
            </div>
            <x-slot name="footer">
                <div class="flex items-center justify-between w-full">
                    <button wire:click="deleteSlot" wire:confirm="Spalte wirklich loeschen? Issues werden in den Backlog verschoben."
                            class="inline-flex items-center gap-1.5 px-3 py-[5px] text-xs font-medium text-red-700 bg-red-50 hover:bg-red-100 rounded-md border border-red-200 transition-colors">
                        @svg('heroicon-o-trash', 'w-3.5 h-3.5')
                        Delete
                    </button>
                    <div class="flex items-center gap-2">
                        <button wire:click="$set('showSlotSettings', false)" class="inline-flex items-center gap-1.5 px-3 py-[5px] text-xs font-medium text-gray-700 bg-gray-50 hover:bg-gray-100 rounded-md border border-gray-300 transition-colors">Cancel</button>
                        <button wire:click="saveSlotSettings" class="inline-flex items-center gap-1.5 px-3 py-[5px] text-xs font-medium text-white bg-[#238636] hover:bg-[#2ea043] rounded-md border border-[#2ea043] transition-colors">Save</button>
                    </div>
                </div>
            </x-slot>
        </x-ui-modal>
    @endif
</x-ui-page>
