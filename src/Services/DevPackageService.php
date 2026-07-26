<?php

namespace Platform\Dev\Services;

use Platform\Dev\Models\DevPackage;
use Platform\Dev\Models\DevBoard;
use Platform\Dev\Models\DevBoardSlot;

class DevPackageService
{
    /**
     * Standard-Slots für jedes Board (immer gleich). Die Worker-Rolle ist per
     * Konvention fest zugeordnet — kein Mensch konfiguriert Slots. Backlog hat
     * keine Rolle (Worker ignoriert es).
     */
    public const DEFAULT_SLOTS = [
        ['name' => 'Backlog',   'role' => null],
        ['name' => 'Ready',     'role' => 'ready'],
        ['name' => 'In Arbeit', 'role' => 'working'],
        ['name' => 'Rückfrage', 'role' => 'human'],
        ['name' => 'Fertig',    'role' => 'done'],
    ];

    public function activate(array $data): DevPackage
    {
        $package = DevPackage::create($data);

        $this->createDefaultBoards($package);

        (new DevDocService())->initializeDocumentation($package, $package->created_by_user_id);

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
            ['name' => 'Inbox', 'type' => 'inbox', 'order' => 0],
            ['name' => 'Features', 'type' => 'feature', 'order' => 1],
            ['name' => 'Bugs', 'type' => 'bug', 'order' => 2],
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

    /**
     * Return the package's inbox board, creating it (with default slots) on
     * demand. Packages created before the inbox board existed get one lazily
     * the first time a feature request is ingested for them.
     */
    public function getOrCreateInboxBoard(DevPackage $package): DevBoard
    {
        $board = $package->boards()->where('type', 'inbox')->first();

        if ($board) {
            return $board;
        }

        $board = DevBoard::create([
            'team_id' => $package->team_id,
            'created_by_user_id' => $package->user_in_charge_id ?? $package->created_by_user_id,
            'dev_package_id' => $package->id,
            'name' => 'Inbox',
            'type' => 'inbox',
            'order' => 0,
        ]);

        $this->createDefaultSlots($board);

        return $board;
    }

    public function createDefaultSlots(DevBoard $board): void
    {
        foreach (self::DEFAULT_SLOTS as $order => $slot) {
            DevBoardSlot::create([
                'team_id' => $board->team_id,
                'created_by_user_id' => $board->created_by_user_id,
                'dev_board_id' => $board->id,
                'name' => $slot['name'],
                'order' => $order,
                'agent_role' => $slot['role'],
            ]);
        }
    }
}
