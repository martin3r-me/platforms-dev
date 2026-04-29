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
    <div class="relative group/card p-0.5">
        <!-- Delete Icon -->
        <div class="absolute -top-1.5 -right-1.5 opacity-0 group-hover/card:opacity-100 transition-opacity z-10">
            <button
                type="button"
                wire:click.stop.prevent="deleteIssue({{ $issue->id }})"
                wire:confirm="Issue wirklich loeschen?"
                class="p-1 rounded-md bg-white border border-gray-200 text-gray-400 hover:text-red-500 hover:border-red-300 transition-colors shadow-sm"
                title="Issue loeschen"
            >
                @svg('heroicon-o-trash', 'w-3 h-3')
            </button>
        </div>

        <!-- Title -->
        <div class="mb-2">
            <h4 class="text-xs font-medium text-gray-900 m-0 leading-snug {{ $isDone ? 'line-through text-gray-400' : '' }}">
                {{ $issue->title }}
            </h4>
        </div>

        <!-- Labels -->
        @if(!empty($issue->labels))
            <div class="mb-2.5 flex flex-wrap gap-1">
                @foreach($issue->labels as $label)
                    <span class="inline-block px-2 py-px rounded-full text-[10px] font-medium leading-relaxed bg-blue-100 text-blue-800">{{ $label }}</span>
                @endforeach
            </div>
        @endif

        <!-- DoD Progress -->
        @if($criteriaTotal > 0)
            <div class="mb-2.5">
                <div class="d-flex items-center gap-1.5">
                    @svg('heroicon-o-clipboard-document-check', 'w-3 h-3 text-gray-400')
                    <div class="flex-grow-1 h-[5px] rounded-full bg-gray-100 overflow-hidden">
                        <div class="h-full rounded-full {{ $criteriaDone === $criteriaTotal ? 'bg-green-500' : 'bg-blue-500' }} transition-all"
                             style="width: {{ round($criteriaDone / $criteriaTotal * 100) }}%"></div>
                    </div>
                    <span class="text-[10px] text-gray-500 tabular-nums">{{ $criteriaDone }}/{{ $criteriaTotal }}</span>
                </div>
            </div>
        @endif

        <!-- Footer -->
        <div class="d-flex items-center justify-between text-[11px] text-gray-500 mt-2.5 pt-2 border-t border-gray-100">
            <div class="d-flex items-center gap-2 min-w-0">
                {{-- Priority --}}
                @if($priorityValue === 'high')
                    <span class="inline-flex items-center gap-0.5 text-red-600 font-medium">
                        <span class="w-2 h-2 rounded-full bg-red-500"></span>
                    </span>
                @elseif($priorityValue === 'normal')
                    <span class="w-2 h-2 rounded-full bg-yellow-400"></span>
                @else
                    <span class="w-2 h-2 rounded-full bg-gray-300"></span>
                @endif

                {{-- Assignee --}}
                @php $userInCharge = $issue->userInCharge ?? null; @endphp
                @if($userInCharge)
                    @if($userInCharge->avatar)
                        <img src="{{ $userInCharge->avatar }}" alt="" class="w-4 h-4 rounded-full object-cover">
                    @else
                        <span class="inline-flex items-center justify-center w-4 h-4 rounded-full bg-gray-200 text-[9px] font-medium text-gray-600">
                            {{ mb_strtoupper(mb_substr($userInCharge->name ?? 'U', 0, 1)) }}
                        </span>
                    @endif
                @endif
            </div>

            <div class="d-flex items-center gap-2">
                {{-- Story Points --}}
                @if($issue->story_points)
                    <span class="inline-flex items-center justify-center px-1.5 py-px rounded bg-gray-100 text-[10px] font-medium text-gray-600 tabular-nums">
                        {{ $issue->story_points->points() }}
                    </span>
                @endif

                {{-- Due Date --}}
                @if($issue->due_date)
                    <span class="flex items-center gap-0.5 {{ $isOverdue ? 'text-red-600 font-medium' : '' }}">
                        @svg('heroicon-o-calendar', 'w-3 h-3')
                        {{ $issue->due_date->format('d.m.') }}
                    </span>
                @endif

                {{-- Created At --}}
                @if($issue->created_at)
                    <span class="flex items-center gap-0.5 text-gray-400" title="{{ $issue->created_at->format('d.m.Y H:i') }}">
                        @svg('heroicon-o-clock', 'w-3 h-3')
                        {{ $issue->created_at->format('d.m.') }}
                    </span>
                @endif
            </div>
        </div>
    </div>
</x-ui-kanban-card>
