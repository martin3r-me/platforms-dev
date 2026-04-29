<?php

namespace Platform\Dev\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Dev\Models\DevIssue;
use Platform\Dev\Services\DevIssueService;
use Platform\Dev\Tools\Concerns\ResolvesDevTeam;

class BulkUpdateIssuesTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesDevTeam;

    public function getName(): string
    {
        return 'dev.issues.bulk.PUT';
    }

    public function getDescription(): string
    {
        return 'PUT /dev/issues/bulk - Aktualisiert mehrere Issues. Zwei Modi: (1) issue_ids + data: gleiche Aenderung fuer alle, (2) issues: Array mit {id, ...fields} fuer individuelle Aenderungen.';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                'team_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Team-ID. Default: aktuelles Team aus Kontext.',
                ],
                'issue_ids' => [
                    'type' => 'array',
                    'items' => ['type' => 'integer'],
                    'description' => 'Modus 1: Array von Issue-IDs, die alle die gleichen Aenderungen bekommen.',
                ],
                'data' => [
                    'type' => 'object',
                    'description' => 'Modus 1: Aenderungen fuer alle issue_ids. Felder: title, description, priority, status, dev_board_slot_id, labels, user_in_charge_id, due_date, story_points, is_done.',
                ],
                'issues' => [
                    'type' => 'array',
                    'description' => 'Modus 2: Array mit individuellen Aenderungen pro Issue.',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'id' => ['type' => 'integer', 'description' => 'Issue-ID (ERFORDERLICH).'],
                        ],
                        'required' => ['id'],
                    ],
                ],
            ],
        ]);
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $resolved = $this->resolveTeam($arguments, $context);
            if ($resolved['error']) {
                return $resolved['error'];
            }
            $teamId = (int) $resolved['team_id'];

            $service = new DevIssueService();
            $allowedFields = ['title', 'description', 'priority', 'status', 'dev_board_slot_id', 'labels', 'user_in_charge_id', 'due_date', 'story_points', 'is_done'];
            $updated = [];

            // Modus 1: issue_ids + data
            if (!empty($arguments['issue_ids']) && !empty($arguments['data'])) {
                $ids = $arguments['issue_ids'];
                $data = array_intersect_key($arguments['data'], array_flip($allowedFields));

                if (empty($data)) {
                    return ToolResult::error('VALIDATION_ERROR', 'Keine gueltigen Felder in data.');
                }

                // Handle status → is_done/done_at
                $data = $this->handleStatusFields($data);

                $issues = DevIssue::where('team_id', $teamId)->whereIn('id', $ids)->get();
                foreach ($issues as $issue) {
                    $service->updateIssue($issue, $data);
                    $updated[] = $issue->id;
                }
            }
            // Modus 2: issues array
            elseif (!empty($arguments['issues'])) {
                $ids = array_column($arguments['issues'], 'id');
                $issues = DevIssue::where('team_id', $teamId)->whereIn('id', $ids)->get()->keyBy('id');

                foreach ($arguments['issues'] as $issueUpdate) {
                    $id = $issueUpdate['id'] ?? null;
                    if (!$id || !$issues->has($id)) {
                        continue;
                    }

                    $data = array_intersect_key($issueUpdate, array_flip($allowedFields));
                    if (empty($data)) {
                        continue;
                    }

                    $data = $this->handleStatusFields($data);
                    $service->updateIssue($issues[$id], $data);
                    $updated[] = $id;
                }
            } else {
                return ToolResult::error('VALIDATION_ERROR', 'Entweder issue_ids+data oder issues Array angeben.');
            }

            return ToolResult::success([
                'updated_count' => count($updated),
                'updated_ids' => $updated,
                'message' => count($updated) . ' Issues aktualisiert.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Bulk-Update: ' . $e->getMessage());
        }
    }

    private function handleStatusFields(array $data): array
    {
        if (isset($data['status'])) {
            if ($data['status'] === 'closed') {
                $data['is_done'] = true;
                $data['done_at'] = now();
            } elseif ($data['status'] === 'open') {
                $data['is_done'] = false;
                $data['done_at'] = null;
            }
        } elseif (isset($data['is_done'])) {
            $data['done_at'] = $data['is_done'] ? now() : null;
        }

        if (array_key_exists('due_date', $data)) {
            $data['due_date'] = $data['due_date'] === '' ? null : $data['due_date'];
        }
        if (array_key_exists('story_points', $data)) {
            $data['story_points'] = $data['story_points'] === '' ? null : $data['story_points'];
        }

        return $data;
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => false,
            'category' => 'action',
            'tags' => ['dev', 'issues', 'bulk', 'update'],
            'risk_level' => 'write',
            'requires_auth' => true,
            'requires_team' => true,
            'idempotent' => true,
        ];
    }
}
