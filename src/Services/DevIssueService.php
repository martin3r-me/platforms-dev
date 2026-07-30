<?php

namespace Platform\Dev\Services;

use Platform\Dev\Models\DevIssue;

class DevIssueService
{
    public function createIssue(array $data): DevIssue
    {
        return DevIssue::create($data);
    }

    public function updateIssue(DevIssue $issue, array $data): DevIssue
    {
        $issue->update($data);
        return $issue->fresh();
    }

    public function moveToSlot(DevIssue $issue, ?int $slotId, ?int $slotOrder = null): DevIssue
    {
        $updateData = ['dev_board_slot_id' => $slotId];
        if ($slotOrder !== null) {
            $updateData['slot_order'] = $slotOrder;
        }

        $issue->update($updateData);
        return $issue->fresh();
    }

    /**
     * Ermittelt die naechste freie slot_order am Ende des angegebenen Slots
     * (bzw. Backlog bei $slotId === null). Damit landen sequentiell angelegte
     * Issues am Ende statt alle auf 0 (= nicht-deterministisches Prepend).
     */
    public function nextSlotOrder(int $boardId, ?int $slotId): int
    {
        $query = DevIssue::where('dev_board_id', $boardId);
        $query = $slotId === null
            ? $query->whereNull('dev_board_slot_id')
            : $query->where('dev_board_slot_id', $slotId);

        $max = $query->max('slot_order');

        return $max === null ? 0 : (int) $max + 1;
    }

    /**
     * Setzt die Reihenfolge (slot_order) mehrerer Issues eines Boards
     * deterministisch anhand der uebergebenen ID-Reihenfolge (0-basiert,
     * Index 0 = oberste Position). Wenn $moveToSlot true ist, werden die
     * Issues zusaetzlich in $slotId verschoben ($slotId === null = Backlog).
     * Nicht gefundene / boardfremde IDs werden uebersprungen.
     *
     * @param  int[]  $issueIds
     * @return array{updated: DevIssue[], skipped: int[]}
     */
    public function reorderIssues(int $boardId, array $issueIds, ?int $slotId = null, bool $moveToSlot = false): array
    {
        $updated = [];
        $skipped = [];

        foreach (array_values($issueIds) as $position => $issueId) {
            $issue = DevIssue::where('dev_board_id', $boardId)->find($issueId);
            if (!$issue) {
                $skipped[] = (int) $issueId;
                continue;
            }

            $data = [
                'slot_order' => $position,
                'order' => $position,
            ];
            if ($moveToSlot) {
                $data['dev_board_slot_id'] = $slotId;
            }

            $issue->update($data);
            $updated[] = $issue->fresh();
        }

        return ['updated' => $updated, 'skipped' => $skipped];
    }

    public function bulkCreate(int $boardId, int $teamId, int $userId, array $issues): array
    {
        $created = [];
        foreach ($issues as $issueData) {
            $created[] = DevIssue::create(array_merge($issueData, [
                'dev_board_id' => $boardId,
                'team_id' => $teamId,
                'created_by_user_id' => $userId,
            ]));
        }
        return $created;
    }
}
