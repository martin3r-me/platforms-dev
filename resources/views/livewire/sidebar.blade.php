<div>
    {{-- Header --}}
    <div x-show="!collapsed" class="px-4 py-3 border-b border-gray-200">
        <div class="flex items-center gap-2">
            <svg class="w-4 h-4 text-gray-500" viewBox="0 0 16 16" fill="currentColor"><path d="M2 2.5A2.5 2.5 0 0 1 4.5 0h8.75a.75.75 0 0 1 .75.75v12.5a.75.75 0 0 1-.75.75h-2.5a.75.75 0 0 1 0-1.5h1.75v-2h-8a1 1 0 0 0-.714 1.7.75.75 0 1 1-1.072 1.05A2.495 2.495 0 0 1 2 11.5Zm10.5-1h-8a1 1 0 0 0-1 1v6.708A2.486 2.486 0 0 1 4.5 9h8ZM5 12.25a.25.25 0 0 1 .25-.25h3.5a.25.25 0 0 1 .25.25v3.25a.25.25 0 0 1-.4.2l-1.45-1.087a.25.25 0 0 0-.3 0L5.4 15.7a.25.25 0 0 1-.4-.2Z"/></svg>
            <span class="text-xs font-semibold text-gray-900">Dev</span>
        </div>
    </div>

    {{-- Navigation --}}
    <x-ui-sidebar-list label="Navigation">
        <x-ui-sidebar-item :href="route('dev.dashboard')">
            <svg class="w-4 h-4 text-gray-500 flex-shrink-0" viewBox="0 0 16 16" fill="currentColor"><path d="M6.5 1.75a.25.25 0 0 1 .25-.25h2.5a.25.25 0 0 1 .25.25v11.5a.25.25 0 0 1-.25.25h-2.5a.25.25 0 0 1-.25-.25Zm-4 4a.25.25 0 0 1 .25-.25h2.5a.25.25 0 0 1 .25.25v7.5a.25.25 0 0 1-.25.25h-2.5a.25.25 0 0 1-.25-.25Zm8-2a.25.25 0 0 1 .25-.25h2.5a.25.25 0 0 1 .25.25v9.5a.25.25 0 0 1-.25.25h-2.5a.25.25 0 0 1-.25-.25Z"/></svg>
            <span class="ml-2 text-xs text-gray-700">Overview</span>
        </x-ui-sidebar-item>
    </x-ui-sidebar-list>

    {{-- Active Packages (Expandable) --}}
    @if($activePackages->isNotEmpty())
        <x-ui-sidebar-list label="Repositories">
            @foreach($activePackages as $package)
                <div x-data="{ expanded: {{ request()->routeIs('dev.packages.*') && request()->route('package')?->id === $package->id ? 'true' : 'false' }} }">
                    {{-- Package Row --}}
                    <div class="flex items-center">
                        <button @click="expanded = !expanded" class="flex-shrink-0 p-1 ml-1 text-gray-400 hover:text-gray-600 transition-colors">
                            <svg :class="expanded ? 'rotate-90' : ''" class="w-3 h-3 transition-transform duration-150" viewBox="0 0 16 16" fill="currentColor"><path d="M6.22 3.22a.75.75 0 0 1 1.06 0l4.25 4.25a.75.75 0 0 1 0 1.06l-4.25 4.25a.751.751 0 0 1-1.042-.018.751.751 0 0 1-.018-1.042L9.94 8 6.22 4.28a.75.75 0 0 1 0-1.06Z"/></svg>
                        </button>
                        <x-ui-sidebar-item :href="route('dev.packages.show', $package)" class="flex-grow-1">
                            <svg class="w-4 h-4 text-gray-400 flex-shrink-0" viewBox="0 0 16 16" fill="currentColor"><path d="M2 2.5A2.5 2.5 0 0 1 4.5 0h8.75a.75.75 0 0 1 .75.75v12.5a.75.75 0 0 1-.75.75h-2.5a.75.75 0 0 1 0-1.5h1.75v-2h-8a1 1 0 0 0-.714 1.7.75.75 0 1 1-1.072 1.05A2.495 2.495 0 0 1 2 11.5Zm10.5-1h-8a1 1 0 0 0-1 1v6.708A2.486 2.486 0 0 1 4.5 9h8ZM5 12.25a.25.25 0 0 1 .25-.25h3.5a.25.25 0 0 1 .25.25v3.25a.25.25 0 0 1-.4.2l-1.45-1.087a.25.25 0 0 0-.3 0L5.4 15.7a.25.25 0 0 1-.4-.2Z"/></svg>
                            <span class="ml-2 text-xs text-gray-700 truncate flex-grow-1">{{ $package->name }}</span>
                            @if(($package->open_features_count ?? 0) > 0)
                                <span class="inline-flex items-center justify-center min-w-[18px] h-[16px] px-1 rounded-full bg-blue-100 text-blue-700 text-[10px] font-medium flex-shrink-0 leading-none tabular-nums">
                                    {{ $package->open_features_count }}
                                </span>
                            @endif
                            @if(($package->open_bugs_count ?? 0) > 0)
                                <span class="inline-flex items-center justify-center min-w-[18px] h-[16px] px-1 rounded-full bg-red-100 text-red-700 text-[10px] font-medium flex-shrink-0 leading-none tabular-nums">
                                    {{ $package->open_bugs_count }}
                                </span>
                            @endif
                        </x-ui-sidebar-item>
                    </div>

                    {{-- Sub-Navigation --}}
                    <div x-show="expanded" x-collapse class="ml-6 border-l border-gray-200">
                        {{-- Boards --}}
                        @foreach($package->boards as $board)
                            <a href="{{ route('dev.packages.boards.show', [$package, $board]) }}"
                               wire:navigate
                               class="flex items-center gap-2 px-3 py-1.5 text-[11px] text-gray-600 hover:text-gray-900 hover:bg-gray-50 transition-colors">
                                @if($board->type->value === 'bug')
                                    @svg('heroicon-o-bug-ant', 'w-3 h-3 text-red-500 flex-shrink-0')
                                @elseif($board->type->value === 'feature')
                                    @svg('heroicon-o-light-bulb', 'w-3 h-3 text-blue-500 flex-shrink-0')
                                @else
                                    @svg('heroicon-o-view-columns', 'w-3 h-3 text-gray-400 flex-shrink-0')
                                @endif
                                <span class="truncate">{{ $board->name }}</span>
                                @if($board->open_issues_count > 0)
                                    <span class="ml-auto text-[10px] font-medium text-gray-500 tabular-nums">{{ $board->open_issues_count }}</span>
                                @endif
                            </a>
                        @endforeach

                        {{-- Documentation --}}
                        <a href="{{ route('dev.packages.docs', $package) }}"
                           wire:navigate
                           class="flex items-center gap-2 px-3 py-1.5 text-[11px] text-gray-600 hover:text-gray-900 hover:bg-gray-50 transition-colors">
                            @svg('heroicon-o-book-open', 'w-3 h-3 text-gray-400 flex-shrink-0')
                            <span class="truncate">Docs</span>
                        </a>
                    </div>
                </div>
            @endforeach
        </x-ui-sidebar-list>
    @endif

    {{-- Archived --}}
    @if($archivedPackages->isNotEmpty())
        <x-ui-sidebar-list label="Archived">
            @foreach($archivedPackages as $package)
                <x-ui-sidebar-item :href="route('dev.packages.show', $package)">
                    @svg('heroicon-o-archive-box', 'w-4 h-4 text-gray-400 flex-shrink-0')
                    <span class="ml-2 text-xs text-gray-400 truncate">{{ $package->name }}</span>
                </x-ui-sidebar-item>
            @endforeach
        </x-ui-sidebar-list>
    @endif

    {{-- Collapsed: Icons-only --}}
    <div x-show="collapsed" class="px-2 py-3 border-b border-gray-200">
        <div class="flex flex-col gap-1.5">
            <a href="{{ route('dev.dashboard') }}" wire:navigate class="flex items-center justify-center p-2 rounded-md text-gray-500 hover:bg-gray-100 hover:text-gray-700 transition-colors">
                @svg('heroicon-o-command-line', 'w-4 h-4')
            </a>
            @foreach($activePackages as $package)
                <a href="{{ route('dev.packages.show', $package) }}" wire:navigate class="relative flex items-center justify-center p-2 rounded-md text-gray-500 hover:bg-gray-100 hover:text-gray-700 transition-colors">
                    @svg($package->icon ?? 'heroicon-o-cube', 'w-4 h-4')
                    @if(($package->open_bugs_count ?? 0) > 0)
                        <span class="absolute -top-0.5 -right-0.5 w-2 h-2 rounded-full bg-red-500 ring-2 ring-white"></span>
                    @endif
                </a>
            @endforeach
        </div>
    </div>
</div>
