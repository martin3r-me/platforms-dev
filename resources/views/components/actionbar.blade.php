{{--
    Dev-Actionbar — EINE Leiste für alle Dev-Seiten, feste Plätze (wie im Planner):

      [Breadcrumb]  [Reiter der Ebene]  ·········  [Filter ▾] [+ Neu ▾] [⋯]

    Die Leiste ist auf jeder Seite identisch: keine Seiten-Badges/Kennzahlen hier —
    die gehören in <x-dev::statusbar> als erstes Element des Inhalts.

    • Ebene Dev:      Packages · Health-Index
    • Ebene Package:  Übersicht · <Boards> · Doku · Health  (sobald :package gesetzt)
    • Filter:  Inhalt der Seite (Slot "filters"); ohne Filter bleibt der Button sichtbar, aber inaktiv.
    • + Neu:   im Package immer Issue (je Board) + Board; immer Package; Seiten ergänzen über Slot "create".
    • ⋯:       Seitenoptionen (Slot "more"); im Package immer Einstellungen + Error Tracking.

    Responsiv: Reiter < md als Dropdown, Button-Beschriftungen < sm nur Icons, Popover passen sich der Breite an.

    <x-dev::actionbar :package="$package" :crumbs="[['label' => $issue->title]]" :filters-active="1">
        <x-slot:filters> … </x-slot:filters>
        <x-slot:create> <x-dev::menu-item icon="…" wire:click="…">…</x-dev::menu-item> </x-slot:create>
        <x-slot:more> … </x-slot:more>
    </x-dev::actionbar>
--}}
@props([
    'package' => null,
    'active' => null,
    'crumbs' => [],
    'filtersActive' => 0,
])

@php
    // Bei Livewire-Updates (wire:click …) ist request()->route() die Update-Route —
    // dann die Route der ursprünglichen Seite auflösen, sonst springen Reiter/Aktionen um.
    $route = request()->route();
    if (\Livewire\Livewire::isLivewireRequest()) {
        try {
            $route = app('router')->getRoutes()->match(\Illuminate\Http\Request::create(\Livewire\Livewire::originalUrl(), 'GET'));
        } catch (\Throwable $e) {
            $route = null;
        }
    }
    $routeName = $route?->getName();
    $routeKey = fn ($param) => is_object($v = $route?->parameter($param)) ? $v->getKey() : $v;

    if ($active === null) {
        $active = match ($routeName) {
            'dev.dashboard' => 'packages',
            'dev.health-index' => 'health-index',
            'dev.packages.show' => 'overview',
            'dev.packages.docs', 'dev.packages.docs.show' => 'docs',
            'dev.packages.health' => 'health',
            'dev.packages.boards.show' => 'board-' . $routeKey('board'),
            'dev.packages.issues.show' => 'board-' . \Platform\Dev\Models\DevIssue::whereKey($routeKey('issue'))->value('dev_board_id'),
            default => null,
        };
    }

    // Seiten-Aktionen: auf der zuständigen Seite direkt per $wire, sonst als Link mit ?aktion=…
    $onShow = $routeName === 'dev.packages.show';
    $onDashboard = $routeName === 'dev.dashboard';

    if ($package) {
        $nav = \Platform\Dev\Support\PackageNavSummary::for($package);
        $healthDot = match ($nav['health']['color']) {
            'green' => 'bg-[color:var(--nx-success)]',
            'yellow' => 'bg-[color:var(--nx-warning)]',
            'red' => 'bg-[color:var(--nx-danger)]',
            default => null,
        };
        $boardIcon = fn ($board) => match ($board->type instanceof \BackedEnum ? $board->type->value : $board->type) {
            'bug' => 'heroicon-o-bug-ant',
            'feature' => 'heroicon-o-light-bulb',
            'inbox' => 'heroicon-o-inbox',
            default => 'heroicon-o-view-columns',
        };

        $tabs = ['overview' => ['href' => route('dev.packages.show', $package), 'label' => 'Übersicht', 'icon' => 'heroicon-o-presentation-chart-line',
            'title' => 'Übersicht — Fortschritt, Errors, Commits, Pull Requests']];
        foreach ($nav['boards'] as $board) {
            $tabs['board-' . $board->id] = ['href' => route('dev.packages.boards.show', [$package, $board]), 'label' => $board->name, 'icon' => $boardIcon($board),
                'title' => $board->name . ' — ' . $board->open_issues_count . ' offen',
                'meta' => $board->open_issues_count > 0 ? (string) $board->open_issues_count : null];
        }
        $tabs['docs'] = ['href' => route('dev.packages.docs', $package), 'label' => 'Doku', 'icon' => 'heroicon-o-book-open',
            'title' => 'Doku — ' . $nav['docs'] . ' Seiten', 'meta' => $nav['docs'] > 0 ? (string) $nav['docs'] : null];
        $tabs['health'] = ['href' => route('dev.packages.health', $package), 'label' => 'Health', 'icon' => 'heroicon-o-heart',
            'title' => 'Health — das Lagebild' . ($nav['health']['score'] !== null ? ' · Index ' . $nav['health']['score'] : ''),
            'meta' => $nav['health']['score'] !== null ? (string) $nav['health']['score'] : null, 'dot' => $healthDot];

        $trail = array_merge(
            [['label' => 'Dev', 'href' => route('dev.dashboard'), 'icon' => 'home']],
            [['label' => $package->name, 'href' => route('dev.packages.show', $package)]],
            $crumbs,
        );
    } else {
        $tabs = [
            'packages' => ['href' => route('dev.dashboard'), 'label' => 'Packages', 'icon' => 'heroicon-o-cube'],
            'health-index' => ['href' => route('dev.health-index'), 'label' => 'Health-Index', 'short' => 'Health', 'icon' => 'heroicon-o-heart',
                'title' => 'Health-Index — Health aller Packages'],
        ];
        $trail = array_merge([['label' => 'Dev', 'href' => route('dev.dashboard'), 'icon' => 'home']], $crumbs);
    }

    $activeTab = $tabs[$active] ?? null;
    $hasFilters = isset($filters) && trim((string) $filters) !== '';

    $iconBtn = 'inline-flex items-center justify-center gap-1.5 h-8 sm:h-7 min-w-8 sm:min-w-7 px-2 rounded-md text-xs font-medium transition-colors';
    $popover = 'absolute top-full mt-1.5 z-40 bg-[color:var(--nx-surface)] border border-[color:var(--nx-line-strong)] rounded-lg shadow-[var(--nx-shadow-pop)]';
