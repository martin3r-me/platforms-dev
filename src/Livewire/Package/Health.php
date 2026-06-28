<?php

namespace Platform\Dev\Livewire\Package;

use Carbon\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Platform\Dev\Models\DevPackage;
use Platform\Dev\Models\DevPackageSnapshot;
use Platform\Dev\Services\DevPackageSnapshotService;

class Health extends Component
{
    public DevPackage $package;

    public int $trendDays = 30;

    public function mount(DevPackage $package): void
    {
        $this->package = $package;
    }

    public function setTrendDays(int $days): void
    {
        $this->trendDays = max(7, min(180, $days));
    }

    public function refreshSnapshot(DevPackageSnapshotService $service): void
    {
        $service->snapshot($this->package, 'manual');

        $this->dispatch('notifications:store', [
            'title' => 'Snapshot aktualisiert',
            'message' => 'Der Health-Stand wurde gerade neu berechnet.',
            'notice_type' => 'success',
            'noticable_type' => DevPackage::class,
            'noticable_id' => $this->package->id,
        ]);
    }

    #[Layout('platform::layouts.app')]
    public function render()
    {
        $latest = DevPackageSnapshot::with(['topIssues', 'topErrors', 'people', 'boards'])
            ->where('dev_package_id', $this->package->id)
            ->orderByDesc('taken_on')
            ->first();

        $from = Carbon::now()->subDays($this->trendDays - 1)->toDateString();
        $to = Carbon::now()->toDateString();

        $trend = DevPackageSnapshot::where('dev_package_id', $this->package->id)
            ->whereBetween('taken_on', [$from, $to])
            ->orderBy('taken_on')
            ->get([
                'id', 'taken_on',
                'health_score', 'health_color', 'worst_axis', 'axis_scores',
                'confidence_score',
                'issues_open', 'issues_done', 'bugs_open', 'features_open',
                'errors_open', 'errors_total_hits',
                'story_points_open', 'story_points_done',
            ]);

        return view('dev::livewire.package.health', [
            'package' => $this->package,
            'latest' => $latest,
            'trend' => $trend,
            'trendFrom' => $from,
            'trendTo' => $to,
        ]);
    }
}
