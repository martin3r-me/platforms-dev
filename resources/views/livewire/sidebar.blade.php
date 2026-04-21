<div>
    {{-- Header --}}
    <div x-show="!collapsed" class="px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider border-b border-gray-200 mb-1">
        Dev
    </div>

    {{-- Navigation --}}
    <x-ui-sidebar-list label="Allgemein">
        <x-ui-sidebar-item :href="route('dev.dashboard')">
            <div class="w-5 h-5 rounded d-flex items-center justify-center flex-shrink-0 text-gray-500">
                @svg('heroicon-o-command-line', 'w-4 h-4')
            </div>
            <span class="ml-2 text-sm font-medium text-gray-700">Dashboard</span>
        </x-ui-sidebar-item>
    </x-ui-sidebar-list>

    {{-- Active Packages --}}
    @if($activePackages->isNotEmpty())
        <x-ui-sidebar-list label="Repositories">
            @foreach($activePackages as $package)
                <x-ui-sidebar-item :href="route('dev.packages.show', $package)">
                    <div class="w-5 h-5 d-flex items-center justify-center flex-shrink-0 text-gray-400">
                        @svg($package->icon ?? 'heroicon-o-cube', 'w-4 h-4')
                    </div>
                    <span class="ml-2 text-sm font-mono text-gray-700 truncate flex-grow-1">{{ $package->name }}</span>
                    @if(($package->open_bugs_count ?? 0) > 0)
                        <span class="inline-flex items-center justify-center min-w-[20px] h-5 px-1.5 rounded-full bg-red-100 text-red-700 text-[11px] font-semibold flex-shrink-0">
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
                    @svg($package->icon ?? 'heroicon-o-archive-box', 'w-4 h-4 text-gray-400')
                    <span class="ml-2 text-sm text-gray-400 truncate">{{ $package->name }}</span>
                </x-ui-sidebar-item>
            @endforeach
        </x-ui-sidebar-list>
    @endif

    {{-- Collapsed: Icons-only --}}
    <div x-show="collapsed" class="px-2 py-2 border-b border-gray-200">
        <div class="flex flex-col gap-2">
            <a href="{{ route('dev.dashboard') }}" wire:navigate class="flex items-center justify-center p-2 rounded-md text-gray-500 hover:bg-gray-100">
                @svg('heroicon-o-command-line', 'w-5 h-5')
            </a>
            @foreach($activePackages as $package)
                <a href="{{ route('dev.packages.show', $package) }}" wire:navigate class="relative flex items-center justify-center p-2 rounded-md text-gray-500 hover:bg-gray-100">
                    @svg($package->icon ?? 'heroicon-o-cube', 'w-5 h-5')
                    @if(($package->open_bugs_count ?? 0) > 0)
                        <span class="absolute -top-0.5 -right-0.5 w-2 h-2 rounded-full bg-red-500"></span>
                    @endif
                </a>
            @endforeach
        </div>
    </div>
</div>
