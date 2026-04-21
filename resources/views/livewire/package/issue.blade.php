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
            <div class="p-6 space-y-5">
                {{-- Status Toggle --}}
                <div>
                    <button type="button" wire:click="toggleDone" class="w-full text-left flex items-center justify-between py-2 px-3 rounded-md border border-gray-200 hover:bg-gray-50 transition-colors cursor-pointer">
                        <div class="flex items-center gap-2">
                            @if($issue->is_done)
                                @svg('heroicon-s-check-circle', 'w-4 h-4 text-purple-500')
                            @else
                                <span class="w-4 h-4 rounded-full border-2 border-green-500 inline-block"></span>
                            @endif
                            <span class="text-sm text-gray-700">Status</span>
                        </div>
                        <span class="inline-flex items-center gap-1.5 px-2 py-0.5 text-xs font-medium rounded-full {{ $issue->is_done ? 'bg-purple-100 text-purple-700' : 'bg-green-100 text-green-700' }}">
                            <span class="w-1.5 h-1.5 rounded-full {{ $issue->is_done ? 'bg-purple-500' : 'bg-green-500' }}"></span>
                            {{ $issue->is_done ? 'Closed' : 'Open' }}
                        </span>
                    </button>
                </div>

                {{-- Story Points --}}
                @if($issue->story_points)
                    <div class="flex items-center justify-between py-2 px-3 rounded-md bg-gray-50 border border-gray-200">
                        <span class="text-sm text-gray-700">Story Points</span>
                        <span class="text-lg font-bold text-gray-900">{{ $issue->story_points->points() }} <span class="text-xs font-medium text-gray-500">{{ $issue->story_points->label() }}</span></span>
                    </div>
                @endif

                {{-- DoD Progress --}}
                @if($criteriaTotal > 0)
                    <div class="py-2 px-3 rounded-md bg-gray-50 border border-gray-200">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-sm text-gray-700">DoD Progress</span>
                            <span class="text-sm font-semibold {{ $criteriaDone === $criteriaTotal ? 'text-green-600' : 'text-gray-900' }}">{{ $criteriaDone }}/{{ $criteriaTotal }}</span>
                        </div>
                        <div class="w-full h-2 rounded-full bg-gray-200 overflow-hidden">
                            <div class="h-full rounded-full {{ $criteriaDone === $criteriaTotal ? 'bg-green-500' : 'bg-blue-500' }} transition-all"
                                 style="width: {{ $criteriaTotal > 0 ? round($criteriaDone / $criteriaTotal * 100) : 0 }}%"></div>
                        </div>
                    </div>
                @endif

                {{-- Issue Info --}}
                <div>
                    <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-3">Details</h3>
                    <div class="space-y-2 text-sm">
                        @php
                            $priorityValue = $issue->priority instanceof \BackedEnum ? $issue->priority->value : $issue->priority;
                        @endphp
                        <div class="flex justify-between">
                            <span class="text-gray-500">Priority</span>
                            <span class="font-medium d-flex items-center gap-1 {{ $priorityValue === 'high' ? 'text-red-600' : 'text-gray-900' }}">
                                @if($priorityValue === 'high') <span class="w-2 h-2 rounded-full bg-red-500"></span> @endif
                                {{ ['low' => 'Low', 'normal' => 'Normal', 'high' => 'High'][$priorityValue] ?? $priorityValue }}
                            </span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">Board</span>
                            <span class="font-medium text-gray-900">{{ $issue->board?->name ?? '-' }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">Column</span>
                            <span class="font-medium text-gray-900">{{ $issue->slot?->name ?? 'Backlog' }}</span>
                        </div>
                        @if($issue->userInCharge)
                            <div class="flex justify-between">
                                <span class="text-gray-500">Assignee</span>
                                <span class="font-medium text-gray-900">{{ $issue->userInCharge->name }}</span>
                            </div>
                        @endif
                        @if($issue->due_date)
                            <div class="flex justify-between">
                                <span class="text-gray-500">Due date</span>
                                <span class="font-medium {{ $issue->due_date->isPast() && !$issue->is_done ? 'text-red-600' : 'text-gray-900' }}">
                                    {{ $issue->due_date->format('d.m.Y') }}
                                </span>
                            </div>
                        @endif
                        <div class="flex justify-between">
                            <span class="text-gray-500">Created</span>
                            <span class="font-medium text-gray-900">{{ $issue->created_at->format('d.m.Y H:i') }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">Author</span>
                            <span class="font-medium text-gray-900">{{ $issue->createdBy?->name ?? 'Unknown' }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </x-ui-page-sidebar>
    </x-slot>

    <x-slot name="activity">
        <x-ui-page-sidebar title="Aktivitaeten" width="w-80" :defaultOpen="false" storeKey="activityOpen" side="right">
            <div class="p-6">
                <h3 class="text-xs font-semibold uppercase tracking-wider text-gray-500 mb-4">Activity</h3>
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

    <x-ui-page-container spacing="space-y-6">
        {{-- Header Section --}}
        <div class="bg-white rounded-md border border-gray-200 overflow-hidden">
            <div class="p-6 lg:p-8">
                <div class="flex items-start justify-between gap-4 mb-4">
                    <div class="flex-1 min-w-0">
                        <h1 class="text-2xl font-bold text-gray-900 mb-4 tracking-tight leading-tight {{ $issue->is_done ? 'line-through opacity-60' : '' }}">{{ $issue->title }}</h1>

                        {{-- Meta --}}
                        <div class="space-y-2">
                            <div class="flex flex-wrap items-center gap-4 text-sm text-gray-500">
                                @if($issue->board)
                                    <span class="flex items-center gap-1.5">
                                        @svg('heroicon-o-view-columns', 'w-4 h-4')
                                        <span>{{ $issue->board->name }}</span>
                                    </span>
                                @endif
                                <span class="flex items-center gap-1.5">
                                    @svg('heroicon-o-rectangle-stack', 'w-4 h-4')
                                    <span>{{ $issue->slot?->name ?? 'Backlog' }}</span>
                                </span>
                            </div>

                            <div class="flex flex-wrap items-center gap-4 text-sm text-gray-500">
                                @if($issue->createdBy)
                                    <span class="flex items-center gap-1.5">
                                        @svg('heroicon-o-user-circle', 'w-4 h-4')
                                        <span>{{ $issue->createdBy->fullname ?? $issue->createdBy->name }}</span>
                                    </span>
                                @endif
                                @if($issue->userInCharge)
                                    <span class="flex items-center gap-1.5">
                                        @svg('heroicon-o-user', 'w-4 h-4')
                                        <span class="text-gray-900 font-medium">{{ $issue->userInCharge->fullname ?? $issue->userInCharge->name }}</span>
                                    </span>
                                @endif
                                @if($issue->due_date)
                                    @php
                                        $isOverdue = $issue->due_date->isPast() && !$issue->is_done;
                                        $isToday = $issue->due_date->isToday();
                                        $isTomorrow = $issue->due_date->isTomorrow();
                                    @endphp
                                    <span class="flex items-center gap-1.5 {{ $isOverdue ? 'text-red-600' : ($isToday || $isTomorrow ? 'text-yellow-600' : '') }}">
                                        @svg('heroicon-o-calendar', 'w-4 h-4')
                                        <span>{{ $issue->due_date->format('d.m.Y') }}</span>
                                    </span>
                                @endif
                                @if($issue->story_points)
                                    <span class="flex items-center gap-1.5">
                                        @svg('heroicon-o-sparkles', 'w-4 h-4')
                                        <span class="font-medium text-gray-900">{{ $issue->story_points->points() }} SP</span>
                                    </span>
                                @endif
                            </div>

                            {{-- Labels --}}
                            @if(!empty($issue->labels))
                                <div class="flex flex-wrap items-center gap-2 mt-1">
                                    @foreach($issue->labels as $label)
                                        <span class="px-2.5 py-0.5 text-xs font-medium rounded-full bg-blue-100 text-blue-700 border border-blue-200">{{ $label }}</span>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>

                    {{-- Status Badges --}}
                    <div class="flex flex-col items-end gap-2 flex-shrink-0">
                        @if($issue->is_done)
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-purple-100 text-purple-700 text-xs font-semibold border border-purple-200">
                                @svg('heroicon-s-check-circle', 'w-3.5 h-3.5')
                                Closed
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-green-100 text-green-700 text-xs font-semibold border border-green-200">
                                <span class="w-1.5 h-1.5 rounded-full bg-green-500"></span>
                                Open
                            </span>
                        @endif
                        @php $priorityValue = $issue->priority instanceof \BackedEnum ? $issue->priority->value : $issue->priority; @endphp
                        @if($priorityValue === 'high')
                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-red-100 text-red-700 text-xs font-semibold border border-red-200">
                                <span class="w-1.5 h-1.5 rounded-full bg-red-500"></span>
                                High
                            </span>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- Form Section --}}
        <div class="bg-white rounded-md border border-gray-200 overflow-hidden">
            <div class="p-6 lg:p-8">
                {{-- Grundinformationen --}}
                <div class="mb-8">
                    <h2 class="text-base font-semibold text-gray-900 mb-4 pb-2 border-b border-gray-200">Grundinformationen</h2>
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
                                        <label class="text-sm font-semibold text-gray-900">Definition of Done</label>
                                    </div>
                                    @if($criteriaTotal > 0)
                                        <div class="flex items-center gap-2">
                                            <span class="text-xs font-medium text-gray-500">
                                                {{ $criteriaDone }}/{{ $criteriaTotal }} done
                                            </span>
                                            <div class="w-20 h-1.5 bg-gray-200 rounded-full overflow-hidden">
                                                <div
                                                    class="h-full transition-all duration-300 {{ $criteriaDone === $criteriaTotal ? 'bg-green-500' : 'bg-blue-500' }}"
                                                    style="width: {{ $criteriaTotal > 0 ? round($criteriaDone / $criteriaTotal * 100) : 0 }}%"
                                                ></div>
                                            </div>
                                        </div>
                                    @endif
                                </div>
                                <p class="text-xs text-gray-500">Kriterien, die erfuellt sein muessen, damit das Issue als erledigt gilt</p>
                            </div>

                            {{-- Criteria List --}}
                            <div class="space-y-2">
                                @forelse($criteria as $index => $criterion)
                                    <div
                                        class="group flex items-start gap-3 p-3 rounded-md border border-gray-200 bg-white hover:border-gray-300 transition-all duration-200 {{ ($criterion['done'] ?? false) ? 'bg-green-50/50' : '' }}"
                                        wire:key="dod-item-{{ $index }}"
                                    >
                                        <button
                                            type="button"
                                            wire:click="toggleCriterion({{ $index }})"
                                            class="flex-shrink-0 w-5 h-5 mt-0.5 rounded border-2 transition-all duration-200 flex items-center justify-center {{ ($criterion['done'] ?? false) ? 'bg-green-500 border-green-500 text-white' : 'border-gray-300 hover:border-green-500' }}"
                                        >
                                            @if($criterion['done'] ?? false)
                                                @svg('heroicon-s-check', 'w-3 h-3')
                                            @endif
                                        </button>

                                        <span class="flex-1 min-w-0 text-sm {{ ($criterion['done'] ?? false) ? 'line-through text-gray-400' : 'text-gray-900' }}">
                                            {{ $criterion['text'] ?? '' }}
                                        </span>

                                        <button
                                            type="button"
                                            wire:click="removeCriterion({{ $index }})"
                                            class="flex-shrink-0 opacity-0 group-hover:opacity-100 p-1 rounded text-gray-400 hover:text-red-600 hover:bg-red-50 transition-all duration-200"
                                        >
                                            @svg('heroicon-o-trash', 'w-4 h-4')
                                        </button>
                                    </div>
                                @empty
                                    <div class="text-center py-6 text-gray-400">
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
                                            class="w-full flex items-center gap-2 p-3 rounded-md border border-dashed border-gray-300 text-gray-500 hover:border-green-400 hover:text-green-600 hover:bg-green-50/50 transition-all duration-200"
                                        >
                                            @svg('heroicon-o-plus', 'w-4 h-4')
                                            <span class="text-sm">DoD-Kriterium hinzufuegen</span>
                                        </button>
                                    </template>

                                    <template x-if="isAdding">
                                        <div class="flex items-center gap-2 p-2 rounded-md border border-blue-300 bg-blue-50">
                                            <input
                                                type="text"
                                                x-ref="newInput"
                                                x-model="newText"
                                                @keydown.enter.prevent="if(newText.trim()) { $wire.addCriterion(newText); newText = ''; }"
                                                @keydown.escape="isAdding = false; newText = ''"
                                                @blur="if(!newText.trim()) { isAdding = false; }"
                                                class="flex-1 bg-transparent border-none p-1 text-sm focus:ring-0 focus:outline-none text-gray-900"
                                                placeholder="Neues Kriterium eingeben..."
                                            />
                                            <button
                                                type="button"
                                                @click="if(newText.trim()) { $wire.addCriterion(newText); newText = ''; } isAdding = false;"
                                                class="flex-shrink-0 p-1 rounded text-green-600 hover:bg-green-100 transition-colors"
                                            >
                                                @svg('heroicon-o-check', 'w-5 h-5')
                                            </button>
                                            <button
                                                type="button"
                                                @click="isAdding = false; newText = ''"
                                                class="flex-shrink-0 p-1 rounded text-gray-400 hover:text-red-600 transition-colors"
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
                                <label class="text-sm font-semibold text-gray-900">Prioritaet</label>
                                <p class="text-xs text-gray-500 mt-1">Bestimmt die Dringlichkeit des Issues.</p>
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
                                <label class="text-sm font-semibold text-gray-900">Story Points</label>
                                <p class="text-xs text-gray-500 mt-1">Schaetzung der Komplexitaet und des Aufwands.</p>
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
                <div class="mb-8 pb-8 border-b border-gray-200">
                    <h2 class="text-base font-semibold text-gray-900 mb-4 pb-2 border-b border-gray-200">Zuweisung & Faelligkeit</h2>
                    <x-ui-form-grid :cols="2" :gap="6">
                        <div>
                            <div class="mb-2">
                                <label class="text-sm font-semibold text-gray-900">Faelligkeitsdatum</label>
                                <p class="text-xs text-gray-500 mt-1">Der Termin, bis zu dem das Issue abgeschlossen sein soll.</p>
                            </div>
                            <input type="date"
                                   wire:model.blur="dueDate"
                                   wire:change="updateDueDate"
                                   class="w-full px-4 py-2.5 text-sm rounded-md bg-white border border-gray-300 text-gray-900 focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all duration-200"
                            />
                        </div>
                        <div>
                            <div class="mb-2">
                                <label class="text-sm font-semibold text-gray-900">Verantwortlicher</label>
                                <p class="text-xs text-gray-500 mt-1">Die Person, die fuer die Umsetzung verantwortlich ist.</p>
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
                                <label class="text-sm font-semibold text-gray-900">Board Slot</label>
                                <p class="text-xs text-gray-500 mt-1">Position auf dem Kanban-Board.</p>
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
                        <label class="text-sm font-semibold text-gray-900">Beschreibung</label>
                        <p class="text-xs text-gray-500 mt-1">Zusaetzliche Notizen und Informationen zum Issue</p>
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
                    <div class="mt-8 pt-8 border-t border-gray-200">
                        <div class="flex items-center gap-2 mb-3">
                            @svg('heroicon-o-tag', 'w-4 h-4 text-gray-400')
                            <label class="text-sm font-semibold text-gray-900">Labels</label>
                        </div>
                        <div class="flex items-center gap-1.5 flex-wrap">
                            @foreach($issue->labels as $label)
                                <span class="px-2.5 py-1 text-xs rounded-full bg-blue-100 text-blue-700 border border-blue-200 font-medium">{{ $label }}</span>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </x-ui-page-container>
</x-ui-page>
