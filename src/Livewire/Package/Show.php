<?php

namespace Platform\Dev\Livewire\Package;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Platform\Dev\Models\DevPackage;
use Platform\Dev\Models\DevIssue;

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

        $totalOpen = DevIssue::whereHas('board', fn ($q) => $q->where('dev_package_id', $this->package->id))
            ->where('status', 'open')
            ->count();

        $totalDone = DevIssue::whereHas('board', fn ($q) => $q->where('dev_package_id', $this->package->id))
            ->where('is_done', true)
            ->count();

        $totalOverdue = DevIssue::whereHas('board', fn ($q) => $q->where('dev_package_id', $this->package->id))
            ->where('status', 'open')
            ->whereNotNull('due_date')
            ->where('due_date', '<', now())
            ->count();

        return view('dev::livewire.package.show', [
            'boards' => $boards,
            'totalOpen' => $totalOpen,
            'totalDone' => $totalDone,
            'totalOverdue' => $totalOverdue,
        ])->layout('platform::layouts.app');
    }
}
