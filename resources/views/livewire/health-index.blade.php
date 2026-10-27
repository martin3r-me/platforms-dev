@php
    $axisLabels = ['bug_pressure' => 'Bug-Druck', 'feature_velocity' => 'Feature-Velocity', 'production_health' => 'Production-Health', 'doc_coverage' => 'Doku-Coverage'];
    $colorTones = [
        'red'    => ['bg' => 'bg-rose-50',    'border' => 'border-rose-300',    'fg' => 'text-rose-700',    'dot' => 'bg-rose-500',    'fill' => 'bg-rose-500',    'label' => 'Brennt'],
        'yellow' => ['bg' => 'bg-amber-50',   'border' => 'border-amber-300',   'fg' => 'text-amber-700',   'dot' => 'bg-amber-500',   'fill' => 'bg-amber-500',   'label' => 'Achtung'],
        'green'  => ['bg' => 'bg-emerald-50', 'border' => 'border-emerald-300', 'fg' => 'text-emerald-700', 'dot' => 'bg-emerald-500', 'fill' => 'bg-emerald-500', 'label' => 'Stabil'],
        'gray'   => ['bg' => 'bg-zinc-50',    'border' => 'border-zinc-300',    'fg' => 'text-zinc-600',    'dot' => 'bg-zinc-400',    'fill' => 'bg-zinc-400',    'label' => 'Keine Daten'],
    ];
    $tone = fn ($c) => $colorTones[$c ?: 'gray'] ?? $colorTones['gray'];
    $distTotal = max(1, array_sum($byColor));
@endphp

