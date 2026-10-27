<?php

namespace Platform\Dev\Livewire;

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;
use Platform\Dev\Models\DevBoard;
use Platform\Dev\Services\DevIssueService;

/**
 * Zentrales Anlegen aus dem "+ Neu"-Menü der Dev-Actionbar: legt ein Issue im
 * Backlog des gewählten Boards an und öffnet es direkt.
 */
class QuickCreate extends Component
{
    #[On('dev-create')]
    public function create(string $type, ?int $boardId = null): void
    {
        if ($type !== 'issue' || ! $boardId) {
            return;
        }

        $user = Auth::user();
        $team = $user?->currentTeam;
        $board = DevBoard::with('package')->find($boardId);

        if (! $team || ! $board || $board->package?->team_id !== $team->id) {
            return;
        }

        $issue = (new DevIssueService())->createIssue([
            'team_id' => $team->id,
            'created_by_user_id' => $user->id,
            'dev_board_id' => $board->id,
            'dev_board_slot_id' => null,
            'title' => 'Neues Issue',
        ]);

        $this->redirectRoute('dev.packages.issues.show', [$board->package, $issue], navigate: true);
    }

    public function render()
    {
        return <<<'HTML'
            <div hidden></div>
        HTML;
    }
}
