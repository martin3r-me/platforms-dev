<?php

namespace Platform\Dev\Observers;

use Platform\Dev\Enums\BoardType;
use Platform\Dev\Models\DevIssue;
use Platform\Dev\Services\DevBoardService;

/**
 * Archiviert ein Feature-Board automatisch, sobald sein letztes offenes
 * Ticket erledigt wird — unabhaengig davon, ob Worker, Mensch oder MCP
 * das Issue geschlossen hat. Bug-Boards laufen dauerhaft und werden
 * nicht angefasst.
 */
class DevIssueObserver
{
    public function updated(DevIssue $issue): void
    {
        if (!$issue->wasChanged(['is_done', 'status'])) {
            return;
        }

        if (!$issue->is_done && $issue->status !== 'closed') {
            return;
        }

        $board = $issue->board;

        if (!$board || $board->type !== BoardType::Feature || $board->status === 'archived') {
            return;
        }

        if ($board->issues()->open()->exists()) {
            return;
        }

        (new DevBoardService())->archiveBoard($board);
    }
}
