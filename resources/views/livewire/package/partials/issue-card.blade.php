@php
    $isDone = $issue->is_done ?? false;
    $priorityValue = $issue->priority instanceof \BackedEnum ? $issue->priority->value : $issue->priority;
    $criteria = $issue->acceptance_criteria ?? [];
    $criteriaTotal = count($criteria);
    $criteriaDone = collect($criteria)->where('done', true)->count();
    $isOverdue = $issue->due_date && $issue->due_date->isPast() && !$isDone;
@endphp
<x-ui-kanban-card
    :title="''"
    :sortable-id="$issue->id"
    :href="route('dev.packages.issues.show', [$package, $issue])"
>
    <div class="relative group/card {{ $isOverdue ? 'bg-red-50/50' : '' }}">
        <!-- Delete Icon -->
        <div class="absolute -top-1 -right-1 opacity-0 group-hover/card:opacity-100 transition-opacity z-10">
            <button
                type="button"
                wire:click.stop.prevent="deleteIssue({{ $issue->id }})"
                wire:confirm="Issue wirklich loeschen?"
                class="p-1 rounded hover:bg-red-50 text-gray-400 hover:text-red-600 transition-colors"
                title="Issue loeschen"
            >
                @svg('heroicon-o-trash', 'w-3.5 h-3.5')
            </button>
        </div>

        <!-- Top Row: Priority Dot + Story Points -->
        <div class="d-flex items-center justify-between mb-2">
            <div class="d-flex items-center gap-2">
                @if($priorityValue === 'high')
                    <span class="inline-flex items-center gap-1 text-xs text-red-600 font-semibold">
                        <span class="w-2 h-2 rounded-full bg-red-500"></span>
                        Hoch
                    </span>
                @elseif($priorityValue === 'normal')
                    <span class="inline-flex items-center gap-1 text-xs text-gray-500">
                        <span class="w-2 h-2 rounded-full bg-blue-400"></span>
                    </span>
                @else
                    <span class="inline-flex items-center gap-1 text-xs text-gray-400">
                        <span class="w-2 h-2 rounded-full bg-gray-300"></span>
                    </span>
                @endif
            </div>
            @if($issue->story_points)
                <span class="inline-flex items-center justify-center min-w-5 h-5 px-1.5 rounded-full bg-gray-100 text-[10px] font-bold text-gray-600" title="{{ $issue->story_points->label() }}">
                    {{ $issue->story_points->points() }}
                </span>
            @endif
        </div>

        <!-- Title -->
        <div class="mb-2">
            <h4 class="text-sm font-medium text-gray-900 m-0 {{ $isDone ? 'line-through text-gray-400' : '' }}">
                {{ $issue->title }}
            </h4>
        </div>

        <!-- Description (truncated) -->
        @if($issue->description)
            <div class="text-xs text-gray-500 mb-2 line-clamp-2">
                {{ Str::limit($issue->description, 80) }}
            </div>
        @endif

        <!-- DoD Progress -->
        @if($criteriaTotal > 0)
            <div class="mb-2">
                <div class="d-flex items-center gap-2">
                    @svg('heroicon-o-clipboard-document-check', 'w-3 h-3 text-gray-400')
                    <div class="flex-grow-1 h-1 rounded-full bg-gray-200 overflow-hidden">
                        <div class="h-full rounded-full {{ $criteriaDone === $criteriaTotal ? 'bg-green-500' : 'bg-blue-500' }} transition-all"
                             style="width: {{ round($criteriaDone / $criteriaTotal * 100) }}%"></div>
                    </div>
                    <span class="text-[10px] text-gray-500 font-medium">{{ $criteriaDone }}/{{ $criteriaTotal }}</span>
                </div>
            </div>
        @endif

        <!-- Labels -->
        @if(!empty($issue->labels))
            <div class="mb-2 flex flex-wrap gap-1">
                @foreach($issue->labels as $label)
                    <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-[10px] leading-tight font-medium bg-blue-100 text-blue-700">{{ $label }}</span>
                @endforeach
            </div>
        @endif

        <!-- Footer: Assignee + Due Date -->
        <div class="d-flex items-center justify-between text-xs text-gray-500 mt-2 pt-2 border-t border-gray-100">
            <div class="d-flex items-center gap-1.5 min-w-0">
                @php $userInCharge = $issue->userInCharge ?? null; @endphp
                @if($userInCharge)
                    @if($userInCharge->avatar)
                        <img src="{{ $userInCharge->avatar }}" alt="" class="w-5 h-5 rounded-full object-cover">
                    @else
                        <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-gray-200 text-[10px] font-medium text-gray-600">
                            {{ mb_strtoupper(mb_substr($userInCharge->name ?? 'U', 0, 1)) }}
                        </span>
                    @endif
                    <span class="truncate max-w-[6rem]">{{ $userInCharge->name }}</span>
                @else
                    <span class="text-gray-400">Nicht zugewiesen</span>
                @endif
            </div>
            @if($issue->due_date)
                <span class="flex items-center gap-1 flex-shrink-0 {{ $isOverdue ? 'text-red-600 font-medium' : '' }}">
                    @svg('heroicon-o-calendar', 'w-3 h-3')
                    {{ $issue->due_date->format('d.m.') }}
                </span>
            @endif
        </div>
    </div>
</x-ui-kanban-card>
