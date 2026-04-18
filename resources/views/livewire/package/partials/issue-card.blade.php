@php
    $priorityValue = $issue->priority instanceof \BackedEnum ? $issue->priority->value : $issue->priority;
    $priorityDot = match($priorityValue) {
        'high' => 'bg-red-500',
        'low' => 'bg-gray-400',
        default => 'bg-blue-500',
    };
@endphp
<a href="{{ route('dev.packages.issues.show', [$package, $issue]) }}" wire:navigate
   class="block p-2.5 rounded-lg bg-white dark:bg-[var(--ui-bg)] border border-[var(--ui-border)]/40 hover:border-[var(--ui-primary)]/40 transition-colors">
    <div class="d-flex items-start gap-2">
        <div class="w-2 h-2 rounded-full {{ $priorityDot }} flex-shrink-0 mt-1.5"></div>
        <div class="min-w-0 flex-grow-1">
            <p class="text-xs font-medium text-[var(--ui-secondary)] truncate">{{ $issue->title }}</p>
            <div class="d-flex items-center gap-2 mt-1">
                @if($issue->userInCharge)
                    <span class="text-[10px] text-[var(--ui-muted)]">{{ $issue->userInCharge->name }}</span>
                @endif
                @if($issue->due_date)
                    <span class="text-[10px] text-[var(--ui-muted)]">{{ $issue->due_date->format('d.m.') }}</span>
                @endif
            </div>
        </div>
    </div>
</a>