@endphp

<div x-data="{ open: null, toggle(m) { this.open = this.open === m ? null : m } }"
     @keydown.escape.window="open = null"
     class="relative shrink-0 z-30 h-12 sm:h-11 px-2 sm:px-4 bg-[color:var(--nx-surface)] border-b border-[color:var(--nx-line)]">
    <div class="h-full flex items-center gap-1.5 sm:gap-2">

        {{-- Wo bin ich --}}
        <div class="min-w-0 flex-1 md:flex-initial md:max-w-[34%] lg:max-w-[30%]">
            <x-ui-breadcrumb :items="$trail" />
        </div>

        {{-- Reiter ab md: ruhige Textreiter, aktiver Reiter mit Akzentstrich auf der Unterkante der Leiste --}}
        <span class="hidden md:block h-4 w-px bg-[color:var(--nx-line)] mx-1.5 shrink-0" aria-hidden="true"></span>
        <nav class="hidden md:flex self-stretch items-stretch min-w-0 overflow-x-auto -mb-px [scrollbar-width:none]" aria-label="Bereiche">
            @foreach($tabs as $key => $tab)
                @php $isActive = $active === $key; @endphp
                <a href="{{ $tab['href'] }}" wire:navigate title="{{ $tab['title'] ?? $tab['label'] }}" @if($isActive) aria-current="page" @endif
                   class="group relative shrink-0 inline-flex items-center gap-1.5 px-2 lg:px-3 text-[12px] whitespace-nowrap transition-colors {{ $isActive ? 'text-[color:var(--nx-text)] font-semibold' : 'text-[color:var(--nx-muted)] font-medium hover:text-[color:var(--nx-text)]' }}">
                    @svg($tab['icon'], 'w-4 h-4 shrink-0 ' . ($isActive ? 'text-[color:var(--nx-accent)]' : 'opacity-70 group-hover:opacity-100'))
                    <span class="{{ $isActive ? '' : 'hidden lg:inline' }}">{{ $tab['label'] }}</span>
                    @if(! empty($tab['dot']))<span class="w-1.5 h-1.5 rounded-full {{ $tab['dot'] }}"></span>@endif
                    @if(($tab['meta'] ?? null) !== null)
                        <span class="hidden xl:inline tabular-nums text-[10px] font-normal text-[color:var(--nx-faint)]">{{ $tab['meta'] }}</span>
                    @endif
                    <span class="absolute inset-x-1.5 lg:inset-x-2 bottom-0 h-0.5 rounded-t-full transition-colors {{ $isActive ? 'bg-[color:var(--nx-accent)]' : 'bg-transparent group-hover:bg-[color:var(--nx-line-strong)]' }}" aria-hidden="true"></span>
                </a>
            @endforeach
        </nav>

        {{-- Reiter: unter md als Dropdown --}}
        <div class="relative md:hidden shrink-0">
            <button type="button" @click="toggle('tabs')" :aria-expanded="open === 'tabs'"
                    class="{{ $iconBtn }} font-semibold text-[color:var(--nx-text)] hover:bg-[color:var(--nx-hover)]">
                @if($activeTab) @svg($activeTab['icon'], 'w-4 h-4 text-[color:var(--nx-accent)]') <span class="max-w-[7rem] truncate">{{ $activeTab['short'] ?? $activeTab['label'] }}</span>
                @else @svg('heroicon-o-squares-2x2', 'w-4 h-4') @endif
                @svg('heroicon-o-chevron-down', 'w-3.5 h-3.5 text-[color:var(--nx-muted)]')
            </button>
            <div x-show="open === 'tabs'" x-cloak x-transition.opacity.duration.100ms @click.outside="open = null"
                 class="{{ $popover }} left-0 w-56 max-h-[70vh] overflow-y-auto p-1">
                @foreach($tabs as $key => $tab)
                    <a href="{{ $tab['href'] }}" wire:navigate
                       class="flex items-center gap-2.5 px-3 py-2 rounded-md text-[13px] {{ $active === $key ? 'bg-[color:var(--nx-accent)]/10 text-[color:var(--nx-accent)] font-medium' : 'text-[color:var(--nx-text)] hover:bg-[color:var(--nx-hover)]' }}">
                        @svg($tab['icon'], 'w-4 h-4 shrink-0')
                        <span class="flex-1 truncate">{{ $tab['label'] }}</span>
                        @if(! empty($tab['dot']))<span class="w-1.5 h-1.5 rounded-full {{ $tab['dot'] }}"></span>@endif
                        @if(($tab['meta'] ?? null) !== null)
                            <span class="text-[11px] tabular-nums text-[color:var(--nx-muted)]">{{ $tab['meta'] }}</span>
                        @endif
                    </a>
                @endforeach
            </div>
        </div>

        <div class="flex-1"></div>

        {{-- Filter --}}
        <div class="relative shrink-0">
            <button type="button" @if($hasFilters) @click="toggle('filter')" @else disabled @endif
                    :aria-expanded="open === 'filter'"
                    title="{{ $hasFilters ? 'Filter' : 'Keine Filter auf dieser Seite' }}"
                    class="{{ $iconBtn }} {{ $hasFilters ? ($filtersActive > 0 ? 'text-[color:var(--nx-accent)] bg-[color:var(--nx-accent)]/10 hover:bg-[color:var(--nx-accent)]/15' : 'text-[color:var(--nx-muted)] hover:text-[color:var(--nx-text)] hover:bg-[color:var(--nx-hover)]') : 'text-[color:var(--nx-faint)] opacity-50 cursor-not-allowed' }}">
                @svg('heroicon-o-funnel', 'w-4 h-4')
                <span class="hidden sm:inline">Filter</span>
                @if($filtersActive > 0)
                    <span class="min-w-[16px] h-4 px-1 rounded-full text-[10px] font-semibold bg-[color:var(--nx-accent)] text-[color:var(--nx-on-accent)] tabular-nums leading-4 text-center">{{ $filtersActive }}</span>
                @endif
            </button>
            @if($hasFilters)
                <div x-show="open === 'filter'" x-cloak x-transition.opacity.duration.100ms @click.outside="open = null"
                     class="{{ $popover }} right-0 w-[min(22rem,calc(100vw-1rem))] max-h-[min(70vh,36rem)] overflow-y-auto p-3 space-y-4">
                    {{ $filters }}
                </div>
            @endif
        </div>

        {{-- + Neu --}}
        <div class="relative shrink-0">
            <button type="button" @click="toggle('new')" :aria-expanded="open === 'new'" title="Neu anlegen"
                    class="{{ $iconBtn }} bg-[color:var(--nx-accent)] text-[color:var(--nx-on-accent)] hover:opacity-90">
                @svg('heroicon-o-plus', 'w-4 h-4')
                <span class="hidden sm:inline">Neu</span>
            </button>
            <div x-show="open === 'new'" x-cloak x-transition.opacity.duration.100ms @click.outside="open = null"
                 @click="if ($event.target.closest('[data-menu-item]')) open = null"
                 class="{{ $popover }} right-0 w-[min(16rem,calc(100vw-1rem))] p-1">
                @if($package)
                    @foreach($nav['boards'] as $board)
                        <x-dev::menu-item :icon="$boardIcon($board)" x-on:click="$dispatch('dev-create', { type: 'issue', boardId: {{ $board->id }} })">
                            Issue in {{ $board->name }}
                        </x-dev::menu-item>
                    @endforeach
                    @if($onShow)
                        <x-dev::menu-item icon="heroicon-o-view-columns" x-on:click="$wire.set('showCreateBoardModal', true)">Board</x-dev::menu-item>
                    @else
                        <x-dev::menu-item icon="heroicon-o-view-columns" :href="route('dev.packages.show', $package) . '?aktion=board'">Board</x-dev::menu-item>
                    @endif
                @endif
                @isset($create)
                    {{ $create }}
                @endisset
                <div class="my-1 border-t border-[color:var(--nx-line)]"></div>
                @if($onDashboard)
                    <x-dev::menu-item icon="heroicon-o-cube" x-on:click="$wire.openActivateModal()" hint="aus GitHub-Repo">Package</x-dev::menu-item>
                @else
                    <x-dev::menu-item icon="heroicon-o-cube" :href="route('dev.dashboard') . '?aktion=package'" hint="aus GitHub-Repo">Package</x-dev::menu-item>
                @endif
            </div>
        </div>

        {{-- ⋯ --}}
        <div class="relative shrink-0">
            <button type="button" @click="toggle('more')" :aria-expanded="open === 'more'" title="Mehr"
                    class="{{ $iconBtn }} text-[color:var(--nx-muted)] hover:text-[color:var(--nx-text)] hover:bg-[color:var(--nx-hover)]">
                @svg('heroicon-o-ellipsis-horizontal', 'w-4 h-4')
            </button>
            <div x-show="open === 'more'" x-cloak x-transition.opacity.duration.100ms @click.outside="open = null"
                 @click="if ($event.target.closest('[data-menu-item]')) open = null"
                 class="{{ $popover }} right-0 w-[min(16rem,calc(100vw-1rem))] p-1">
                @isset($more)
                    {{ $more }}
                    <div class="my-1 border-t border-[color:var(--nx-line)]"></div>
                @endisset
                @if($package)
                    @if($onShow)
                        <x-dev::menu-item icon="heroicon-o-cog-6-tooth" x-on:click="$wire.startEditingPackage()">Package-Einstellungen</x-dev::menu-item>
                        <x-dev::menu-item icon="heroicon-o-bug-ant" x-on:click="$wire.openErrorSettings()">Error Tracking</x-dev::menu-item>
                    @else
                        <x-dev::menu-item icon="heroicon-o-cog-6-tooth" :href="route('dev.packages.show', $package) . '?aktion=settings'">Package-Einstellungen</x-dev::menu-item>
                        <x-dev::menu-item icon="heroicon-o-bug-ant" :href="route('dev.packages.show', $package) . '?aktion=errors'">Error Tracking</x-dev::menu-item>
                    @endif
                @else
                    @if($onDashboard)
                        <x-dev::menu-item icon="heroicon-o-bug-ant" x-on:click="$wire.set('showErrorTracking', true)">Error Tracking (Team-Token)</x-dev::menu-item>
                    @else
                        <x-dev::menu-item icon="heroicon-o-bug-ant" :href="route('dev.dashboard') . '?aktion=errors'">Error Tracking (Team-Token)</x-dev::menu-item>
                    @endif
                @endif
            </div>
        </div>
    </div>

    @livewire('dev.quick-create', key('dev-quick-create'))
</div>
