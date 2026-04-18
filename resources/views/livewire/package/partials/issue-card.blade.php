@php
    $isDone = $issue->is_done ?? false;
    $priorityValue = $issue->priority instanceof \BackedEnum ? $issue->priority->value : $issue->priority;
@endphp
<x-ui-kanban-card
    :title="''"
    :sortable-id="$issue->id"
    :href="route('dev.packages.issues.show', [$package, $issue])"
>
    <div class="relative group/card">
        <!-- Lösch-Icon -->
        <div class="absolute -top-1 -right-1 opacity-0 group-hover/card:opacity-100 transition-opacity z-10">
            <button
                type="button"
                wire:click.stop.prevent="deleteIssue({{ $issue->id }})"
                wire:confirm="Issue wirklich löschen?"
                class="p-1 rounded hover:bg-[var(--ui-danger)]/10 text-[var(--ui-muted)] hover:text-[var(--ui-danger)] transition-colors"
                title="Issue löschen"
            >
                @svg('heroicon-o-trash', 'w-3.5 h-3.5')
            </button>
        </div>

    <!-- Priorität (oben in eigene Zeile) -->
    @if($priorityValue === 'high')
        <div class="mb-3 flex items-start gap-2">
            <span class="inline-flex items-start gap-1 text-xs text-[var(--ui-danger)] font-semibold">
                @svg('heroicon-o-fire','w-3 h-3 mt-0.5')
                <span>Hoch</span>
            </span>
        </div>
    @endif

    <!-- Verantwortlicher (eigene Zeile) -->
    @php
        $userInCharge = $issue->userInCharge ?? null;
        $initials = $userInCharge ? mb_strtoupper(mb_substr($userInCharge->name ?? $userInCharge->email ?? 'U', 0, 1)) : null;
    @endphp
    @if($userInCharge)
        <div class="mb-3">
            <span class="inline-flex items-start gap-1 text-xs text-[var(--ui-muted)] min-w-0">
                @if($userInCharge->avatar)
                    <img src="{{ $userInCharge->avatar }}" alt="{{ $userInCharge->name ?? $userInCharge->email }}" class="w-3.5 h-3.5 rounded-full object-cover mt-0.5">
                @else
                    <span class="inline-flex items-center justify-center w-3.5 h-3.5 rounded-full bg-[var(--ui-muted-5)] border border-[var(--ui-border)]/40 text-[10px] text-[var(--ui-muted)] mt-0.5">{{ $initials }}</span>
                @endif
                <span class="truncate max-w-[7rem]">{{ $userInCharge->name ?? $userInCharge->email }}</span>
            </span>
        </div>
    @endif

    <!-- Titel (durchgestrichen wenn erledigt) -->
    <div class="mb-4">
        <h4 class="text-sm font-medium text-[var(--ui-secondary)] m-0 {{ $isDone ? 'line-through text-[var(--ui-muted)]' : '' }}">
            {{ $issue->title }}
        </h4>
    </div>

    <!-- Labels -->
    @if(!empty($issue->labels))
        <div class="mb-3 flex flex-wrap gap-1">
            @foreach($issue->labels as $label)
                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] leading-tight bg-[var(--ui-muted-10)] text-[var(--ui-muted)]">{{ $label }}</span>
            @endforeach
        </div>
    @endif

    <!-- Meta: Board -->
    @if($issue->board)
        <div class="mb-3">
            <span class="inline-flex items-start gap-1 text-xs text-[var(--ui-muted)] min-w-0">
                @svg('heroicon-o-rectangle-stack','w-2.5 h-2.5 mt-0.5')
                <span class="truncate max-w-[9rem]">{{ $issue->board->name }}</span>
            </span>
        </div>
    @endif

    <!-- Eingang -->
    @if($issue->created_at)
        <div class="mb-3">
            <span class="inline-flex items-start gap-1 text-xs text-[var(--ui-muted)]">
                @svg('heroicon-o-clock','w-3 h-3 mt-0.5')
                <span>{{ $issue->created_at->format('d.m.Y H:i') }}</span>
            </span>
        </div>
    @endif

    <!-- Fällig -->
    @if($issue->due_date)
        <div class="mb-3">
            <span class="inline-flex items-start gap-1 text-xs {{ $issue->due_date->isPast() && !$isDone ? 'text-[var(--ui-danger)]' : 'text-[var(--ui-muted)]' }}">
                @svg('heroicon-o-calendar', 'w-3 h-3 mt-0.5')
                <span>{{ $issue->due_date->format('d.m.Y') }}</span>
            </span>
        </div>
    @endif

    <!-- Description (truncated) -->
    @if($issue->description)
        <div class="text-xs text-[var(--ui-muted)] my-1.5 mb-3 line-clamp-2">
            {{ Str::limit($issue->description, 80) }}
        </div>
    @endif

    </div>
</x-ui-kanban-card>
