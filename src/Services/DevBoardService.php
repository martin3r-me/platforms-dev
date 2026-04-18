<?php

namespace Platform\Dev\Services;

use Platform\Dev\Models\DevBoard;
use Platform\Dev\Models\DevBoardSlot;

class DevBoardService
{
    public function createBoard(array $data): DevBoard
    {
        $board = DevBoard::create($data);

        if (($data['create_default_slots'] ?? true) === true) {
            (new DevPackageService())->createDefaultSlots($board);
        }

        return $board;
    }

    public function updateBoard(DevBoard $board, array $data): DevBoard
    {
        $board->update($data);
        return $board->fresh();
    }

    public function reorderSlots(DevBoard $board, array $slotIds): void
    {
        foreach ($slotIds as $position => $slotId) {
            DevBoardSlot::where('id', $slotId)
                ->where('dev_board_id', $board->id)
                ->update(['order' => $position]);
        }
    }
}
