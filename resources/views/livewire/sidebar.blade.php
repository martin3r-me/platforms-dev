<div>
    {{-- Header --}}
    <div x-show="!collapsed" class="p-3 text-sm italic text-[var(--ui-secondary)] uppercase border-b border-[var(--ui-border)] mb-2">
        Dev
    </div>

    {{-- Navigation --}}
    <x-ui-sidebar-list label="Allgemein">
        <x-ui-sidebar-item :href="route('dev.dashboard')">
            <div class="w-6 h-6 rounded-md bg-gradient-to-br from-[var(--ui-primary)]/20 to-[var(--ui-success)]/10 d-flex items-center justify-center flex-shrink-0">
                @svg('heroicon-o-command-line', 'w-3.5 h-3.5 text-[var(--ui-primary)]')
            </div>
            <span class="ml-2 text-sm">Dashboard</span>
        </x-ui-sidebar-item>
    </x-ui-sidebar-list>

    {{-- Active Packages --}}
    @if($activePackages->isNotEmpty())
        <x-ui-sidebar-list label="Packages">
            @foreach($activePackages as $package)
                <x-ui-sidebar-item :href="route('dev.packages.show', $package)">
                    <div class="w-6 h-6 rounded-md bg-gradient-to-br from-[var(--ui-primary)]/15 to-[var(--ui-primary)]/5 d-flex items-center justify-center flex-shrink-0">
                        @svg($package->icon ?? 'heroicon-o-cube', 'w-3.5 h-3.5 text-[var(--ui-primary)]')
                    </div>
                    <span class="ml-2 text-sm truncate flex-grow-1">{{ $package->name }}</span>
                    @if(($package->open_bugs_count ?? 0) > 0)
                        <span class="inline-flex items-center justify-center min-w-[18px] h-[18px] px-1 rounded-full bg-red-500/15 text-[var(--ui-danger)] text-[10px] font-bold flex-shrink-0">
                            {{ $package->open_bugs_count }}
                        </span>
                    @endif
                </x-ui-sidebar-item>
            @endforeach
        </x-ui-sidebar-list>
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
                @svg('heroicon-o-command-line', 'w-5 h-5')
            </a>
            @foreach($activePackages as $package)
                <a href="{{ route('dev.packages.show', $package) }}" wire:navigate class="relative flex items-center justify-center p-2 rounded-md text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)]">
                    @svg($package->icon ?? 'heroicon-o-cube', 'w-5 h-5')
                    @if(($package->open_bugs_count ?? 0) > 0)
                        <span class="absolute -top-0.5 -right-0.5 w-2 h-2 rounded-full bg-[var(--ui-danger)]"></span>
                    @endif
                </a>
            @endforeach
        </div>
    </div>
</div>
