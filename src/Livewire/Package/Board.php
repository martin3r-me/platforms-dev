<?php

namespace Platform\Dev\Livewire\Package;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Platform\Dev\Models\DevPackage;
use Platform\Dev\Models\DevBoard;
use Platform\Dev\Models\DevIssue;
use Platform\Dev\Services\DevIssueService;

class Board extends Component
{
    public DevPackage $package;
    public DevBoard $board;

    public string $newIssueTitle = '';
    public ?int $newIssueSlotId = null;

    public function mount(DevPackage $package, DevBoard $board): void
    {
        $this->package = $package;
        $this->board = $board;
    }

    public function createIssue(): void
    {
        $user = Auth::user();
        $team = $user->currentTeam;

        if (!$team || trim($this->newIssueTitle) === '') {
            return;
        }

        $service = new DevIssueService();
        $service->createIssue([
            'team_id' => $team->id,
            'created_by_user_id' => $user->id,
            'dev_board_id' => $this->board->id,
            'dev_board_slot_id' => $this->newIssueSlotId,
            'title' => trim($this->newIssueTitle),
        ]);

        $this->newIssueTitle = '';
        $this->newIssueSlotId = null;
    }

    public function moveIssue(int $issueId, ?int $slotId): void
    {
        $issue = DevIssue::where('dev_board_id', $this->board->id)->find($issueId);
        if (!$issue) {
            return;
        }

        $service = new DevIssueService();
        $service->moveToSlot($issue, $slotId);
    }

    public function closeIssue(int $issueId): void
    {
        $issue = DevIssue::where('dev_board_id', $this->board->id)->find($issueId);
        if ($issue) {
            $issue->close();
        }
    }

    public function reopenIssue(int $issueId): void
    {
        $issue = DevIssue::where('dev_board_id', $this->board->id)->find($issueId);
        if ($issue) {
            $issue->reopen();
        }
    }

    public function render()
    {
        $slots = $this->board->slots()
            ->with(['issues' => fn ($q) => $q->where('status', 'open')->orderBy('slot_order')])
            ->orderBy('order')
            ->get();

        $backlogIssues = DevIssue::where('dev_board_id', $this->board->id)
            ->whereNull('dev_board_slot_id')
            ->where('status', 'open')
            ->orderBy('order')
            ->get();

        $closedCount = DevIssue::where('dev_board_id', $this->board->id)
            ->where('status', 'closed')
            ->count();

        return view('dev::livewire.package.board', [
            'slots' => $slots,
            'backlogIssues' => $backlogIssues,
            'closedCount' => $closedCount,
        ])->layout('platform::layouts.app');
    }
}
