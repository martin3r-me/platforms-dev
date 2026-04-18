<?php

namespace Platform\Dev\Livewire\Package;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Platform\Dev\Models\DevPackage;
use Platform\Dev\Models\DevIssue;
use Platform\Dev\Services\DevIssueService;

class Issue extends Component
{
    public DevPackage $package;
    public DevIssue $issue;

    public bool $editing = false;
    public string $editTitle = '';
    public string $editDescription = '';
    public string $editPriority = 'normal';

    public function mount(DevPackage $package, DevIssue $issue): void
    {
        $this->package = $package;
        $this->issue = $issue;
    }

    public function startEditing(): void
    {
        $this->editTitle = $this->issue->title;
        $this->editDescription = $this->issue->description ?? '';
        $this->editPriority = $this->issue->priority instanceof \BackedEnum ? $this->issue->priority->value : $this->issue->priority;
        $this->editing = true;
    }

    public function saveEdit(): void
    {
        if (trim($this->editTitle) === '') {
            return;
        }

        $service = new DevIssueService();
        $this->issue = $service->updateIssue($this->issue, [
            'title' => trim($this->editTitle),
            'description' => trim($this->editDescription) ?: null,
            'priority' => $this->editPriority,
        ]);

        $this->editing = false;
    }

    public function cancelEdit(): void
    {
        $this->editing = false;
    }

    public function toggleDone(): void
    {
        if ($this->issue->is_done) {
            $this->issue->reopen();
        } else {
            $this->issue->close();
        }
        $this->issue->refresh();
    }

    public function render()
    {
        $this->issue->load(['board', 'slot', 'userInCharge', 'createdBy']);

        return view('dev::livewire.package.issue', [
        ])->layout('platform::layouts.app');
    }
}
