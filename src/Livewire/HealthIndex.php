<?php

namespace Platform\Dev\Livewire;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Platform\Dev\Models\DevPackageSnapshot;

class HealthIndex extends Component
{
    /** @var string all|red|yellow|green|gray */
    public string $colorFilter = 'all';

    /** @var string all|bug_pressure|feature_velocity|production_health|doc_coverage */
    public string $axisFilter = 'all';

    /** @var string worst|best|movement|confidence|name */
    public string $sort = 'worst';

    #[Layout('platform::layouts.app')]
    public function render()
    {
        $user = Auth::user();
        $team = $user->currentTeam;

        $latestIds = DB::table('dev_package_snapshots as a')
            ->where('a.team_id', $team->id)
            ->whereRaw('a.taken_on = (
                SELECT MAX(b.taken_on) FROM dev_package_snapshots b
                WHERE b.dev_package_id = a.dev_package_id
            )')
            ->pluck('a.id');

        $all = DevPackageSnapshot::with('package:id,name')
            ->whereIn('id', $latestIds)
            ->get();

        $totalAll = $all->count();

        $byColor = ['red' => 0, 'yellow' => 0, 'green' => 0, 'gray' => 0];
        $byAxis = ['bug_pressure' => 0, 'feature_velocity' => 0, 'production_health' => 0, 'doc_coverage' => 0];
        $byConfidence = ['high_75_100' => 0, 'medium_50_74' => 0, 'low_25_49' => 0, 'none_0_24' => 0];
        $totalErrorsLive = 0;
        $totalErrorsHits = 0;

        foreach ($all as $s) {
            $byColor[$s->health_color ?: 'gray'] = ($byColor[$s->health_color ?: 'gray'] ?? 0) + 1;
            if ($s->worst_axis && isset($byAxis[$s->worst_axis])) $byAxis[$s->worst_axis]++;
            $c = (int) $s->confidence_score;
            if ($c >= 75) $byConfidence['high_75_100']++;
            elseif ($c >= 50) $byConfidence['medium_50_74']++;
            elseif ($c >= 25) $byConfidence['low_25_49']++;
            else $byConfidence['none_0_24']++;
            $totalErrorsLive += (int) $s->errors_open + (int) $s->errors_acknowledged;
            $totalErrorsHits += (int) $s->errors_total_hits;
        }

        $filtered = $all;
        if ($this->colorFilter !== 'all') {
            $filtered = $filtered->filter(fn ($s) => ($s->health_color ?: 'gray') === $this->colorFilter);
        }
        if ($this->axisFilter !== 'all') {
            $filtered = $filtered->filter(fn ($s) => $s->worst_axis === $this->axisFilter);
        }

        $colorRank = ['red' => 0, 'yellow' => 1, 'gray' => 2, 'green' => 3];
        $filtered = match ($this->sort) {
            'best' => $filtered->sortByDesc(fn ($s) => $s->health_score ?? -1)->values(),
            'movement' => $filtered->sortByDesc(fn ($s) => $s->last_movement_at?->timestamp ?? 0)->values(),
            'confidence' => $filtered->sortBy('confidence_score')->values(),
            'name' => $filtered->sortBy(fn ($s) => mb_strtolower($s->package?->name ?? ''))->values(),
            default => $filtered->sort(function ($a, $b) use ($colorRank) {
                $ra = $colorRank[$a->health_color ?? 'gray'] ?? 9;
                $rb = $colorRank[$b->health_color ?? 'gray'] ?? 9;
                if ($ra !== $rb) return $ra <=> $rb;
                return (int) ($a->health_score ?? 999) <=> (int) ($b->health_score ?? 999);
            })->values(),
        };

        $withDelta = $all->filter(fn ($s) => $s->delta_health_score !== null && $s->delta_health_score !== 0);
        $topGainers = $withDelta->filter(fn ($s) => $s->delta_health_score > 0)->sortByDesc('delta_health_score')->take(5)->values();
        $topLosers = $withDelta->filter(fn ($s) => $s->delta_health_score < 0)->sortBy('delta_health_score')->take(5)->values();

        return view('dev::livewire.health-index', [
            'team' => $team,
            'totalAll' => $totalAll,
            'byColor' => $byColor,
            'byAxis' => $byAxis,
            'byConfidence' => $byConfidence,
            'totalErrorsLive' => $totalErrorsLive,
            'totalErrorsHits' => $totalErrorsHits,
            'snapshots' => $filtered,
            'lastTakenOn' => $all->max('taken_on'),
            'topGainers' => $topGainers,
            'topLosers' => $topLosers,
            'movedPackagesCount' => $withDelta->count(),
        ]);
    }
}
