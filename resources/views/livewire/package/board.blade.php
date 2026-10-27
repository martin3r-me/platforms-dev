<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="{{ $board->name }}" />
    </x-slot>

    <x-slot name="actionbar">
        <x-dev::actionbar :package="$package" :filters-active="$showDone ? 1 : 0">
            <x-slot:filters>
                <x-dev::filter-group label="Ansicht">
                    <x-dev::chip :active="$showDone" icon="heroicon-o-check-circle" wire:click="toggleShowDone">Geschlossene Issues anzeigen</x-dev::chip>
                </x-dev::filter-group>
            </x-slot:filters>
            <x-slot:create>
                <x-dev::menu-item icon="heroicon-o-square-2-stack" wire:click="createBoardSlot">Spalte</x-dev::menu-item>
            </x-slot:create>
            <x-slot:more>
                <x-dev::menu-item icon="heroicon-o-pencil" wire:click="openBoardSettings">Board umbenennen</x-dev::menu-item>
                <x-dev::menu-item icon="heroicon-o-archive-box" :danger="true" wire:click="archiveBoard" wire:confirm="Board wirklich archivieren? Issues bleiben erhalten.">Board archivieren</x-dev::menu-item>
            </x-slot:more>
        </x-dev::actionbar>
    </x-slot>

    @php
        $allIssues = $groups->flatMap(fn($g) => $g->tasks);
        $statsDone = $groups->filter(fn($g) => $g->isDoneGroup ?? false)->sum(fn($g) => $g->tasks->count());
        $statsOpen = $groups->filter(fn($g) => !($g->isDoneGroup ?? false))->sum(fn($g) => $g->tasks->count());
        $statsTotal = $allIssues->count();
        $statsHigh = $allIssues->filter(fn($t) => ($t->priority instanceof \BackedEnum ? $t->priority->value : $t->priority) === 'high')->count();
        $statsOverdue = $allIssues->filter(fn($t) => $t->due_date && $t->due_date->isPast() && !$t->is_done)->count();
        $statsPct = $statsTotal > 0 ? round($statsDone / $statsTotal * 100) : 0;
    @endphp
    <div class="flex-1 min-w-0 min-h-0 flex flex-col">
    {{-- Kennzahlen (vorher in der Sidebar) --}}
    <x-dev::statusbar class="gap-x-4 gap-y-1">
        <span><span class="font-medium text-gray-900 tabular-nums">{{ $statsOpen }}</span> open</span>
        <span><span class="font-medium text-green-600 tabular-nums">{{ $statsDone }}</span> closed</span>
        @if($statsHigh > 0)
            <span><span class="font-medium text-red-600 tabular-nums">{{ $statsHigh }}</span> high priority</span>
        @endif
        @if($statsOverdue > 0)
            <span><span class="font-medium text-red-600 tabular-nums">{{ $statsOverdue }}</span> overdue</span>
        @endif
        @if($statsTotal > 0)
            <span class="inline-flex items-center gap-2">
                <span class="w-20 h-1.5 rounded-full bg-gray-100 overflow-hidden"><span class="block h-full rounded-full bg-[#238636]" style="width: {{ $statsPct }}%"></span></span>
                <span class="tabular-nums">{{ $statsPct }}%</span>
            </span>
        @endif
        @if($board->description)
            <span class="hidden md:inline truncate min-w-0 flex-1 text-right text-gray-400" title="{{ $board->description }}">{{ $board->description }}</span>
        @endif
    </x-dev::statusbar>

    <!-- Kanban-Board -->
    <div class="flex-1 min-h-0">
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
    </div>
    </div>

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
