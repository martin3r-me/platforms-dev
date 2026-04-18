<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="{{ $board->name }}" icon="heroicon-o-view-columns" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Dev', 'href' => route('dev.dashboard'), 'icon' => 'code-bracket'],
            ['label' => $package->name, 'href' => route('dev.packages.show', $package)],
            ['label' => $board->name],
        ]" />
    </x-slot>

    <x-ui-page-container>
        <div class="space-y-4">
            {{-- Board Header --}}
            <div class="d-flex items-center justify-between">
                <div>
                    <h1 class="text-lg font-bold text-[var(--ui-secondary)]">{{ $board->name }}</h1>
                    @if($board->description)
                        <p class="text-xs text-[var(--ui-muted)]">{{ $board->description }}</p>
                    @endif
                </div>
                <div class="d-flex items-center gap-2">
                    <span class="text-xs text-[var(--ui-muted)]">{{ $closedCount }} erledigt</span>
                </div>
            </div>

            {{-- Quick Add --}}
            <div class="d-flex items-center gap-2">
                <div class="flex-grow-1">
                    <input
                        wire:model="newIssueTitle"
                        wire:keydown.enter="createIssue"
                        type="text"
                        placeholder="Neues Issue erstellen..."
                        class="w-full px-3 py-2 text-sm rounded-lg bg-[var(--ui-muted-5)] border border-[var(--ui-border)]/40 text-[var(--ui-secondary)] placeholder-[var(--ui-muted)] focus:outline-none focus:border-[var(--ui-primary)]/40"
                    />
                </div>
                <x-ui-button variant="primary" size="sm" wire:click="createIssue">
                    @svg('heroicon-o-plus', 'w-4 h-4')
                </x-ui-button>
            </div>

            {{-- Kanban Grid --}}
            <div class="grid gap-3" style="grid-template-columns: repeat({{ $slots->count() + ($backlogIssues->isNotEmpty() ? 1 : 0) }}, minmax(200px, 1fr)); overflow-x: auto;">
                {{-- Backlog --}}
                @if($backlogIssues->isNotEmpty())
                    <div class="rounded-lg bg-[var(--ui-muted-5)] border border-[var(--ui-border)]/40">
                        <div class="p-3 border-b border-[var(--ui-border)]/40">
                            <h4 class="text-xs font-semibold text-[var(--ui-muted)] uppercase">Backlog ({{ $backlogIssues->count() }})</h4>
                        </div>
                        <div class="p-2 space-y-2">
                            @foreach($backlogIssues as $issue)
                                @include('dev::livewire.package.partials.issue-card', ['issue' => $issue])
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- Slots --}}
                @foreach($slots as $slot)
                    <div class="rounded-lg bg-[var(--ui-muted-5)] border border-[var(--ui-border)]/40">
                        <div class="p-3 border-b border-[var(--ui-border)]/40">
                            <h4 class="text-xs font-semibold text-[var(--ui-muted)] uppercase">{{ $slot->name }} ({{ $slot->issues->count() }})</h4>
                        </div>
                        <div class="p-2 space-y-2 min-h-[100px]">
                            @foreach($slot->issues as $issue)
                                @include('dev::livewire.package.partials.issue-card', ['issue' => $issue])
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </x-ui-page-container>
</x-ui-page>