<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Dev Health-Index" icon="heroicon-o-heart" />
    </x-slot>

    <x-slot name="actionbar">
        <x-dev::actionbar :filters-active="($colorFilter !== 'all' ? 1 : 0) + ($axisFilter !== 'all' ? 1 : 0)">
            <x-slot:filters>
                <x-dev::filter-group label="Ampel">
                    <x-dev::chip :active="$colorFilter === 'all'" :count="array_sum($byColor)" wire:click="$set('colorFilter', 'all')">Alle</x-dev::chip>
                    @foreach(['red', 'yellow', 'green', 'gray'] as $c)
                        <x-dev::chip :active="$colorFilter === $c" :dot="$tone($c)['dot']" :count="$byColor[$c] ?? 0" wire:click="$set('colorFilter', '{{ $c }}')">{{ $tone($c)['label'] }}</x-dev::chip>
                    @endforeach
                </x-dev::filter-group>
                <x-dev::filter-group label="Schwächste Achse">
                    <x-dev::chip :active="$axisFilter === 'all'" wire:click="$set('axisFilter', 'all')">Alle</x-dev::chip>
                    @foreach($axisLabels as $axisKey => $axisName)
                        <x-dev::chip :active="$axisFilter === $axisKey" :count="$byAxis[$axisKey] ?? 0" wire:click="$set('axisFilter', '{{ $axisKey }}')">{{ $axisName }}</x-dev::chip>
                    @endforeach
                </x-dev::filter-group>
                <x-dev::filter-group label="Sortierung" :stack="true">
                    @foreach([
                        'worst' => 'Schlimmste zuerst',
                        'best' => 'Beste zuerst',
                        'confidence' => 'Geringste Confidence zuerst',
                        'movement' => 'Letzte Bewegung zuerst',
                        'name' => 'Name (A→Z)',
                    ] as $key => $label)
                        <x-dev::chip :row="true" :active="$sort === $key" wire:click="$set('sort', '{{ $key }}')">{{ $label }}</x-dev::chip>
                    @endforeach
                </x-dev::filter-group>
            </x-slot:filters>
        </x-dev::actionbar>
    </x-slot>

    <div class="flex-1 min-w-0 min-h-0 flex flex-col overflow-hidden">
        <div class="px-3 sm:px-6 pt-3 pb-3 border-b border-[var(--ui-border)]/40 bg-white">
            <div class="flex flex-wrap items-start justify-between gap-x-6 gap-y-2 mb-3">
                <div class="min-w-0">
                    <h1 class="text-base font-semibold text-[var(--ui-secondary)] truncate m-0 leading-tight inline-flex items-center gap-2">
                        @svg('heroicon-o-heart', 'w-4 h-4 text-rose-500')
                        Dev Health-Index
                    </h1>
                    <p class="text-[11px] text-[var(--ui-muted)] mt-0.5 m-0">
                        Teamweite Sicht — {{ $totalAll }} Package{{ $totalAll === 1 ? '' : 's' }}, sortiert nach
                        @switch($sort)
                            @case('best') Score absteigend @break
                            @case('confidence') Confidence aufsteigend @break
                            @case('movement') letzter Bewegung @break
                            @case('name') Name @break
                            @default schwächster zuerst
                        @endswitch
                        @if($lastTakenOn) · Stand {{ $lastTakenOn->format('d.m.Y') }} @endif
                    </p>
                </div>
                {{-- Ampel-Legende = Filter: Klick filtert, erneuter Klick hebt auf --}}
                <div class="flex flex-wrap items-center gap-1 flex-shrink-0 text-[11px]">
                    @foreach(['red' => 'Brennt', 'yellow' => 'Achtung', 'green' => 'Stabil', 'gray' => 'Keine Daten'] as $c => $label)
                        @php $t = $tone($c); $isActive = $colorFilter === $c; @endphp
                        <button type="button" wire:click="$set('colorFilter', '{{ $isActive ? 'all' : $c }}')"
                                class="inline-flex items-center gap-1.5 px-2 py-1 rounded-md border transition-colors {{ $isActive ? $t['bg'].' '.$t['border'] : 'border-transparent hover:bg-[var(--ui-muted-5)]' }}">
                            <span class="w-1.5 h-1.5 rounded-full {{ $t['dot'] }}"></span>
                            <span class="font-semibold tabular-nums {{ $byColor[$c] > 0 ? $t['fg'] : 'text-[var(--ui-muted)]' }}">{{ $byColor[$c] }}</span>
                            <span class="text-[var(--ui-muted)]">{{ $label }}</span>
                        </button>
                    @endforeach
                </div>
            </div>

            <div class="h-3 w-full bg-zinc-100 rounded-full overflow-hidden flex">
                @foreach(['red', 'yellow', 'green', 'gray'] as $c)
                    @php $w = round(($byColor[$c] / $distTotal) * 100, 2); $t = $tone($c); @endphp
                    @if($byColor[$c] > 0)
                        <div class="{{ $t['fill'] }} h-full" style="width: {{ $w }}%" title="{{ $t['label'] }}: {{ $byColor[$c] }}"></div>
                    @endif
                @endforeach
            </div>
        </div>

        <div class="flex-1 overflow-y-auto px-3 sm:px-6 py-3 bg-[var(--ui-muted-5)]">
            @if($snapshots->isEmpty())
                <div class="rounded-xl border border-[var(--ui-border)] bg-white p-12 text-center">
                    <div class="mx-auto w-12 h-12 rounded-full bg-zinc-100 flex items-center justify-center mb-3">
                        @svg('heroicon-o-funnel', 'w-6 h-6 text-zinc-400')
                    </div>
                    <h3 class="text-sm font-medium text-[var(--ui-secondary)] m-0">Keine Packages passen zu deinen Filtern</h3>
                </div>
            @else
                <ul class="space-y-2">
                    @foreach($snapshots as $s)
                        @php
                            $t = $tone($s->health_color);
                            $axisVal = $s->axis_scores[$s->worst_axis] ?? null;
                            $worstLabel = $axisLabels[$s->worst_axis] ?? null;
                            $delta = $s->delta_health_score;
                            $deltaArrow = $delta === null || $delta === 0 ? null : ($delta > 0 ? '↑' : '↓');
                            $deltaColor = $delta === null ? 'text-zinc-400' : ($delta > 0 ? 'text-emerald-600' : 'text-rose-600');
                        @endphp
                        <li>
                            <a href="{{ route('dev.packages.health', $s->dev_package_id) }}" wire:navigate
                               class="group flex items-stretch rounded-xl border border-[var(--ui-border)] bg-white hover:border-indigo-600/60 hover:shadow-md transition-all overflow-hidden">

                                <div class="flex flex-col items-center justify-center w-20 flex-shrink-0 {{ $t['bg'] }} border-r {{ $t['border'] }}/60 py-3">
                                    <span class="text-2xl font-bold tabular-nums {{ $t['fg'] }} leading-none">{{ $s->health_score ?? '–' }}</span>
                                    <span class="text-[9px] uppercase tracking-wider {{ $t['fg'] }} opacity-70 mt-1">{{ $t['label'] }}</span>
                                </div>

                                <div class="flex-1 min-w-0 px-4 py-3">
                                    <div class="flex items-center gap-2 mb-1">
                                        <span class="text-sm font-semibold text-[var(--ui-secondary)] truncate">{{ $s->package?->name }}</span>
                                    </div>

                                    <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-[11px]">
                                        @if($worstLabel)
                                            <span class="inline-flex items-center gap-1 {{ $t['fg'] }}">
                                                @svg('heroicon-o-exclamation-triangle', 'w-3 h-3')
                                                <span>Schwach: {{ $worstLabel }}{{ $axisVal !== null ? ' ('.$axisVal.')' : '' }}</span>
                                            </span>
                                        @endif
                                        @if($s->bugs_open > 0)
                                            <span class="inline-flex items-center gap-1 text-rose-600">🐛 <span class="tabular-nums font-medium">{{ $s->bugs_open }}</span></span>
                                        @endif
                                        @if($s->features_open > 0)
                                            <span class="inline-flex items-center gap-1 text-indigo-600">🚀 <span class="tabular-nums font-medium">{{ $s->features_open }}</span></span>
                                        @endif
                                        @if($s->errors_open > 0)
                                            <span class="inline-flex items-center gap-1 text-amber-600">
                                                @svg('heroicon-o-fire', 'w-3 h-3')
                                                <span class="tabular-nums font-medium">{{ $s->errors_open }}</span> errors
                                            </span>
                                        @endif
                                        @if($s->confidence_score < 50)
                                            <span class="inline-flex items-center gap-1 text-zinc-500">
                                                @svg('heroicon-o-question-mark-circle', 'w-3 h-3')
                                                <span>Conf {{ $s->confidence_score }}%</span>
                                            </span>
                                        @endif
                                    </div>
                                </div>

                                <div class="flex items-center gap-3 px-4 flex-shrink-0">
                                    @if($deltaArrow)
                                        <span class="inline-flex items-center gap-0.5 text-[11px] tabular-nums {{ $deltaColor }} font-medium">
                                            {{ $deltaArrow }}{{ abs($delta) }}
                                        </span>
                                    @endif
                                    @svg('heroicon-o-chevron-right', 'w-4 h-4 text-[var(--ui-muted)] group-hover:text-indigo-600 transition-colors')
                                </div>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
</x-ui-page>
