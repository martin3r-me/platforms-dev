<?php

namespace Platform\Dev\Livewire\Package;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Platform\Dev\Models\DevPackage;
use Platform\Dev\Models\DevBoard;
use Platform\Dev\Models\DevIssue;

class Show extends Component
{
    public DevPackage $package;
    public string $activeTab = 'feature';

    public function mount(DevPackage $package): void
    {
        $this->package = $package;

        $firstBoard = $package->boards()->first();
        if ($firstBoard) {
            $this->activeTab = $firstBoard->type instanceof \BackedEnum ? $firstBoard->type->value : $firstBoard->type;
        }
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

    public function setTab(string $tab): void
    {
        $this->activeTab = $tab;
    }

    public function render()
    {
        $boards = $this->package->boards()
            ->with(['slots' => fn ($q) => $q->orderBy('order')])
            ->orderBy('order')
            ->get();

        $activeBoard = $boards->first(function ($b) {
            $type = $b->type instanceof \BackedEnum ? $b->type->value : $b->type;
            return $type === $this->activeTab;
        }) ?? $boards->first();

        $boardSlots = collect();
        $backlogIssues = collect();

        if ($activeBoard) {
            $boardSlots = $activeBoard->slots()
                ->with(['issues' => fn ($q) => $q->where('status', 'open')->orderBy('slot_order')])
                ->orderBy('order')
                ->get();

            $backlogIssues = DevIssue::where('dev_board_id', $activeBoard->id)
                ->whereNull('dev_board_slot_id')
                ->where('status', 'open')
                ->orderBy('order')
                ->get();
        }

        $discussions = $this->package->discussions()
            ->withCount('replies')
            ->limit(5)
            ->get();

        return view('dev::livewire.package.show', [
            'boards' => $boards,
            'activeBoard' => $activeBoard,
            'boardSlots' => $boardSlots,
            'backlogIssues' => $backlogIssues,
            'discussions' => $discussions,
        ])->layout('platform::layouts.app');
    }
}
