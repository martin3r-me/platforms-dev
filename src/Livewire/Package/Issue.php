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

    // Inline-editable properties
    public string $title = '';
    public string $description = '';
    public string $priority = 'normal';
    public ?string $storyPoints = null;
    public ?int $userInChargeId = null;
    public ?string $dueDate = null;
    public ?int $slotId = null;

    // Acceptance criteria (DoD)
    public string $newCriterion = '';

    public function mount(DevPackage $package, DevIssue $issue): void
    {
        $this->package = $package;
        $this->issue = $issue;

        $this->title = $issue->title;
        $this->description = $issue->description ?? '';
        $this->priority = $issue->priority instanceof \BackedEnum ? $issue->priority->value : $issue->priority;
        $this->storyPoints = $issue->story_points?->value;
        $this->userInChargeId = $issue->user_in_charge_id;
        $this->dueDate = $issue->due_date?->format('Y-m-d');
        $this->slotId = $issue->dev_board_slot_id;
    }

    public function rendered(): void
    {
        // Comms - Communication (Threads, not channels)
        $this->dispatch('comms', [
            'model' => get_class($this->issue),
            'modelId' => $this->issue->id,
            'subject' => $this->issue->title,
            'description' => $this->issue->description ?? '',
            'url' => route('dev.packages.issues.show', [$this->package, $this->issue]),
            'source' => 'dev.issue.view',
            'recipients' => array_filter([$this->issue->user_in_charge_id]),
            'capabilities' => [
                'manage_channels' => false,
                'threads' => true,
            ],
            'meta' => [
                'priority' => $this->issue->priority instanceof \BackedEnum ? $this->issue->priority->value : $this->issue->priority,
                'due_date' => $this->issue->due_date,
                'is_done' => $this->issue->is_done,
            ],
        ]);

        // Terminal Activity + Files + Tags
        $this->dispatch('terminal:app:activity');
        $this->dispatch('terminal:app:files');
        $this->dispatch('terminal:app:tags');

        // Organization - Time Tracking only (entities/dimensions on package level)
        $this->dispatch('organization', [
            'context_type' => get_class($this->issue),
            'context_id' => $this->issue->id,
            'linked_contexts' => [
                ['type' => get_class($this->package), 'id' => $this->package->id],
            ],
            'allow_time_entry' => true,
            'allow_entities' => false,
            'allow_dimensions' => false,
        ]);

        // ExtraFields - point to parent package for definitions
        $this->dispatch('extrafields', [
            'context_type' => get_class($this->package),
            'context_id' => $this->package->id,
        ]);

        // Playground - LLM context
        $this->dispatch('playground', [
            'type' => 'Issue',
            'model' => get_class($this->issue),
            'modelId' => $this->issue->id,
            'title' => $this->issue->title,
            'description' => $this->issue->description ?? '',
            'url' => route('dev.packages.issues.show', [$this->package, $this->issue]),
            'source' => 'dev.issue.view',
            'meta' => [
                'priority' => $this->issue->priority instanceof \BackedEnum ? $this->issue->priority->value : $this->issue->priority,
                'story_points' => $this->issue->story_points,
                'due_date' => $this->issue->due_date?->toIso8601String(),
                'is_done' => $this->issue->is_done,
                'package' => $this->package->name,
                'board' => $this->issue->board?->name,
                'slot' => $this->issue->slot?->name ?? 'Backlog',
            ],
        ]);
    }

    // --- Inline Save Methods ---

    public function updateTitle(): void
    {
        if (trim($this->title) === '') {
            $this->title = $this->issue->title;
            return;
        }

        $service = new DevIssueService();
        $this->issue = $service->updateIssue($this->issue, ['title' => trim($this->title)]);
    }

    public function updateDescription(): void
    {
        $service = new DevIssueService();
        $this->issue = $service->updateIssue($this->issue, ['description' => trim($this->description) ?: null]);
    }

    public function updatePriority(string $value): void
    {
        $this->priority = $value;
        $service = new DevIssueService();
        $this->issue = $service->updateIssue($this->issue, ['priority' => $value]);
    }

    public function updateStoryPoints(): void
    {
        $service = new DevIssueService();
        $this->issue = $service->updateIssue($this->issue, ['story_points' => $this->storyPoints ?: null]);
        $this->storyPoints = $this->issue->story_points?->value;
    }

    public function updateUserInCharge($userId): void
    {
        $this->userInChargeId = $userId ?: null;
        $service = new DevIssueService();
        $this->issue = $service->updateIssue($this->issue, ['user_in_charge_id' => $userId ?: null]);
    }

    public function updateDueDate(): void
    {
        $service = new DevIssueService();
        $this->issue = $service->updateIssue($this->issue, ['due_date' => $this->dueDate ?: null]);
    }

    public function updateSlot($slotId): void
    {
        $this->slotId = $slotId ?: null;
        $service = new DevIssueService();
        $this->issue = $service->updateIssue($this->issue, ['dev_board_slot_id' => $slotId ?: null]);
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

    // --- Acceptance Criteria (DoD) ---

    public function addCriterion(): void
    {
        if (trim($this->newCriterion) === '') {
            return;
        }

        $criteria = $this->issue->acceptance_criteria ?? [];
        $criteria[] = [
            'text' => trim($this->newCriterion),
            'done' => false,
        ];

        $this->issue->update(['acceptance_criteria' => $criteria]);
        $this->newCriterion = '';
    }

    public function toggleCriterion(int $index): void
    {
        $criteria = $this->issue->acceptance_criteria ?? [];
        if (isset($criteria[$index])) {
            $criteria[$index]['done'] = !$criteria[$index]['done'];
            $this->issue->update(['acceptance_criteria' => $criteria]);
        }
    }

    public function removeCriterion(int $index): void
    {
        $criteria = $this->issue->acceptance_criteria ?? [];
        unset($criteria[$index]);
        $this->issue->update(['acceptance_criteria' => array_values($criteria)]);
    }

    public function render()
    {
        $this->issue->load(['board', 'slot', 'userInCharge', 'createdBy']);

        $criteria = $this->issue->acceptance_criteria ?? [];
        $criteriaTotal = count($criteria);
        $criteriaDone = collect($criteria)->where('done', true)->count();

        // Team members for user assignment
        $teamUsers = Auth::user()
            ->currentTeam
            ->users()
            ->orderBy('name')
            ->get()
            ->map(fn ($user) => ['id' => $user->id, 'name' => $user->fullname ?? $user->name]);

        // Board slots for slot assignment
        $boardSlots = $this->issue->board
            ? $this->issue->board->slots()->orderBy('order')->get()->map(fn ($s) => ['id' => $s->id, 'name' => $s->name])
            : collect();

        return view('dev::livewire.package.issue', [
            'criteria' => $criteria,
            'criteriaTotal' => $criteriaTotal,
            'criteriaDone' => $criteriaDone,
            'teamUsers' => $teamUsers,
            'boardSlots' => $boardSlots,
        ])->layout('platform::layouts.app');
    }
}
