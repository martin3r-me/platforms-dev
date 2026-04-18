<?php

namespace Platform\Dev\Livewire;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Platform\Dev\Models\DevPackage;
use Platform\Dev\Models\DevIssue;
use Platform\Dev\Services\DevPackageService;

class Dashboard extends Component
{
    public string $activatePackageName = '';
    public string $activatePackageDescription = '';
    public string $activatePackageRepo = '';
    public bool $showActivateModal = false;

    public function openActivateModal(): void
    {
        $this->activatePackageName = '';
        $this->activatePackageDescription = '';
        $this->activatePackageRepo = '';
        $this->showActivateModal = true;
    }

    public function activatePackage(): void
    {
        $user = Auth::user();
        $team = $user->currentTeam;

        if (!$team || trim($this->activatePackageName) === '') {
            return;
        }

        $service = new DevPackageService();
        $package = $service->activate([
            'name' => trim($this->activatePackageName),
            'description' => trim($this->activatePackageDescription) ?: null,
            'github_repo_full_name' => trim($this->activatePackageRepo) ?: null,
            'team_id' => $team->id,
            'created_by_user_id' => $user->id,
        ]);

        $this->showActivateModal = false;
        $this->dispatch('updateSidebar');
        return $this->redirect(route('dev.packages.show', $package), navigate: true);
    }

    public function render()
    {
        $user = Auth::user();
        $team = $user->currentTeam;

        $packages = DevPackage::where('team_id', $team->id)
            ->active()
            ->withCount(['boards', 'discussions'])
            ->orderBy('order')
            ->get();

        $totalPackages = $packages->count();
        $totalOpenIssues = DevIssue::whereHas('board.package', fn ($q) => $q->where('team_id', $team->id)->where('status', 'active'))
            ->where('status', 'open')
            ->count();

        // Per-package stats
        $packageStats = [];
        foreach ($packages as $package) {
            $openFeatures = DevIssue::whereHas('board', fn ($q) => $q->where('dev_package_id', $package->id)->where('type', 'feature'))
                ->where('status', 'open')->count();
            $openBugs = DevIssue::whereHas('board', fn ($q) => $q->where('dev_package_id', $package->id)->where('type', 'bug'))
                ->where('status', 'open')->count();
            $packageStats[$package->id] = [
                'open_features' => $openFeatures,
                'open_bugs' => $openBugs,
            ];
        }

        return view('dev::livewire.dashboard', [
            'packages' => $packages,
            'totalPackages' => $totalPackages,
            'totalOpenIssues' => $totalOpenIssues,
            'packageStats' => $packageStats,
        ])->layout('platform::layouts.app');
    }
}
