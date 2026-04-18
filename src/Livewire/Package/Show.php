<?php

namespace Platform\Dev\Livewire\Package;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Platform\Dev\Models\DevPackage;
use Platform\Dev\Models\DevIssue;
use Platform\Integrations\Models\IntegrationGithubCommit;
use Platform\Integrations\Models\IntegrationGithubPullRequest;
use Platform\Integrations\Models\IntegrationGithubRepo;

class Show extends Component
{
    public DevPackage $package;

    public function mount(DevPackage $package): void
    {
        $this->package = $package;
    }

    public function rendered(): void
    {
        // Comms - Communication/Channel Integration
        $this->dispatch('comms', [
            'model' => get_class($this->package),
            'modelId' => $this->package->id,
            'subject' => $this->package->name,
            'description' => $this->package->description ?? '',
            'url' => route('dev.packages.show', $this->package),
            'source' => 'dev.package.view',
            'recipients' => array_filter([$this->package->user_in_charge_id]),
            'capabilities' => [
                'manage_channels' => true,
                'threads' => false,
            ],
            'meta' => [
                'status' => $this->package->status,
                'github_repo' => $this->package->github_repo_full_name,
                'created_at' => $this->package->created_at,
            ],
        ]);

        // Terminal Activity + Files + Tags
        $this->dispatch('terminal:app:activity');
        $this->dispatch('terminal:app:files');
        $this->dispatch('terminal:app:tags');

        // Organization - Time Tracking + Entity Linking + Dimensions
        $this->dispatch('organization', [
            'context_type' => get_class($this->package),
            'context_id' => $this->package->id,
            'allow_time_entry' => true,
            'allow_entities' => true,
            'allow_dimensions' => true,
            'include_children_relations' => ['boards.issues'],
        ]);

        // ExtraFields
        $this->dispatch('extrafields', [
            'context_type' => get_class($this->package),
            'context_id' => $this->package->id,
        ]);
    }

    public function render()
    {
        $boards = $this->package->boards()
            ->withCount(['issues as open_issues_count' => fn ($q) => $q->where('status', 'open')])
            ->orderBy('order')
            ->get();

        $packageIssues = DevIssue::whereHas('board', fn ($q) => $q->where('dev_package_id', $this->package->id));

        $totalOpen = (clone $packageIssues)->where('status', 'open')->count();
        $totalDone = (clone $packageIssues)->where('is_done', true)->count();
        $totalOverdue = (clone $packageIssues)
            ->where('status', 'open')
            ->whereNotNull('due_date')
            ->where('due_date', '<', now())
            ->count();
        $totalHighPriority = (clone $packageIssues)->where('status', 'open')->where('priority', 'high')->count();

        // Recent open issues for this package
        $recentIssues = DevIssue::whereHas('board', fn ($q) => $q->where('dev_package_id', $this->package->id))
            ->where('status', 'open')
            ->with(['board', 'userInCharge'])
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        // Recently completed
        $recentlyDone = DevIssue::whereHas('board', fn ($q) => $q->where('dev_package_id', $this->package->id))
            ->where('is_done', true)
            ->with(['board'])
            ->orderByDesc('done_at')
            ->limit(5)
            ->get();

        // GitHub data for this package
        $repoIds = collect();
        if ($this->package->github_repo_full_name) {
            $repoIds = IntegrationGithubRepo::where('full_name', $this->package->github_repo_full_name)
                ->where('is_active', true)
                ->pluck('id');
        }

        $recentCommits = $repoIds->isNotEmpty()
            ? IntegrationGithubCommit::whereIn('repo_id', $repoIds)
                ->orderByDesc('committed_at')
                ->limit(15)
                ->get()
            : collect();

        $openPullRequests = $repoIds->isNotEmpty()
            ? IntegrationGithubPullRequest::whereIn('repo_id', $repoIds)
                ->where('state', 'open')
                ->orderByDesc('github_created_at')
                ->limit(10)
                ->get()
            : collect();

        return view('dev::livewire.package.show', [
            'boards' => $boards,
            'totalOpen' => $totalOpen,
            'totalDone' => $totalDone,
            'totalOverdue' => $totalOverdue,
            'totalHighPriority' => $totalHighPriority,
            'recentIssues' => $recentIssues,
            'recentlyDone' => $recentlyDone,
            'recentCommits' => $recentCommits,
            'openPullRequests' => $openPullRequests,
        ])->layout('platform::layouts.app');
    }
}
