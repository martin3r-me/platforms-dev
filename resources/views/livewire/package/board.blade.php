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
                    <button @click="open = !open" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium text-[var(--ui-secondary)] border border-[var(--ui-border)] rounded-md hover:bg-[var(--ui-muted-5)] transition-colors">
                        @if($board->type->value === 'bug')
                            @svg('heroicon-o-bug-ant', 'w-4 h-4 text-[var(--ui-danger)]')
                        @else
                            @svg('heroicon-o-light-bulb', 'w-4 h-4 text-[var(--ui-primary)]')
                        @endif
                        {{ $board->name }}
                        @svg('heroicon-o-chevron-down', 'w-3 h-3 text-[var(--ui-muted)]')
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
                        class="absolute left-0 mt-1 w-64 rounded-lg bg-[var(--ui-surface)] shadow-lg ring-1 ring-[var(--ui-border)] z-50 py-1 top-full"
                        style="display: none;"
                    >
                        @foreach($allBoards as $b)
                            <a href="{{ route('dev.packages.boards.show', [$package, $b]) }}"
                               wire:navigate
                               @click="open = false"
                               class="flex items-center gap-2.5 px-3 py-2 text-sm transition-colors {{ $b->id === $board->id ? 'bg-[var(--ui-primary-5)] text-[var(--ui-primary)] font-medium' : 'text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)]' }}">
                                @if($b->id === $board->id)
                                    @svg('heroicon-o-check', 'w-4 h-4 flex-shrink-0')
                                @elseif($b->type->value === 'bug')
                                    @svg('heroicon-o-bug-ant', 'w-4 h-4 text-[var(--ui-danger)] flex-shrink-0')
                                @else
                                    @svg('heroicon-o-light-bulb', 'w-4 h-4 text-[var(--ui-primary)] flex-shrink-0')
                                @endif
                                <span class="flex-grow-1 truncate">{{ $b->name }}</span>
                                <span class="text-xs px-1.5 py-0.5 rounded-full bg-[var(--ui-muted-5)] text-[var(--ui-muted)] font-medium">{{ $b->open_issues_count }}</span>
                            </a>
                        @endforeach
                    </div>
                </div>
            </x-slot>
            {{-- Board Actions --}}
            <div x-data="{ open: false }" class="relative inline-flex">
                <x-ui-button variant="ghost" size="sm" wire:click="openBoardSettings" class="rounded-r-none border-r-0">
                    @svg('heroicon-o-cog-6-tooth', 'w-4 h-4')
                </x-ui-button>
                <button
                    @click="open = !open"
                    class="inline-flex items-center px-1.5 border border-[var(--ui-border)] rounded-r-md hover:bg-[var(--ui-muted-5)] transition-colors"
                >
                    @svg('heroicon-o-chevron-down', 'w-3 h-3 text-[var(--ui-secondary)]')
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
                    class="absolute right-0 mt-1 w-48 rounded-lg bg-[var(--ui-surface)] shadow-lg ring-1 ring-[var(--ui-border)] z-50 py-1 top-full"
                    style="display: none;"
                >
                    <button wire:click="openBoardSettings" @click="open = false"
                            class="flex items-center gap-2 w-full px-3 py-2 text-sm text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)] transition-colors text-left">
                        @svg('heroicon-o-pencil', 'w-4 h-4 text-[var(--ui-muted)]')
                        Board umbenennen
                    </button>
                    <button wire:click="createBoardSlot" @click="open = false"
                            class="flex items-center gap-2 w-full px-3 py-2 text-sm text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)] transition-colors text-left">
                        @svg('heroicon-o-square-2-stack', 'w-4 h-4 text-[var(--ui-muted)]')
                        Spalte hinzufuegen
                    </button>
                    <div class="border-t border-[var(--ui-border)]/40 my-1"></div>
                    <button wire:click="archiveBoard" wire:confirm="Board wirklich archivieren? Issues bleiben erhalten."
                            @click="open = false"
                            class="flex items-center gap-2 w-full px-3 py-2 text-sm text-[var(--ui-danger)] hover:bg-[var(--ui-danger)]/5 transition-colors text-left">
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
                    <h3 class="text-lg font-semibold text-[var(--ui-secondary)] mb-2">{{ $board->name }}</h3>
                    <div class="text-sm text-[var(--ui-muted)]">{{ $board->description ?? 'Keine Beschreibung' }}</div>
                </div>

                {{-- Ansicht --}}
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-4">Ansicht</h3>
                    <div class="space-y-2">
                        <label class="flex items-center gap-3 cursor-pointer">
                            <input
                                type="checkbox"
                                wire:click="toggleShowDone"
                                @if($showDone) checked @endif
                                class="w-4 h-4 rounded border-[var(--ui-border)] text-[var(--ui-primary)] focus:ring-[var(--ui-primary)] focus:ring-offset-0"
                            >
                            <span class="text-sm text-[var(--ui-secondary)]">Erledigte Issues anzeigen</span>
                        </label>
                    </div>
                </div>

                {{-- Statistiken --}}
                <div class="grid grid-cols-2 gap-2">
                    <x-ui-dashboard-tile title="Offen" :count="$groups->filter(fn($g) => !($g->isDoneGroup ?? false))->sum(fn($g) => $g->tasks->count())" icon="clock" variant="warning" size="sm" />
                    <x-ui-dashboard-tile title="Gesamt" :count="$groups->flatMap(fn($g) => $g->tasks)->count()" icon="document-text" variant="secondary" size="sm" />
                    <x-ui-dashboard-tile title="Erledigt" :count="$groups->filter(fn($g) => $g->isDoneGroup ?? false)->sum(fn($g) => $g->tasks->count())" icon="check-circle" variant="success" size="sm" />
                    <x-ui-dashboard-tile title="Ohne Faelligkeit" :count="$groups->flatMap(fn($g) => $g->tasks)->filter(fn($t) => !$t->due_date)->count()" icon="calendar" variant="neutral" size="sm" />
                    <x-ui-dashboard-tile title="Hohe Prioritaet" :count="$groups->flatMap(fn($g) => $g->tasks)->filter(fn($t) => ($t->priority instanceof \BackedEnum ? $t->priority->value : $t->priority) === 'high')->count()" icon="fire" variant="danger" size="sm" />
                    <x-ui-dashboard-tile title="Ueberfaellig" :count="$groups->flatMap(fn($g) => $g->tasks)->filter(fn($t) => $t->due_date && $t->due_date->isPast() && !$t->is_done)->count()" icon="exclamation-circle" variant="danger" size="sm" />
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
                @else
                    <div class="text-sm text-[var(--ui-muted)] italic">Noch keine erledigten Issues</div>
                @endif
            </div>
        </x-ui-page-sidebar>
    </x-slot>

    <x-slot name="activity">
        <x-ui-page-sidebar title="Aktivitaeten" width="w-80" :defaultOpen="false" storeKey="activityOpen" side="right">
            <div class="p-6">
                <h3 class="text-xs font-semibold uppercase tracking-wider text-[var(--ui-muted)] mb-4">Letzte Aktivitaeten</h3>
                <div class="space-y-3">
                    @forelse(($activities ?? []) as $activity)
                        <div class="p-3 rounded-lg border border-[var(--ui-border)]/40 bg-[var(--ui-muted-5)] hover:bg-[var(--ui-muted)] transition-colors">
                            <div class="flex items-start justify-between gap-2 mb-1">
                                <div class="flex-1 min-w-0">
                                    <div class="text-sm font-medium text-[var(--ui-secondary)] leading-snug">
                                        {{ $activity['title'] ?? 'Aktivitaet' }}
                                    </div>
                                </div>
                            </div>
                            <div class="flex items-center gap-2 text-xs text-[var(--ui-muted)]">
                                @svg('heroicon-o-clock', 'w-3 h-3')
                                <span>{{ $activity['time'] ?? '' }}</span>
                            </div>
                        </div>
                    @empty
                        <div class="py-8 text-center">
                            <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-[var(--ui-muted-5)] mb-3">
                                @svg('heroicon-o-clock', 'w-6 h-6 text-[var(--ui-muted)]')
                            </div>
                            <p class="text-sm text-[var(--ui-muted)]">Noch keine Aktivitaeten</p>
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
                        <span class="text-xs text-[var(--ui-muted)] font-normal">({{ $column->tasks->count() }})</span>
                    </span>
                </x-slot>
                <x-slot name="headerActions">
                    <button
                        wire:click="createIssue('{{ $column->id }}')"
                        class="p-1 rounded bg-[var(--ui-primary-5)] text-[var(--ui-primary)] hover:bg-[var(--ui-primary)]/20 transition-colors"
                        title="Neues Issue">
                        @svg('heroicon-o-plus', 'w-4 h-4')
                    </button>
                    <button
                        wire:click="openSlotSettings({{ $column->id }})"
                        class="p-1 rounded text-[var(--ui-muted)] hover:text-[var(--ui-secondary)] opacity-0 group-hover:opacity-100 transition-all"
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
                        <div class="w-10 h-10 bg-[var(--ui-primary-10)] rounded-lg flex items-center justify-center">
                            @svg('heroicon-o-pencil-square', 'w-5 h-5 text-[var(--ui-primary)]')
                        </div>
                    </div>
                    <div>
                        <h3 class="text-lg font-semibold text-[var(--ui-secondary)]">Board bearbeiten</h3>
                        <p class="text-sm text-[var(--ui-muted)]">Name und Beschreibung anpassen</p>
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
                    <x-ui-button variant="secondary-outline" size="sm" wire:click="$set('showBoardSettings', false)">
                        Abbrechen
                    </x-ui-button>
                    <x-ui-button variant="primary" size="sm" wire:click="saveBoardSettings">
                        <span class="inline-flex items-center gap-2">
                            @svg('heroicon-o-check', 'w-4 h-4')
                            Speichern
                        </span>
                    </x-ui-button>
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
                        <div class="w-10 h-10 bg-[var(--ui-primary-10)] rounded-lg flex items-center justify-center">
                            @svg('heroicon-o-view-columns', 'w-5 h-5 text-[var(--ui-primary)]')
                        </div>
                    </div>
                    <div>
                        <h3 class="text-lg font-semibold text-[var(--ui-secondary)]">Spalte bearbeiten</h3>
                        <p class="text-sm text-[var(--ui-muted)]">Name aendern oder Spalte loeschen</p>
                    </div>
                </div>
            </x-slot>

            <div class="space-y-6">
                <x-ui-input-text wire:model="editSlotName" label="Name" required />
            </div>

            <x-slot name="footer">
                <div class="flex items-center justify-between w-full">
                    <x-ui-button variant="danger-outline" size="sm" wire:click="deleteSlot" wire:confirm="Spalte wirklich loeschen? Issues werden in den Backlog verschoben.">
                        <span class="inline-flex items-center gap-2">
                            @svg('heroicon-o-trash', 'w-4 h-4')
                            Loeschen
                        </span>
                    </x-ui-button>
                    <div class="flex items-center gap-3">
                        <x-ui-button variant="secondary-outline" size="sm" wire:click="$set('showSlotSettings', false)">Abbrechen</x-ui-button>
                        <x-ui-button variant="primary" size="sm" wire:click="saveSlotSettings">
                            <span class="inline-flex items-center gap-2">
                                @svg('heroicon-o-check', 'w-4 h-4')
                                Speichern
                            </span>
                        </x-ui-button>
                    </div>
                </div>
            </x-slot>
        </x-ui-modal>
    @endif
</x-ui-page>
