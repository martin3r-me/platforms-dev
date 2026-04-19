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
        </x-ui-page-actionbar>
    </x-slot>

    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Uebersicht" width="w-80" :defaultOpen="true" storeKey="sidebarOpen" side="left">
            <div class="p-6 space-y-6">
                {{-- Status Toggle --}}
                <div class="space-y-2">
                    <button type="button" wire:click="toggleDone" class="w-full text-left flex items-center justify-between py-2 px-3 rounded-lg bg-[var(--ui-muted-5)] border border-[var(--ui-border)]/40 hover:bg-[var(--ui-primary-5)] transition-colors cursor-pointer">
                        <div class="flex items-center gap-2">
                            @if($issue->is_done)
                                @svg('heroicon-s-check-circle', 'w-4 h-4 text-[var(--ui-success)]')
                            @else
                                @svg('heroicon-o-circle-stack', 'w-4 h-4 text-[var(--ui-warning)]')
                            @endif
                            <span class="text-sm text-[var(--ui-secondary)]">Status</span>
                        </div>
                        <span class="text-sm font-semibold {{ $issue->is_done ? 'text-[var(--ui-success)]' : 'text-[var(--ui-secondary)]' }}">
                            {{ $issue->is_done ? 'Erledigt' : 'Offen' }}
                        </span>
                    </button>
                </div>

                {{-- Story Points --}}
                @if($issue->story_points)
                    <div class="flex items-center justify-between py-2 px-3 rounded-lg bg-[var(--ui-primary-5)] border border-[var(--ui-primary)]/20">
                        <span class="text-sm text-[var(--ui-secondary)]">Story Points</span>
                        <span class="text-lg font-bold text-[var(--ui-primary)]">{{ $issue->story_points->points() }} <span class="text-xs font-medium opacity-60">{{ $issue->story_points->label() }}</span></span>
                    </div>
                @endif

                {{-- DoD Progress --}}
                @if($criteriaTotal > 0)
                    <div class="py-2 px-3 rounded-lg bg-[var(--ui-muted-5)] border border-[var(--ui-border)]/40">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-sm text-[var(--ui-secondary)]">DoD Fortschritt</span>
                            <span class="text-sm font-semibold {{ $criteriaDone === $criteriaTotal ? 'text-[var(--ui-success)]' : 'text-[var(--ui-secondary)]' }}">{{ $criteriaDone }}/{{ $criteriaTotal }}</span>
                        </div>
                        <div class="w-full h-1.5 rounded-full bg-[var(--ui-border)]/40 overflow-hidden">
                            <div class="h-full rounded-full {{ $criteriaDone === $criteriaTotal ? 'bg-[var(--ui-success)]' : 'bg-[var(--ui-primary)]' }} transition-all"
                                 style="width: {{ $criteriaTotal > 0 ? round($criteriaDone / $criteriaTotal * 100) : 0 }}%"></div>
                        </div>
                    </div>
                @endif

                {{-- Issue Info --}}
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-4">Details</h3>
                    <div class="space-y-2 text-sm">
                        @php
                            $priorityValue = $issue->priority instanceof \BackedEnum ? $issue->priority->value : $issue->priority;
                        @endphp
                        <div class="flex justify-between">
                            <span class="text-[var(--ui-muted)]">Prioritaet:</span>
                            <span class="font-medium {{ $priorityValue === 'high' ? 'text-[var(--ui-danger)]' : 'text-[var(--ui-secondary)]' }}">
                                @if($priorityValue === 'high') @svg('heroicon-o-fire', 'w-3.5 h-3.5 inline') @endif
                                {{ ['low' => 'Niedrig', 'normal' => 'Normal', 'high' => 'Hoch'][$priorityValue] ?? $priorityValue }}
                            </span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-[var(--ui-muted)]">Board:</span>
                            <span class="font-medium text-[var(--ui-secondary)]">{{ $issue->board?->name ?? '-' }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-[var(--ui-muted)]">Slot:</span>
                            <span class="font-medium text-[var(--ui-secondary)]">{{ $issue->slot?->name ?? 'Backlog' }}</span>
                        </div>
                        @if($issue->userInCharge)
                            <div class="flex justify-between">
                                <span class="text-[var(--ui-muted)]">Zustaendig:</span>
                                <span class="font-medium text-[var(--ui-secondary)]">{{ $issue->userInCharge->name }}</span>
                            </div>
                        @endif
                        @if($issue->due_date)
                            <div class="flex justify-between">
                                <span class="text-[var(--ui-muted)]">Faellig:</span>
                                <span class="font-medium {{ $issue->due_date->isPast() && !$issue->is_done ? 'text-[var(--ui-danger)]' : 'text-[var(--ui-secondary)]' }}">
                                    {{ $issue->due_date->format('d.m.Y') }}
                                </span>
                            </div>
                        @endif
                        <div class="flex justify-between">
                            <span class="text-[var(--ui-muted)]">Erstellt:</span>
                            <span class="font-medium text-[var(--ui-secondary)]">{{ $issue->created_at->format('d.m.Y H:i') }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-[var(--ui-muted)]">Erstellt von:</span>
                            <span class="font-medium text-[var(--ui-secondary)]">{{ $issue->createdBy?->name ?? 'Unbekannt' }}</span>
                        </div>
                    </div>
                </div>
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

    <x-ui-page-container spacing="space-y-6">
        {{-- Header Section --}}
        <div class="bg-white rounded-xl border border-[var(--ui-border)]/60 shadow-sm overflow-hidden">
            <div class="p-6 lg:p-8">
                <div class="flex items-start justify-between gap-4 mb-4">
                    <div class="flex-1 min-w-0">
                        <h1 class="text-3xl font-bold text-[var(--ui-secondary)] mb-4 tracking-tight leading-tight {{ $issue->is_done ? 'line-through opacity-60' : '' }}">{{ $issue->title }}</h1>

                        {{-- Meta Informationen --}}
                        <div class="space-y-2">
                            {{-- Erste Zeile: Board & Slot --}}
                            <div class="flex flex-wrap items-center gap-6 text-sm text-[var(--ui-muted)]">
                                @if($issue->board)
                                    <span class="flex items-center gap-2">
                                        @svg('heroicon-o-view-boards', 'w-4 h-4')
                                        <span>Board: <span class="text-[var(--ui-secondary)]">{{ $issue->board->name }}</span></span>
                                    </span>
                                @endif
                                <span class="flex items-center gap-2">
                                    @svg('heroicon-o-view-columns', 'w-4 h-4')
                                    <span>Slot: <span class="text-[var(--ui-secondary)]">{{ $issue->slot?->name ?? 'Backlog' }}</span></span>
                                </span>
                            </div>

                            {{-- Zweite Zeile: Personen & Details --}}
                            <div class="flex flex-wrap items-center gap-6 text-sm text-[var(--ui-muted)]">
                                @if($issue->createdBy)
                                    <span class="flex items-center gap-2">
                                        @svg('heroicon-o-user-circle', 'w-4 h-4')
                                        <span>Erstellt von: <span class="text-[var(--ui-secondary)]">{{ $issue->createdBy->fullname ?? $issue->createdBy->name }}</span></span>
                                    </span>
                                @endif
                                @if($issue->userInCharge)
                                    <span class="flex items-center gap-2">
                                        @svg('heroicon-o-user', 'w-4 h-4')
                                        <span>Verantwortlich: <span class="text-[var(--ui-secondary)]">{{ $issue->userInCharge->fullname ?? $issue->userInCharge->name }}</span></span>
                                    </span>
                                @endif
                                @if($issue->due_date)
                                    @php
                                        $isOverdue = $issue->due_date->isPast() && !$issue->is_done;
                                        $isToday = $issue->due_date->isToday();
                                        $isTomorrow = $issue->due_date->isTomorrow();
                                        $dueDateColor = $isOverdue ? 'text-[var(--ui-danger)]' : ($isToday || $isTomorrow ? 'text-[var(--ui-warning)]' : 'text-[var(--ui-muted)]');
                                        $dueDateTextColor = $isOverdue ? 'text-[var(--ui-danger)]' : ($isToday || $isTomorrow ? 'text-[var(--ui-warning)]' : 'text-[var(--ui-secondary)]');
                                    @endphp
                                    <span class="flex items-center gap-2">
                                        @svg('heroicon-o-calendar', 'w-4 h-4 ' . $dueDateColor)
                                        <span>Faellig: <span class="{{ $dueDateTextColor }}">{{ $issue->due_date->format('d.m.Y') }}</span></span>
                                    </span>
                                @endif
                                @if($issue->story_points)
                                    <span class="flex items-center gap-2">
                                        @svg('heroicon-o-sparkles', 'w-4 h-4')
                                        <span>Story Points: <span class="text-[var(--ui-secondary)] font-medium">{{ $issue->story_points->points() }} SP</span></span>
                                    </span>
                                @endif
                            </div>

                            {{-- Labels --}}
                            @if(!empty($issue->labels))
                                <div class="flex flex-wrap items-center gap-2 text-sm text-[var(--ui-muted)]">
                                    @svg('heroicon-o-tag', 'w-4 h-4')
                                    @foreach($issue->labels as $label)
                                        <span class="px-2 py-0.5 text-xs rounded-full bg-[var(--ui-primary-5)] text-[var(--ui-primary)] border border-[var(--ui-primary)]/20">{{ $label }}</span>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>

                    {{-- Status Badges --}}
                    <div class="flex flex-col items-end gap-2 flex-shrink-0">
                        @if($issue->is_done)
                            <x-ui-badge variant="success" size="sm">Erledigt</x-ui-badge>
                        @endif
                        @php $priorityValue = $issue->priority instanceof \BackedEnum ? $issue->priority->value : $issue->priority; @endphp
                        @if($priorityValue === 'high')
                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-[var(--ui-danger)]/10 text-[var(--ui-danger)] text-xs font-semibold">
                                @svg('heroicon-o-fire', 'w-3.5 h-3.5')
                                Hoch
                            </span>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- Form Section --}}
        <div class="bg-white rounded-xl border border-[var(--ui-border)]/60 shadow-sm overflow-hidden">
            <div class="p-6 lg:p-8">
                {{-- Grundinformationen --}}
                <div class="mb-8">
                    <h2 class="text-lg font-semibold text-[var(--ui-secondary)] mb-4">Grundinformationen</h2>
                    <x-ui-form-grid :cols="2" :gap="6">
                        <div class="col-span-2">
                            <x-ui-input-text
                                name="title"
                                label="Titel"
                                wire:model.blur="title"
                                wire:change="updateTitle"
                                placeholder="Titel eingeben..."
                                required
                            />
                        </div>

                        {{-- Definition of Done --}}
                        <div class="col-span-2">
                            <div class="mb-4">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-2 mb-1">
                                        <label class="text-sm font-semibold text-[var(--ui-secondary)]">Definition of Done</label>
                                    </div>
                                    @if($criteriaTotal > 0)
                                        <div class="flex items-center gap-2">
                                            <span class="text-xs font-medium text-[var(--ui-muted)]">
                                                {{ $criteriaDone }}/{{ $criteriaTotal }} erledigt
                                            </span>
                                            <div class="w-20 h-1.5 bg-[var(--ui-muted-5)] rounded-full overflow-hidden">
                                                <div
                                                    class="h-full transition-all duration-300 {{ $criteriaDone === $criteriaTotal ? 'bg-[var(--ui-success)]' : 'bg-[var(--ui-primary)]' }}"
                                                    style="width: {{ $criteriaTotal > 0 ? round($criteriaDone / $criteriaTotal * 100) : 0 }}%"
                                                ></div>
                                            </div>
                                        </div>
                                    @endif
                                </div>
                                <p class="text-xs text-[var(--ui-muted)]">Kriterien, die erfuellt sein muessen, damit das Issue als erledigt gilt</p>
                            </div>

                            {{-- Criteria List --}}
                            <div class="space-y-2">
                                @forelse($criteria as $index => $criterion)
                                    <div
                                        class="group flex items-start gap-3 p-3 rounded-lg border border-[var(--ui-border)]/60 bg-[var(--ui-surface)] hover:border-[var(--ui-primary)]/40 transition-all duration-200 {{ ($criterion['done'] ?? false) ? 'bg-[var(--ui-success-5)]' : '' }}"
                                        wire:key="dod-item-{{ $index }}"
                                    >
                                        <button
                                            type="button"
                                            wire:click="toggleCriterion({{ $index }})"
                                            class="flex-shrink-0 w-5 h-5 mt-0.5 rounded border-2 transition-all duration-200 flex items-center justify-center {{ ($criterion['done'] ?? false) ? 'bg-[var(--ui-success)] border-[var(--ui-success)] text-white' : 'border-[var(--ui-border)] hover:border-[var(--ui-primary)]' }}"
                                        >
                                            @if($criterion['done'] ?? false)
                                                @svg('heroicon-s-check', 'w-3 h-3')
                                            @endif
                                        </button>

                                        <span class="flex-1 min-w-0 text-sm {{ ($criterion['done'] ?? false) ? 'line-through text-[var(--ui-muted)]' : 'text-[var(--ui-secondary)]' }}">
                                            {{ $criterion['text'] ?? '' }}
                                        </span>

                                        <button
                                            type="button"
                                            wire:click="removeCriterion({{ $index }})"
                                            class="flex-shrink-0 opacity-0 group-hover:opacity-100 p-1 rounded text-[var(--ui-muted)] hover:text-[var(--ui-danger)] hover:bg-[var(--ui-danger-5)] transition-all duration-200"
                                        >
                                            @svg('heroicon-o-trash', 'w-4 h-4')
                                        </button>
                                    </div>
                                @empty
                                    <div class="text-center py-6 text-[var(--ui-muted)]">
                                        <div class="flex justify-center mb-2">
                                            @svg('heroicon-o-clipboard-document-check', 'w-8 h-8')
                                        </div>
                                        <p class="text-sm">Noch keine DoD-Kriterien definiert</p>
                                    </div>
                                @endforelse
                            </div>

                            {{-- Add Criterion --}}
                            <div class="mt-3">
                                <div
                                    x-data="{ newText: '', isAdding: false }"
                                    class="relative"
                                >
                                    <template x-if="!isAdding">
                                        <button
                                            type="button"
                                            @click="isAdding = true; $nextTick(() => $refs.newInput?.focus())"
                                            class="w-full flex items-center gap-2 p-3 rounded-lg border border-dashed border-[var(--ui-border)]/60 text-[var(--ui-muted)] hover:border-[var(--ui-primary)]/60 hover:text-[var(--ui-primary)] hover:bg-[var(--ui-primary-5)] transition-all duration-200"
                                        >
                                            @svg('heroicon-o-plus', 'w-4 h-4')
                                            <span class="text-sm">DoD-Kriterium hinzufuegen</span>
                                        </button>
                                    </template>

                                    <template x-if="isAdding">
                                        <div class="flex items-center gap-2 p-2 rounded-lg border border-[var(--ui-primary)]/60 bg-[var(--ui-primary-5)]">
                                            <input
                                                type="text"
                                                x-ref="newInput"
                                                x-model="newText"
                                                @keydown.enter.prevent="if(newText.trim()) { $wire.addCriterion(newText); newText = ''; }"
                                                @keydown.escape="isAdding = false; newText = ''"
                                                @blur="if(!newText.trim()) { isAdding = false; }"
                                                class="flex-1 bg-transparent border-none p-1 text-sm focus:ring-0 focus:outline-none text-[var(--ui-secondary)]"
                                                placeholder="Neues Kriterium eingeben..."
                                            />
                                            <button
                                                type="button"
                                                @click="if(newText.trim()) { $wire.addCriterion(newText); newText = ''; } isAdding = false;"
                                                class="flex-shrink-0 p-1 rounded text-[var(--ui-primary)] hover:bg-[var(--ui-primary-10)] transition-colors"
                                            >
                                                @svg('heroicon-o-check', 'w-5 h-5')
                                            </button>
                                            <button
                                                type="button"
                                                @click="isAdding = false; newText = ''"
                                                class="flex-shrink-0 p-1 rounded text-[var(--ui-muted)] hover:text-[var(--ui-danger)] transition-colors"
                                            >
                                                @svg('heroicon-o-x-mark', 'w-5 h-5')
                                            </button>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </div>

                        <div>
                            <div class="mb-2">
                                <label class="text-sm font-semibold text-[var(--ui-secondary)]">Prioritaet</label>
                                <p class="text-xs text-[var(--ui-muted)] mt-1">Bestimmt die Dringlichkeit des Issues.</p>
                            </div>
                            <x-ui-input-select
                                name="priority"
                                label=""
                                :options="[
                                    ['value' => 'low', 'label' => 'Niedrig'],
                                    ['value' => 'normal', 'label' => 'Normal'],
                                    ['value' => 'high', 'label' => 'Hoch'],
                                ]"
                                optionValue="value"
                                optionLabel="label"
                                :nullable="false"
                                wire:model="priority"
                                wire:change="updatePriority($event.target.value)"
                            />
                        </div>
                        <div>
                            <div class="mb-2">
                                <label class="text-sm font-semibold text-[var(--ui-secondary)]">Story Points</label>
                                <p class="text-xs text-[var(--ui-muted)] mt-1">Schaetzung der Komplexitaet und des Aufwands.</p>
                            </div>
                            <x-ui-input-select
                                name="storyPoints"
                                label=""
                                :options="\Platform\Dev\Enums\IssueStoryPoints::cases()"
                                optionValue="value"
                                optionLabel="label"
                                :nullable="true"
                                nullLabel="– Story Points auswaehlen –"
                                wire:model="storyPoints"
                                wire:change="updateStoryPoints"
                            />
                        </div>
                    </x-ui-form-grid>
                </div>

                {{-- Faelligkeit & Verantwortung --}}
                <div class="mb-8 pb-8 border-b border-[var(--ui-border)]/60">
                    <h2 class="text-lg font-semibold text-[var(--ui-secondary)] mb-4">Zuweisung & Faelligkeit</h2>
                    <x-ui-form-grid :cols="2" :gap="6">
                        <div>
                            <div class="mb-2">
                                <label class="text-sm font-semibold text-[var(--ui-secondary)]">Faelligkeitsdatum</label>
                                <p class="text-xs text-[var(--ui-muted)] mt-1">Der Termin, bis zu dem das Issue abgeschlossen sein soll.</p>
                            </div>
                            <input type="date"
                                   wire:model.blur="dueDate"
                                   wire:change="updateDueDate"
                                   class="w-full px-4 py-2.5 text-sm rounded-lg bg-[var(--ui-surface)] border border-[var(--ui-border)]/60 text-[var(--ui-secondary)] focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/20 focus:border-[var(--ui-primary)] transition-all duration-200"
                            />
                        </div>
                        <div>
                            <div class="mb-2">
                                <label class="text-sm font-semibold text-[var(--ui-secondary)]">Verantwortlicher</label>
                                <p class="text-xs text-[var(--ui-muted)] mt-1">Die Person, die fuer die Umsetzung verantwortlich ist.</p>
                            </div>
                            <x-ui-input-select
                                name="userInChargeId"
                                label=""
                                :options="$teamUsers"
                                optionValue="id"
                                optionLabel="name"
                                :nullable="true"
                                nullLabel="– Verantwortlichen auswaehlen –"
                                wire:model="userInChargeId"
                                wire:change="updateUserInCharge($event.target.value)"
                            />
                        </div>
                        <div class="col-span-2">
                            <div class="mb-2">
                                <label class="text-sm font-semibold text-[var(--ui-secondary)]">Board Slot</label>
                                <p class="text-xs text-[var(--ui-muted)] mt-1">Position auf dem Kanban-Board.</p>
                            </div>
                            <x-ui-input-select
                                name="slotId"
                                label=""
                                :options="$boardSlots"
                                optionValue="id"
                                optionLabel="name"
                                :nullable="true"
                                nullLabel="Backlog (kein Slot)"
                                wire:model="slotId"
                                wire:change="updateSlot($event.target.value)"
                            />
                        </div>
                    </x-ui-form-grid>
                </div>

                {{-- Beschreibung --}}
                <div>
                    <div class="mb-4">
                        <label class="text-sm font-semibold text-[var(--ui-secondary)]">Beschreibung</label>
                        <p class="text-xs text-[var(--ui-muted)] mt-1">Zusaetzliche Notizen und Informationen zum Issue</p>
                    </div>
                    <x-ui-input-textarea
                        name="description"
                        label=""
                        wire:model.blur="description"
                        wire:change="updateDescription"
                        :placeholder="empty($description) ? 'Beschreibung hinzufuegen (optional)' : ''"
                        rows="6"
                    />
                </div>

                {{-- Labels --}}
                @if(!empty($issue->labels))
                    <div class="mt-8 pt-8 border-t border-[var(--ui-border)]/60">
                        <div class="flex items-center gap-2 mb-3">
                            @svg('heroicon-o-tag', 'w-4 h-4 text-[var(--ui-muted)]')
                            <label class="text-sm font-semibold text-[var(--ui-secondary)]">Labels</label>
                        </div>
                        <div class="flex items-center gap-1.5 flex-wrap">
                            @foreach($issue->labels as $label)
                                <span class="px-2.5 py-1 text-xs rounded-full bg-[var(--ui-primary-5)] text-[var(--ui-primary)] border border-[var(--ui-primary)]/20 font-medium">{{ $label }}</span>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </x-ui-page-container>
</x-ui-page>
