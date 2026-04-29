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

    public function bulkUpdate(array $issues, array $data): int
    {
        $count = 0;
        foreach ($issues as $issue) {
            $issue->update($data);
            $count++;
        }
        return $count;
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
