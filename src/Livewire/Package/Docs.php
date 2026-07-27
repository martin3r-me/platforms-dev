<?php

namespace Platform\Dev\Livewire\Package;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Platform\Dev\Models\DevPackage;
use Platform\Dev\Services\DevDocService;

class Docs extends Component
{
    public DevPackage $package;

    public function mount(DevPackage $package): void
    {
        $this->package = $package;
    }

    public function initializeDocs(): void
    {
        $docService = new DevDocService();
        $docService->initializeDocumentation($this->package, Auth::id());
    }

    public function render()
    {
        $docPages = $this->package->docPages()
            ->with('lastEditedBy:id,name')
            ->withCount('revisions')
            ->orderBy('position')
            ->orderBy('title')
            ->get();

        $docPublishedCount = $docPages->where('status', 'published')->count();

        $boards = $this->package->boards()
            ->active()
            ->withCount(['issues as open_issues_count' => fn ($q) => $q->open()])
            ->orderBy('order')
            ->get();

        return view('dev::livewire.package.docs', [
            'docPages' => $docPages,
            'docPublishedCount' => $docPublishedCount,
            'boards' => $boards,
        ])->layout('platform::layouts.app');
    }
}
