<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="array_filter([
            ['label' => 'Dev', 'href' => route('dev.dashboard'), 'icon' => 'code-bracket'],
            ['label' => $package->name, 'href' => route('dev.packages.show', $package)],
            $issue->board ? ['label' => $issue->board->name, 'href' => route('dev.packages.boards.show', [$package, $issue->board])] : null,
            ['label' => Str::limit($issue->title, 40)],
        ])">
            @if(!$editing)
                <x-ui-button variant="secondary-outline" size="sm" wire:click="startEditing">
                    @svg('heroicon-o-pencil', 'w-4 h-4')
                    <span>Bearbeiten</span>
                </x-ui-button>
            @endif
        </x-ui-page-actionbar>
    </x-slot>

    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Übersicht" width="w-80" :defaultOpen="true" storeKey="sidebarOpen" side="left">
            <div class="p-6 space-y-6">
                {{-- Status --}}
                <div class="space-y-2">
                    <button type="button" wire:click="toggleDone" class="w-full text-left flex items-center justify-between py-2 px-3 rounded-lg bg-[var(--ui-muted-5)] border border-[var(--ui-border)]/40 hover:bg-[var(--ui-primary-5)] transition-colors cursor-pointer">
                        <div class="flex items-center gap-2">
                            @svg('heroicon-o-check-circle', 'w-4 h-4 text-[var(--ui-success)]')
                            <span class="text-sm text-[var(--ui-secondary)]">Status</span>
                        </div>
                        <span class="text-sm font-semibold text-[var(--ui-secondary)]">{{ $issue->is_done ? 'Erledigt' : 'Offen' }}</span>
                    </button>
                </div>

                {{-- Issue Info --}}
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-4">Issue Info</h3>
                    <div class="space-y-2 text-sm">
                        <div class="flex justify-between">
                            <span class="text-[var(--ui-muted)]">Erstellt:</span>
                            <span class="font-medium text-[var(--ui-secondary)]">{{ $issue->created_at->format('d.m.Y H:i') }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-[var(--ui-muted)]">Aktualisiert:</span>
                            <span class="font-medium text-[var(--ui-secondary)]">{{ $issue->updated_at->format('d.m.Y H:i') }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-[var(--ui-muted)]">Erstellt von:</span>
                            <span class="font-medium text-[var(--ui-secondary)]">{{ $issue->createdBy?->name ?? 'Unbekannt' }}</span>
                        </div>
                        @if($issue->userInCharge)
                            <div class="flex justify-between">
                                <span class="text-[var(--ui-muted)]">Zuständig:</span>
                                <span class="font-medium text-[var(--ui-secondary)]">{{ $issue->userInCharge->name }}</span>
                            </div>
                        @endif
                        <div class="flex justify-between">
                            <span class="text-[var(--ui-muted)]">Board:</span>
                            <span class="font-medium text-[var(--ui-secondary)]">{{ $issue->board?->name ?? '-' }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-[var(--ui-muted)]">Slot:</span>
                            <span class="font-medium text-[var(--ui-secondary)]">{{ $issue->slot?->name ?? 'Backlog' }}</span>
                        </div>
                        @if($issue->due_date)
                            <div class="flex justify-between">
                                <span class="text-[var(--ui-muted)]">Fällig:</span>
                                <span class="font-medium {{ $issue->due_date->isPast() && !$issue->is_done ? 'text-[var(--ui-danger)]' : 'text-[var(--ui-secondary)]' }}">{{ $issue->due_date->format('d.m.Y') }}</span>
                            </div>
                        @endif
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

    <x-ui-page-container spacing="space-y-6">
        {{-- Header Block --}}
        <div class="bg-white rounded-xl border border-[var(--ui-border)]/60 shadow-sm overflow-hidden">
            <div class="py-4 px-6">
                <div class="flex items-start justify-between gap-4">
                    <div class="flex-1 min-w-0">
                        <h1 class="text-xl font-bold text-[var(--ui-secondary)] mb-2 tracking-tight">{{ $issue->title }}</h1>
                        <div class="flex flex-wrap items-center gap-5 text-sm text-[var(--ui-muted)]">
                            @if($issue->board)
                                <span class="flex items-center gap-1.5">
                                    @svg('heroicon-o-rectangle-stack', 'w-4 h-4')
                                    {{ $issue->board->name }}
                                </span>
                            @endif
                            @if($issue->userInCharge)
                                <span class="flex items-center gap-1.5">
                                    @svg('heroicon-o-user', 'w-4 h-4')
                                    {{ $issue->userInCharge->name }}
                                </span>
                            @endif
                            @if($issue->due_date)
                                @php
                                    $isOverdue = $issue->due_date->isPast() && !$issue->is_done;
                                    $dueDateColor = $isOverdue ? 'text-[var(--ui-danger)]' : '';
                                @endphp
                                <span class="flex items-center gap-1.5 {{ $dueDateColor }}">
                                    @svg('heroicon-o-calendar', 'w-4 h-4')
                                    {{ $issue->due_date->format('d.m.Y') }}
                                </span>
                            @endif
                            @php
                                $priorityValue = $issue->priority instanceof \BackedEnum ? $issue->priority->value : $issue->priority;
                            @endphp
                            @if($priorityValue === 'high')
                                <span class="flex items-center gap-1.5 text-[var(--ui-danger)]">
                                    @svg('heroicon-o-fire', 'w-4 h-4')
                                    Hoch
                                </span>
                            @endif
                        </div>
                    </div>
                    <div class="flex items-center gap-2 flex-shrink-0">
                        @if($issue->is_done)
                            <x-ui-badge variant="success" size="sm">Erledigt</x-ui-badge>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- Grunddaten --}}
        <div class="bg-white rounded-xl border border-[var(--ui-border)]/60 shadow-sm overflow-hidden">
            <div class="p-5">
                @if($editing)
                    <h4 class="text-sm font-semibold text-[var(--ui-muted)] uppercase tracking-wider mb-3">Grunddaten</h4>
                    <x-ui-form-grid :cols="2" :gap="6">
                        <x-ui-input-text wire:model="editTitle" label="Titel" required />
                        <x-ui-input-select
                            name="editPriority"
                            wire:model="editPriority"
                            label="Priorität"
                            :options="['low' => 'Niedrig', 'normal' => 'Normal', 'high' => 'Hoch']"
                        />
                    </x-ui-form-grid>

                    <div class="mt-6">
                        <h4 class="text-sm font-semibold text-[var(--ui-muted)] uppercase tracking-wider mb-3">Beschreibung</h4>
                        <x-ui-input-textarea wire:model="editDescription" label="Beschreibung" rows="6" />
                    </div>

                    <div class="mt-6 d-flex items-center gap-2">
                        <x-ui-button variant="primary" size="sm" wire:click="saveEdit">
                            @svg('heroicon-o-check', 'w-4 h-4')
                            <span>Speichern</span>
                        </x-ui-button>
                        <x-ui-button variant="secondary-outline" size="sm" wire:click="cancelEdit">Abbrechen</x-ui-button>
                    </div>
                @else
                    <h4 class="text-sm font-semibold text-[var(--ui-muted)] uppercase tracking-wider mb-3">Beschreibung</h4>
                    @if($issue->description)
                        <div class="prose prose-sm max-w-none text-[var(--ui-secondary)]">
                            {!! nl2br(e($issue->description)) !!}
                        </div>
                    @else
                        <p class="text-sm text-[var(--ui-muted)] italic">Keine Beschreibung vorhanden.</p>
                    @endif
                @endif
            </div>
        </div>

        {{-- Labels --}}
        @if(!empty($issue->labels))
            <div class="bg-white rounded-xl border border-[var(--ui-border)]/60 shadow-sm overflow-hidden">
                <div class="p-5">
                    <h4 class="text-sm font-semibold text-[var(--ui-muted)] uppercase tracking-wider mb-3">Labels</h4>
                    <div class="d-flex items-center gap-1 flex-wrap">
                        @foreach($issue->labels as $label)
                            <span class="px-2 py-0.5 text-xs rounded-full bg-[var(--ui-muted-5)] border border-[var(--ui-border)]/40 text-[var(--ui-muted)]">{{ $label }}</span>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif
    </x-ui-page-container>
</x-ui-page>
