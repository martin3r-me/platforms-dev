<?php

namespace Platform\Dev\Livewire;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Platform\Dev\Models\DevPackage;
use Platform\Dev\Models\DevPackageErrorSettings;
use Platform\Dev\Models\DevIssue;
use Platform\Dev\Services\DevPackageService;
use Platform\Integrations\Models\IntegrationGithubCommit;
use Platform\Integrations\Models\IntegrationGithubPullRequest;
use Platform\Integrations\Models\IntegrationGithubRepo;

class Dashboard extends Component
{
    public string $activatePackageName = '';
    public string $activatePackageDescription = '';
    public ?int $selectedRepoId = null;
    public bool $showActivateModal = false;
    public bool $showErrorTracking = false;

    public function openActivateModal(): void
    {
        $this->activatePackageName = '';
        $this->activatePackageDescription = '';
        $this->selectedRepoId = null;
        $this->showActivateModal = true;
    }

    public function updatedSelectedRepoId($value): void
    {
        if ($value) {
            $repos = $this->getAvailableRepos();
            $repo = $repos->firstWhere('id', (int) $value);
            if ($repo && $this->activatePackageName === '') {
                $this->activatePackageName = $repo->name;
            }
        }
    }

    public function activatePackage(): void
    {
        $user = Auth::user();
        $team = $user->currentTeam;

        if (!$team || trim($this->activatePackageName) === '') {
            return;
        }

        $repoFullName = null;
        if ($this->selectedRepoId) {
            $repos = $this->getAvailableRepos();
            $repo = $repos->firstWhere('id', (int) $this->selectedRepoId);
            if ($repo) {
                $repoFullName = $repo->full_name;
            }
        }

        $service = new DevPackageService();
        $package = $service->activate([
            'name' => trim($this->activatePackageName),
            'description' => trim($this->activatePackageDescription) ?: null,
            'github_repo_full_name' => $repoFullName,
            'github_repo_id' => $this->selectedRepoId,
            'team_id' => $team->id,
            'created_by_user_id' => $user->id,
            'user_in_charge_id' => $user->id,
        ]);

        $this->showActivateModal = false;
        $this->dispatch('updateSidebar');
        $this->redirect(route('dev.packages.show', $package), navigate: true);
    }

    protected function getAvailableRepos()
    {
        try {
            $user = Auth::user();

            $linkedRepoIds = DevPackage::where('team_id', $user->currentTeam->id)
                ->whereNotNull('github_repo_id')
                ->pluck('github_repo_id');

            return \Platform\Integrations\Models\IntegrationsGithubRepository::query()
                ->where('user_id', $user->id)
                ->whereNotIn('id', $linkedRepoIds)
                ->orderBy('full_name')
                ->get();
        } catch (\Throwable $e) {
            return collect();
        }
    }

    public function generateTeamToken(): void
    {
        $user = Auth::user();
        $team = $user->currentTeam;

        // Find first package with settings, or create settings on first package
        $package = DevPackage::where('team_id', $team->id)->active()->first();
        if (!$package) {
            return;
        }

        $settings = DevPackageErrorSettings::getOrCreateForPackage($package);
        $settings->generateToken();
    }

    public function regenerateTeamToken(): void
    {
        $this->generateTeamToken();
    }

    protected function getTeamIngestUrl(): ?string
    {
        $user = Auth::user();
        $team = $user->currentTeam;

        $settings = DevPackageErrorSettings::whereHas('package', function ($q) use ($team) {
            $q->where('team_id', $team->id);
        })->whereNotNull('ingest_token')->where('enabled', true)->first();

        return $settings?->getIngestUrl();
    }

    public function render()
    {
        $user = Auth::user();
        $team = $user->currentTeam;

        $packages = DevPackage::where('team_id', $team->id)
            ->active()
            ->orderBy('order')
            ->get();

        $totalPackages = $packages->count();

        $teamIssues = DevIssue::whereHas('board.package', fn ($q) => $q->where('team_id', $team->id)->where('status', 'active'));

        $totalOpen = (clone $teamIssues)->where('status', 'open')->count();
        $totalDone = (clone $teamIssues)->where('is_done', true)->count();
        $totalOverdue = (clone $teamIssues)
            ->where('status', 'open')
            ->whereNotNull('due_date')
            ->where('due_date', '<', now())
            ->count();
        $totalHighPriority = (clone $teamIssues)->where('status', 'open')->where('priority', 'high')->count();

        // Recent open issues
        $recentIssues = DevIssue::whereHas('board.package', fn ($q) => $q->where('team_id', $team->id)->where('status', 'active'))
            ->where('status', 'open')
            ->with(['board.package', 'userInCharge', 'createdBy'])
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        // Recently completed
        $recentlyDone = DevIssue::whereHas('board.package', fn ($q) => $q->where('team_id', $team->id)->where('status', 'active'))
            ->where('is_done', true)
            ->with(['board.package', 'userInCharge'])
            ->orderByDesc('done_at')
            ->limit(5)
            ->get();

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

        // GitHub data: recent commits + open PRs across all team packages
        $repoFullNames = $packages->pluck('github_repo_full_name')->filter()->values();
        $repoIds = $repoFullNames->isNotEmpty()
            ? IntegrationGithubRepo::whereIn('full_name', $repoFullNames)->where('is_active', true)->pluck('id')
            : collect();

        $recentCommits = $repoIds->isNotEmpty()
            ? IntegrationGithubCommit::whereIn('repo_id', $repoIds)
                ->with('repo')
                ->orderByDesc('committed_at')
                ->limit(15)
                ->get()
            : collect();

        $openPullRequests = $repoIds->isNotEmpty()
            ? IntegrationGithubPullRequest::whereIn('repo_id', $repoIds)
                ->where('state', 'open')
                ->with('repo')
                ->orderByDesc('github_created_at')
                ->limit(10)
                ->get()
            : collect();

        $availableRepos = $this->showActivateModal ? $this->getAvailableRepos() : collect();

        $ingestUrl = $this->showErrorTracking ? $this->getTeamIngestUrl() : null;
        $errorEndpointConfigured = (bool) config('platform.error_endpoint');

        return view('dev::livewire.dashboard', [
            'packages' => $packages,
            'totalPackages' => $totalPackages,
            'totalOpen' => $totalOpen,
            'totalDone' => $totalDone,
            'totalOverdue' => $totalOverdue,
            'totalHighPriority' => $totalHighPriority,
            'recentIssues' => $recentIssues,
            'recentlyDone' => $recentlyDone,
            'recentCommits' => $recentCommits,
            'openPullRequests' => $openPullRequests,
            'packageStats' => $packageStats,
            'availableRepos' => $availableRepos,
            'ingestUrl' => $ingestUrl,
            'errorEndpointConfigured' => $errorEndpointConfigured,
        ])->layout('platform::layouts.app');
    }
}
