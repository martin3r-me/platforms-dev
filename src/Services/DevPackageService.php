<?php

namespace Platform\Dev\Services;

use Platform\Dev\Models\DevPackage;
use Platform\Dev\Models\DevBoard;
use Platform\Dev\Models\DevBoardSlot;

class DevPackageService
{
    public const DEFAULT_SLOT_NAMES = ['Backlog', 'To Do', 'In Progress', 'Review', 'Done'];

    public function activate(array $data): DevPackage
    {
        $package = DevPackage::create($data);

        $this->createDefaultBoards($package);

        return $package;
    }

    public function deactivate(DevPackage $package): DevPackage
    {
        $package->update(['status' => 'archived']);
        return $package->fresh();
    }

    public function reactivate(DevPackage $package): DevPackage
    {
        $package->update(['status' => 'active']);
        return $package->fresh();
    }

    protected function createDefaultBoards(DevPackage $package): void
    {
        $boards = [
            ['name' => 'Features', 'type' => 'feature', 'order' => 0],
            ['name' => 'Bugs', 'type' => 'bug', 'order' => 1],
        ];

        foreach ($boards as $boardData) {
            $board = DevBoard::create([
                'team_id' => $package->team_id,
                'created_by_user_id' => $package->created_by_user_id,
                'dev_package_id' => $package->id,
                'name' => $boardData['name'],
                'type' => $boardData['type'],
                'order' => $boardData['order'],
            ]);

            $this->createDefaultSlots($board);
        }
    }

    public function createDefaultSlots(DevBoard $board): void
    {
        foreach (self::DEFAULT_SLOT_NAMES as $order => $name) {
            DevBoardSlot::create([
                'team_id' => $board->team_id,
                'created_by_user_id' => $board->created_by_user_id,
                'dev_board_id' => $board->id,
                'name' => $name,
                'order' => $order,
            ]);
        }
    }
}
