<?php

namespace Platform\Dev\Livewire;

use Livewire\Component;
use Livewire\Attributes\On;
use Platform\Dev\Models\DevPackage;
use Platform\Dev\Models\DevIssue;

class Sidebar extends Component
{
    #[On('updateSidebar')]
    public function updateSidebar()
    {
    }

    public function render()
    {
        $user = auth()->user();

        if (!$user) {
            return view('dev::livewire.sidebar', [
                'activePackages' => collect(),
                'archivedPackages' => collect(),
            ]);
        }

        $teamId = $user->currentTeam->id ?? null;

        $activePackages = $teamId
            ? DevPackage::where('team_id', $teamId)
                ->where('status', 'active')
                ->with(['boards' => fn ($q) => $q->active()->withCount([
                    'issues as open_issues_count' => fn ($q) => $q->where('status', 'open'),
                ])->orderBy('order')])
                ->orderBy('order')
                ->get()
            : collect();

        // Eager-load open bugs + feature counts per package
        if ($activePackages->isNotEmpty()) {
            $packageIds = $activePackages->pluck('id');

            $bugCounts = DevIssue::query()
                ->join('dev_boards', 'dev_issues.dev_board_id', '=', 'dev_boards.id')
                ->where('dev_boards.type', 'bug')
                ->where('dev_issues.status', 'open')
                ->whereIn('dev_boards.dev_package_id', $packageIds)
                ->selectRaw('dev_boards.dev_package_id, COUNT(*) as count')
                ->groupBy('dev_boards.dev_package_id')
                ->pluck('count', 'dev_package_id');

            $featureCounts = DevIssue::query()
                ->join('dev_boards', 'dev_issues.dev_board_id', '=', 'dev_boards.id')
                ->where('dev_boards.type', 'feature')
                ->where('dev_issues.status', 'open')
                ->whereIn('dev_boards.dev_package_id', $packageIds)
                ->selectRaw('dev_boards.dev_package_id, COUNT(*) as count')
                ->groupBy('dev_boards.dev_package_id')
                ->pluck('count', 'dev_package_id');

            $activePackages->each(function ($package) use ($bugCounts, $featureCounts) {
                $package->open_bugs_count = $bugCounts[$package->id] ?? 0;
                $package->open_features_count = $featureCounts[$package->id] ?? 0;
            });
        }

        $archivedPackages = $teamId
            ? DevPackage::where('team_id', $teamId)->where('status', 'archived')->orderBy('name')->get()
            : collect();

        return view('dev::livewire.sidebar', [
            'activePackages' => $activePackages,
            'archivedPackages' => $archivedPackages,
        ]);
    }
}
