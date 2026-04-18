<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="{{ $board->name }}" icon="heroicon-o-view-columns" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Dev', 'href' => route('dev.dashboard'), 'icon' => 'code-bracket'],
            ['label' => $package->name, 'href' => route('dev.packages.show', $package)],
            ['label' => $board->name],
        ]">
            <x-slot name="left">
                <x-ui-button variant="ghost" size="sm" wire:click="toggleShowDone">
                    @svg('heroicon-o-check-circle', 'w-4 h-4')
                    <span>{{ $showDone ? 'Erledigte ausblenden' : 'Erledigte anzeigen' }}</span>
                </x-ui-button>
            </x-slot>
            <x-ui-button variant="ghost" size="sm" wire:click="createBoardSlot">
                @svg('heroicon-o-square-2-stack', 'w-4 h-4')
                <span>Spalte</span>
            </x-ui-button>
        </x-ui-page-actionbar>
    </x-slot>

    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Board-Uebersicht" width="w-80" :defaultOpen="true" side="left">
            <div class="p-6 space-y-6">
                {{-- Board-Info --}}
                <div>
                    <h3 class="text-lg font-semibold text-[var(--ui-secondary)] mb-2">{{ $board->name }}</h3>
                    @if($board->description)
                        <div class="text-sm text-[var(--ui-muted)]">{{ $board->description }}</div>
                    @endif
                </div>

                {{-- Statistiken --}}
                <div class="grid grid-cols-2 gap-2">
                    <x-ui-dashboard-tile title="Offen" :count="$openCount" icon="clock" variant="warning" size="sm" />
                    <x-ui-dashboard-tile title="Erledigt" :count="$doneCount" icon="check-circle" variant="success" size="sm" />
                </div>

                {{-- Erledigte Issues --}}
                @php $completedIssues = $groups->filter(fn($g) => $g->isDoneGroup ?? false)->flatMap(fn($g) => $g->tasks); @endphp
                @if($completedIssues->count() > 0)
                    <div>
                        <h4 class="font-medium text-[var(--ui-secondary)] mb-3">Erledigte Issues ({{ $completedIssues->count() }})</h4>
                        <div class="space-y-1 max-h-60 overflow-y-auto">
                            @foreach($completedIssues->take(10) as $issue)
                                <a href="{{ route('dev.packages.issues.show', [$package, $issue]) }}" class="block p-2 rounded text-sm border border-[var(--ui-border)]/60 bg-[var(--ui-muted-5)] hover:bg-[var(--ui-primary-5)] transition" wire:navigate>
                                    <div class="d-flex items-center gap-2">
                                        <x-heroicon-o-check-circle class="w-4 h-4 text-[var(--ui-success)]"/>
                                        <span class="truncate">{{ $issue->title }}</span>
                                    </div>
                                </a>
                            @endforeach
                            @if($completedIssues->count() > 10)
                                <div class="text-xs text-[var(--ui-muted)] italic text-center">+{{ $completedIssues->count() - 10 }} weitere</div>
                            @endif
                        </div>
                    </div>
                @endif
            </div>
        </x-ui-page-sidebar>
    </x-slot>

    {{-- Kanban Board --}}
    <x-ui-kanban-container class="h-full" sortable="updateSlotOrder" sortable-group="updateIssueOrder">
        @foreach($groups->filter(fn ($g) => !($g->isDoneGroup ?? false)) as $column)
            @php $isBacklog = $column->isBacklog ?? false; @endphp
            <x-ui-kanban-column :sortable-id="$column->id" :scrollable="true">
                <x-slot name="title">
                    <span class="flex items-center gap-1.5">
                        {{ $column->name }}
                        <span class="text-[var(--ui-muted)]">({{ $column->tasks->count() }})</span>
                    </span>
                </x-slot>
                <x-slot name="headerActions">
                    <button
                        wire:click="createIssue('{{ $column->id }}')"
                        class="text-[var(--ui-muted)] hover:text-[var(--ui-secondary)] transition-colors"
                        title="Neues Issue">
                        @svg('heroicon-o-plus-circle', 'w-4 h-4')
                    </button>
                </x-slot>

                @foreach($column->tasks as $issue)
                    @include('dev::livewire.package.partials.issue-card', ['issue' => $issue])
                @endforeach
            </x-ui-kanban-column>
        @endforeach

        {{-- Erledigt-Spalte --}}
        @if($showDone)
            @php $doneGroup = $groups->first(fn($g) => ($g->isDoneGroup ?? false)); @endphp
            @if($doneGroup)
                <x-ui-kanban-column :sortable-id="null" :scrollable="true" :muted="true">
                    <x-slot name="title">ERLEDIGT ({{ $doneGroup->tasks->count() }})</x-slot>
                    @foreach($doneGroup->tasks as $issue)
                        @include('dev::livewire.package.partials.issue-card', ['issue' => $issue])
                    @endforeach
                </x-ui-kanban-column>
            @endif
        @endif
    </x-ui-kanban-container>
</x-ui-page>
