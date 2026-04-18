<div>
    {{-- Header --}}
    <div x-show="!collapsed" class="p-3 text-sm italic text-[var(--ui-secondary)] uppercase border-b border-[var(--ui-border)] mb-2">
        Dev
    </div>

    {{-- Navigation --}}
    <x-ui-sidebar-list label="Allgemein">
        <x-ui-sidebar-item :href="route('dev.dashboard')">
            @svg('heroicon-o-home', 'w-4 h-4 text-[var(--ui-secondary)]')
            <span class="ml-2 text-sm">Dashboard</span>
        </x-ui-sidebar-item>
    </x-ui-sidebar-list>

    {{-- Active Packages --}}
    @if($activePackages->isNotEmpty())
        @foreach($activePackages as $package)
            <x-ui-sidebar-list :label="$package->name">
                <x-ui-sidebar-item :href="route('dev.packages.show', $package)">
                    @svg($package->icon ?? 'heroicon-o-cube', 'w-4 h-4 text-[var(--ui-secondary)]')
                    <span class="ml-2 text-sm truncate">Übersicht</span>
                </x-ui-sidebar-item>
                @foreach($package->boards as $board)
                    <x-ui-sidebar-item :href="route('dev.packages.boards.show', [$package, $board])">
                        @svg('heroicon-o-view-columns', 'w-4 h-4 text-[var(--ui-muted)]')
                        <span class="ml-2 text-sm truncate">{{ $board->name }}</span>
                    </x-ui-sidebar-item>
                @endforeach
            </x-ui-sidebar-list>
        @endforeach
    @endif

    {{-- Archived --}}
    @if($archivedPackages->isNotEmpty())
        <x-ui-sidebar-list label="Archiviert">
            @foreach($archivedPackages as $package)
                <x-ui-sidebar-item :href="route('dev.packages.show', $package)">
                    @svg($package->icon ?? 'heroicon-o-archive-box', 'w-4 h-4 text-[var(--ui-muted)]')
                    <span class="ml-2 text-sm truncate text-[var(--ui-muted)]">{{ $package->name }}</span>
                </x-ui-sidebar-item>
            @endforeach
        </x-ui-sidebar-list>
    @endif

    {{-- Collapsed: Icons-only --}}
    <div x-show="collapsed" class="px-2 py-2 border-b border-[var(--ui-border)]">
        <div class="flex flex-col gap-2">
            <a href="{{ route('dev.dashboard') }}" wire:navigate class="flex items-center justify-center p-2 rounded-md text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)]">
                @svg('heroicon-o-home', 'w-5 h-5')
            </a>
            @foreach($activePackages as $package)
                <a href="{{ route('dev.packages.show', $package) }}" wire:navigate class="flex items-center justify-center p-2 rounded-md text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)]">
                    @svg($package->icon ?? 'heroicon-o-cube', 'w-5 h-5')
                </a>
            @endforeach
        </div>
    </div>
</div>
