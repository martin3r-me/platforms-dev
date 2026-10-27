<?php

namespace Platform\Dev\Livewire\Package;

use Illuminate\Database\Eloquent\Model;
use Livewire\Component;
use Platform\Core\Livewire\Concerns\HasPageContext;
use Illuminate\Support\Facades\Auth;
use Platform\Dev\Models\DevPackage;
use Platform\Dev\Models\DevIssue;
use Platform\Dev\Services\DevIssueService;

class Issue extends Component
{
    use HasPageContext;

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

    protected function pageContext(): ?Model
    {
        return $this->issue;
    }

    protected function pageContextOptions(): array
    {
        return [
            'subject' => $this->issue->title,
            'url' => route('dev.packages.issues.show', [$this->package, $this->issue]),
            'source' => 'dev.issue.view',
            // In die Organisation wird das Package gehängt, nicht das einzelne Issue
            'apps' => ['links' => false],
            'recipients' => array_filter([$this->issue->user_in_charge_id]),
            'meta' => [
                'priority' => $this->issue->priority instanceof \BackedEnum ? $this->issue->priority->value : $this->issue->priority,
                'due_date' => $this->issue->due_date,
                'is_done' => $this->issue->is_done,
            ],
        ];
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

    public function addCriterion(?string $text = null): void
    {
        $criterionText = trim($text ?? $this->newCriterion);
        if ($criterionText === '') {
            return;
        }

        $criteria = $this->issue->acceptance_criteria ?? [];
        $criteria[] = [
            'text' => $criterionText,
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
