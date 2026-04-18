<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="{{ $issue->title }}" icon="heroicon-o-ticket" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Dev', 'href' => route('dev.dashboard'), 'icon' => 'code-bracket'],
            ['label' => $package->name, 'href' => route('dev.packages.show', $package)],
            ['label' => $issue->title],
        ]" />
    </x-slot>

    <x-ui-page-container>
        <div class="max-w-3xl space-y-6">
            {{-- Issue Header --}}
            <div class="d-flex items-start justify-between">
                <div class="flex-grow-1">
                    @if($editing)
                        <div class="space-y-3">
                            <x-ui-input-text wire:model="editTitle" label="Titel" />
                            <x-ui-input-textarea wire:model="editDescription" label="Beschreibung" rows="6" />
                            <x-ui-input-select
                                name="editPriority"
                                wire:model="editPriority"
                                label="Prioritaet"
                                :options="['low' => 'Niedrig', 'normal' => 'Normal', 'high' => 'Hoch']"
                            />
                            <div class="d-flex items-center gap-2">
                                <x-ui-button variant="primary" size="sm" wire:click="saveEdit">Speichern</x-ui-button>
                                <x-ui-button variant="secondary-outline" size="sm" wire:click="cancelEdit">Abbrechen</x-ui-button>
                            </div>
                        </div>
                    @else
                        <h1 class="text-xl font-bold text-[var(--ui-secondary)] mb-2">{{ $issue->title }}</h1>
                        <div class="d-flex items-center gap-3 mb-4">
                            @php
                                $priorityValue = $issue->priority instanceof \BackedEnum ? $issue->priority->value : $issue->priority;
                                $priorityColor = match($priorityValue) {
                                    'high' => 'danger',
                                    'low' => 'secondary',
                                    default => 'primary',
                                };
                            @endphp
                            <x-ui-badge :variant="$issue->status === 'open' ? 'success' : 'secondary'">
                                {{ $issue->status === 'open' ? 'Offen' : 'Geschlossen' }}
                            </x-ui-badge>
                            <x-ui-badge :variant="$priorityColor">
                                {{ ucfirst($priorityValue) }}
                            </x-ui-badge>
                            @if($issue->slot)
                                <x-ui-badge variant="secondary">{{ $issue->slot->name }}</x-ui-badge>
                            @endif
                        </div>

                        @if($issue->description)
                            <div class="prose prose-sm max-w-none text-[var(--ui-secondary)]">
                                {!! nl2br(e($issue->description)) !!}
                            </div>
                        @else
                            <p class="text-sm text-[var(--ui-muted)] italic">Keine Beschreibung.</p>
                        @endif
                    @endif
                </div>

                @if(!$editing)
                    <div class="d-flex items-center gap-2 flex-shrink-0">
                        <x-ui-button variant="secondary-outline" size="sm" wire:click="startEditing">
                            @svg('heroicon-o-pencil', 'w-4 h-4')
                        </x-ui-button>
                        <x-ui-button
                            :variant="$issue->is_done ? 'secondary-outline' : 'success'"
                            size="sm"
                            wire:click="toggleDone"
                        >
                            @if($issue->is_done)
                                Wiederoeffnen
                            @else
                                Erledigt
                            @endif
                        </x-ui-button>
                    </div>
                @endif
            </div>

            {{-- Labels --}}
            @if(!empty($issue->labels))
                <div class="d-flex items-center gap-1 flex-wrap">
                    @foreach($issue->labels as $label)
                        <span class="px-2 py-0.5 text-xs rounded-full bg-[var(--ui-muted-5)] border border-[var(--ui-border)]/40 text-[var(--ui-muted)]">{{ $label }}</span>
                    @endforeach
                </div>
            @endif
        </div>
    </x-ui-page-container>

    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Details" width="w-80" :defaultOpen="true">
            <div class="p-5 space-y-4">
                <div>
                    <div class="text-[10px] font-semibold uppercase tracking-wider text-[var(--ui-muted)] mb-1">Board</div>
                    <div class="text-sm text-[var(--ui-secondary)]">{{ $issue->board?->name ?? '-' }}</div>
                </div>
                <div>
                    <div class="text-[10px] font-semibold uppercase tracking-wider text-[var(--ui-muted)] mb-1">Slot</div>
                    <div class="text-sm text-[var(--ui-secondary)]">{{ $issue->slot?->name ?? 'Backlog' }}</div>
                </div>
                <div>
                    <div class="text-[10px] font-semibold uppercase tracking-wider text-[var(--ui-muted)] mb-1">Zustaendig</div>
                    <div class="text-sm text-[var(--ui-secondary)]">{{ $issue->userInCharge?->name ?? '-' }}</div>
                </div>
                <div>
                    <div class="text-[10px] font-semibold uppercase tracking-wider text-[var(--ui-muted)] mb-1">Erstellt von</div>
                    <div class="text-sm text-[var(--ui-secondary)]">{{ $issue->createdBy?->name ?? '-' }}</div>
                </div>
                @if($issue->due_date)
                    <div>
                        <div class="text-[10px] font-semibold uppercase tracking-wider text-[var(--ui-muted)] mb-1">Faellig</div>
                        <div class="text-sm text-[var(--ui-secondary)]">{{ $issue->due_date->format('d.m.Y') }}</div>
                    </div>
                @endif
                <div>
                    <div class="text-[10px] font-semibold uppercase tracking-wider text-[var(--ui-muted)] mb-1">Erstellt</div>
                    <div class="text-sm text-[var(--ui-secondary)]">{{ $issue->created_at->format('d.m.Y H:i') }}</div>
                </div>
            </div>
        </x-ui-page-sidebar>
    </x-slot>
</x-ui-page>
