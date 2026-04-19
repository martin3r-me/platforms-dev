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
    <div class="relative group/card border-l-2 {{ $priorityValue === 'high' ? 'border-l-[var(--ui-danger)]' : ($priorityValue === 'normal' ? 'border-l-[var(--ui-primary)]' : 'border-l-[var(--ui-border)]') }} -ml-px {{ $isOverdue ? 'bg-[var(--ui-danger)]/[0.03]' : '' }}">
        <!-- Delete Icon -->
        <div class="absolute -top-1 -right-1 opacity-0 group-hover/card:opacity-100 transition-opacity z-10">
            <button
                type="button"
                wire:click.stop.prevent="deleteIssue({{ $issue->id }})"
                wire:confirm="Issue wirklich loeschen?"
                class="p-1 rounded hover:bg-[var(--ui-danger)]/10 text-[var(--ui-muted)] hover:text-[var(--ui-danger)] transition-colors"
                title="Issue loeschen"
            >
                @svg('heroicon-o-trash', 'w-3.5 h-3.5')
            </button>
        </div>

        <!-- Top Row: Priority + Story Points -->
        <div class="d-flex items-center justify-between mb-2">
            <div class="d-flex items-center gap-2">
                @if($priorityValue === 'high')
                    <span class="inline-flex items-center gap-1 text-xs text-[var(--ui-danger)] font-semibold">
                        @svg('heroicon-o-fire','w-3 h-3')
                        <span>Hoch</span>
                    </span>
                @endif
            </div>
            @if($issue->story_points)
                <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-[var(--ui-primary-5)] border border-[var(--ui-primary)]/20 text-[10px] font-bold text-[var(--ui-primary)]">
                    {{ $issue->story_points }}
                </span>
            @endif
        </div>

        <!-- Title -->
        <div class="mb-2">
            <h4 class="text-sm font-medium text-[var(--ui-secondary)] m-0 {{ $isDone ? 'line-through text-[var(--ui-muted)]' : '' }}">
                {{ $issue->title }}
            </h4>
        </div>

        <!-- Description (truncated) -->
        @if($issue->description)
            <div class="text-xs text-[var(--ui-muted)] mb-2 line-clamp-2">
                {{ Str::limit($issue->description, 80) }}
            </div>
        @endif

        <!-- DoD Progress -->
        @if($criteriaTotal > 0)
            <div class="mb-2">
                <div class="d-flex items-center gap-2">
                    @svg('heroicon-o-clipboard-document-check', 'w-3 h-3 text-[var(--ui-muted)]')
                    <div class="flex-grow-1 h-1 rounded-full bg-[var(--ui-border)]/40 overflow-hidden">
                        <div class="h-full rounded-full {{ $criteriaDone === $criteriaTotal ? 'bg-[var(--ui-success)]' : 'bg-[var(--ui-primary)]' }} transition-all"
                             style="width: {{ round($criteriaDone / $criteriaTotal * 100) }}%"></div>
                    </div>
                    <span class="text-[10px] text-[var(--ui-muted)] font-medium">{{ $criteriaDone }}/{{ $criteriaTotal }}</span>
                </div>
            </div>
        @endif

        <!-- Labels -->
        @if(!empty($issue->labels))
            <div class="mb-2 flex flex-wrap gap-1">
                @foreach($issue->labels as $label)
                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] leading-tight bg-[var(--ui-primary-5)] text-[var(--ui-primary)]">{{ $label }}</span>
                @endforeach
            </div>
        @endif

        <!-- Footer: Assignee + Due Date -->
        <div class="d-flex items-center justify-between text-xs text-[var(--ui-muted)] mt-2 pt-2 border-t border-[var(--ui-border)]/20">
            <div class="d-flex items-center gap-1.5 min-w-0">
                @php $userInCharge = $issue->userInCharge ?? null; @endphp
                @if($userInCharge)
                    @if($userInCharge->avatar)
                        <img src="{{ $userInCharge->avatar }}" alt="" class="w-5 h-5 rounded-full object-cover ring-1 ring-[var(--ui-border)]/40">
                    @else
                        <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-[var(--ui-primary-5)] text-[10px] font-semibold text-[var(--ui-primary)]">
                            {{ mb_strtoupper(mb_substr($userInCharge->name ?? 'U', 0, 1)) }}
                        </span>
                    @endif
                    <span class="truncate max-w-[6rem]">{{ $userInCharge->name }}</span>
                @else
                    <span class="text-[var(--ui-muted)]/60">Nicht zugewiesen</span>
                @endif
            </div>
            @if($issue->due_date)
                <span class="flex items-center gap-1 flex-shrink-0 {{ $isOverdue ? 'text-[var(--ui-danger)] font-medium' : '' }}">
                    @svg('heroicon-o-calendar', 'w-3 h-3')
                    {{ $issue->due_date->format('d.m.') }}
                </span>
            @endif
        </div>
    </div>
</x-ui-kanban-card>
