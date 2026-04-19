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
            <div class="p-4 space-y-4">
                <div class="text-sm text-[var(--ui-muted)]">Letzte Aktivitaeten</div>
                <div class="space-y-3 text-sm">
                    @foreach(($activities ?? []) as $activity)
                        <div class="p-2 rounded border border-[var(--ui-border)]/60 bg-[var(--ui-muted-5)]">
                            <div class="font-medium text-[var(--ui-secondary)] truncate">{{ $activity['title'] ?? 'Aktivitaet' }}</div>
                            <div class="text-[var(--ui-muted)]">{{ $activity['time'] ?? '' }}</div>
                        </div>
                    @endforeach
                </div>
            </div>
        </x-ui-page-sidebar>
    </x-slot>

    <x-ui-page-container spacing="space-y-6">
        {{-- Header with Status Toggle + Inline Title + Priority Badge --}}
        <div class="bg-[var(--ui-surface)] rounded-xl border border-[var(--ui-border)]/60 overflow-hidden">
            <div class="py-4 px-6">
                <div class="d-flex items-center gap-4">
                    {{-- Status Circle --}}
                    <button type="button" wire:click="toggleDone" class="flex-shrink-0 group cursor-pointer">
                        @if($issue->is_done)
                            <div class="w-8 h-8 rounded-full bg-[var(--ui-success)]/15 d-flex items-center justify-center group-hover:bg-[var(--ui-success)]/25 transition-colors">
                                @svg('heroicon-s-check-circle', 'w-5 h-5 text-[var(--ui-success)]')
                            </div>
                        @else
                            <div class="w-8 h-8 rounded-full border-2 border-[var(--ui-border)] d-flex items-center justify-center group-hover:border-[var(--ui-success)] transition-colors">
                                <div class="w-2 h-2 rounded-full bg-[var(--ui-border)] group-hover:bg-[var(--ui-success)] transition-colors"></div>
                            </div>
                        @endif
                    </button>

                    {{-- Inline Title --}}
                    <div class="flex-grow-1 min-w-0">
                        <input type="text"
                               wire:model.blur="title"
                               wire:change="updateTitle"
                               class="text-xl font-bold text-[var(--ui-secondary)] tracking-tight bg-transparent border-none focus:outline-none focus:ring-0 w-full p-0 {{ $issue->is_done ? 'line-through opacity-60' : '' }}"
                               placeholder="Titel eingeben...">
                    </div>

                    {{-- Priority Badge --}}
                    @php $priorityValue = $issue->priority instanceof \BackedEnum ? $issue->priority->value : $issue->priority; @endphp
                    @if($priorityValue === 'high')
                        <span class="flex-shrink-0 inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-[var(--ui-danger)]/10 text-[var(--ui-danger)] text-xs font-semibold">
                            @svg('heroicon-o-fire', 'w-3.5 h-3.5')
                            Hoch
                        </span>
                    @endif
                    @if($issue->is_done)
                        <x-ui-badge variant="success" size="sm">Erledigt</x-ui-badge>
                    @endif
                </div>
            </div>
        </div>

        {{-- Compact Metadata Bar --}}
        <div class="bg-[var(--ui-surface)] rounded-xl border border-[var(--ui-border)]/60 overflow-hidden">
            <div class="px-5 py-3 d-flex items-center gap-4 flex-wrap text-sm">
                {{-- Priority --}}
                <div class="d-flex items-center gap-1.5">
                    @svg('heroicon-o-flag', 'w-4 h-4 text-[var(--ui-muted)]')
                    <select wire:model="priority" wire:change="updatePriority($event.target.value)"
                            class="text-sm text-[var(--ui-secondary)] bg-transparent border-none focus:outline-none focus:ring-0 p-0 cursor-pointer">
                        <option value="low">Niedrig</option>
                        <option value="normal">Normal</option>
                        <option value="high">Hoch</option>
                    </select>
                </div>

                <span class="text-[var(--ui-border)]">|</span>

                {{-- Assignee --}}
                <div class="d-flex items-center gap-1.5">
                    @svg('heroicon-o-user', 'w-4 h-4 text-[var(--ui-muted)]')
                    <select wire:model="userInChargeId" wire:change="updateUserInCharge($event.target.value)"
                            class="text-sm text-[var(--ui-secondary)] bg-transparent border-none focus:outline-none focus:ring-0 p-0 cursor-pointer max-w-[8rem]">
                        <option value="">Nicht zugewiesen</option>
                        @foreach($teamUsers as $user)
                            <option value="{{ $user['id'] }}">{{ $user['name'] }}</option>
                        @endforeach
                    </select>
                </div>

                <span class="text-[var(--ui-border)]">|</span>

                {{-- Due Date --}}
                <div class="d-flex items-center gap-1.5">
                    @svg('heroicon-o-calendar', 'w-4 h-4 text-[var(--ui-muted)]')
                    <input type="date"
                           wire:model.blur="dueDate"
                           wire:change="updateDueDate"
                           class="text-sm text-[var(--ui-secondary)] bg-transparent border-none focus:outline-none focus:ring-0 p-0 cursor-pointer">
                </div>

                <span class="text-[var(--ui-border)]">|</span>

                {{-- Story Points --}}
                <div class="d-flex items-center gap-1.5">
                    <span class="inline-flex items-center justify-center w-4 h-4 rounded-full bg-[var(--ui-primary-5)] text-[9px] font-bold text-[var(--ui-primary)]">SP</span>
                    <select wire:model="storyPoints" wire:change="updateStoryPoints"
                            class="text-sm text-[var(--ui-secondary)] bg-transparent border-none focus:outline-none focus:ring-0 p-0 cursor-pointer">
                        <option value="">–</option>
                        @foreach(\Platform\Dev\Enums\IssueStoryPoints::cases() as $sp)
                            <option value="{{ $sp->value }}">{{ $sp->label() }} ({{ $sp->points() }})</option>
                        @endforeach
                    </select>
                </div>

                <span class="text-[var(--ui-border)]">|</span>

                {{-- Slot --}}
                <div class="d-flex items-center gap-1.5">
                    @svg('heroicon-o-view-columns', 'w-4 h-4 text-[var(--ui-muted)]')
                    <select wire:model="slotId" wire:change="updateSlot($event.target.value)"
                            class="text-sm text-[var(--ui-secondary)] bg-transparent border-none focus:outline-none focus:ring-0 p-0 cursor-pointer">
                        <option value="">Backlog</option>
                        @foreach($boardSlots as $slot)
                            <option value="{{ $slot['id'] ?? $slot->id ?? '' }}">{{ $slot['name'] ?? $slot->name ?? '' }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        {{-- Description --}}
        <div class="bg-[var(--ui-surface)] rounded-xl border border-[var(--ui-border)]/60 overflow-hidden">
            <div class="p-5">
                <div class="d-flex items-center gap-2 mb-3">
                    @svg('heroicon-o-document-text', 'w-4 h-4 text-[var(--ui-muted)]')
                    <h4 class="text-xs font-semibold text-[var(--ui-muted)] uppercase tracking-wider">Beschreibung</h4>
                </div>
                <textarea
                    wire:model.blur="description"
                    wire:change="updateDescription"
                    rows="6"
                    placeholder="Beschreibung hinzufuegen..."
                    class="w-full text-sm text-[var(--ui-secondary)] placeholder-[var(--ui-muted)] bg-[var(--ui-muted-5)] border border-[var(--ui-border)]/30 rounded-lg px-4 py-3 focus:outline-none focus:border-[var(--ui-primary)]/40 resize-y"
                ></textarea>
            </div>
        </div>

        {{-- Acceptance Criteria (DoD) --}}
        <div class="bg-[var(--ui-surface)] rounded-xl border border-[var(--ui-border)]/60 overflow-hidden">
            <div class="p-5">
                <div class="d-flex items-center justify-between mb-4">
                    <div class="d-flex items-center gap-2">
                        @svg('heroicon-o-clipboard-document-check', 'w-4 h-4 text-[var(--ui-muted)]')
                        <h4 class="text-xs font-semibold text-[var(--ui-muted)] uppercase tracking-wider">Definition of Done</h4>
                        @if($criteriaTotal > 0)
                            <span class="text-xs px-1.5 py-0.5 rounded {{ $criteriaDone === $criteriaTotal ? 'bg-green-500/10 text-[var(--ui-success)]' : 'bg-[var(--ui-muted-5)] text-[var(--ui-muted)]' }}">
                                {{ $criteriaDone }}/{{ $criteriaTotal }}
                            </span>
                        @endif
                    </div>
                </div>

                {{-- Criteria List --}}
                @if($criteriaTotal > 0)
                    <div class="space-y-1 mb-4">
                        @foreach($criteria as $index => $criterion)
                            <div class="d-flex items-center gap-3 py-2 px-3 rounded-lg hover:bg-[var(--ui-muted-5)] transition-colors group">
                                <button type="button" wire:click="toggleCriterion({{ $index }})" class="flex-shrink-0 cursor-pointer">
                                    @if($criterion['done'] ?? false)
                                        @svg('heroicon-s-check-circle', 'w-5 h-5 text-[var(--ui-success)]')
                                    @else
                                        @svg('heroicon-o-circle-stack', 'w-5 h-5 text-[var(--ui-muted)]')
                                    @endif
                                </button>
                                <span class="text-sm flex-grow-1 {{ ($criterion['done'] ?? false) ? 'line-through text-[var(--ui-muted)]' : 'text-[var(--ui-secondary)]' }}">
                                    {{ $criterion['text'] ?? '' }}
                                </span>
                                <button type="button"
                                        wire:click="removeCriterion({{ $index }})"
                                        class="flex-shrink-0 opacity-0 group-hover:opacity-100 transition-opacity p-1 rounded hover:bg-[var(--ui-danger)]/10 text-[var(--ui-muted)] hover:text-[var(--ui-danger)]">
                                    @svg('heroicon-o-x-mark', 'w-3.5 h-3.5')
                                </button>
                            </div>
                        @endforeach
                    </div>
                @endif

                {{-- Add Criterion --}}
                <div class="d-flex items-center gap-2">
                    <div class="flex-grow-1">
                        <input type="text"
                               wire:model="newCriterion"
                               wire:keydown.enter="addCriterion"
                               placeholder="Neues Kriterium hinzufuegen..."
                               class="w-full px-3 py-2 text-sm rounded-lg bg-[var(--ui-muted-5)] border border-[var(--ui-border)]/40 text-[var(--ui-secondary)] placeholder-[var(--ui-muted)] focus:outline-none focus:border-[var(--ui-primary)]/40">
                    </div>
                    <x-ui-button variant="secondary-outline" size="sm" wire:click="addCriterion">
                        @svg('heroicon-o-plus', 'w-4 h-4')
                    </x-ui-button>
                </div>
            </div>
        </div>

        {{-- Labels --}}
        @if(!empty($issue->labels))
            <div class="bg-[var(--ui-surface)] rounded-xl border border-[var(--ui-border)]/60 overflow-hidden">
                <div class="p-5">
                    <div class="d-flex items-center gap-2 mb-3">
                        @svg('heroicon-o-tag', 'w-4 h-4 text-[var(--ui-muted)]')
                        <h4 class="text-xs font-semibold text-[var(--ui-muted)] uppercase tracking-wider">Labels</h4>
                    </div>
                    <div class="d-flex items-center gap-1 flex-wrap">
                        @foreach($issue->labels as $label)
                            <span class="px-2 py-0.5 text-xs rounded-full bg-[var(--ui-primary-5)] text-[var(--ui-primary)]">{{ $label }}</span>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif
    </x-ui-page-container>
</x-ui-page>
