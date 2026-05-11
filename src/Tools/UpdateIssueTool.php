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

class UpdateIssueTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesDevTeam;

    public function getName(): string
    {
        return 'dev.issues.PUT';
    }

    public function getDescription(): string
    {
        return 'PUT /dev/issues/{id} - Aktualisiert ein Issue. Parameter: issue_id (required). Optional: title, description, priority, status, dev_board_slot_id, labels, user_in_charge_id, due_date, is_done, story_points, dod_items, dod_items_update.';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                'team_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Team-ID. Default: aktuelles Team aus Kontext.',
                ],
                'issue_id' => [
                    'type' => 'integer',
                    'description' => 'ID des Issues (ERFORDERLICH).',
                ],
                'title' => [
                    'type' => 'string',
                    'description' => 'Optional: Neuer Titel.',
                ],
                'description' => [
                    'type' => 'string',
                    'description' => 'Optional: Neue Beschreibung.',
                ],
                'priority' => [
                    'type' => 'string',
                    'enum' => ['low', 'normal', 'high'],
                    'description' => 'Optional: Neue Prioritaet.',
                ],
                'status' => [
                    'type' => 'string',
                    'enum' => ['open', 'closed'],
                    'description' => 'Optional: Neuer Status. Bei "closed" wird is_done=true und done_at gesetzt. Bei "open" wird is_done=false und done_at entfernt.',
                ],
                'dev_board_slot_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Neuer Slot (Spalte). Null = Backlog.',
                ],
                'labels' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                    'description' => 'Optional: Neue Labels.',
                ],
                'user_in_charge_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Neuer zustaendiger User.',
                ],
                'due_date' => [
                    'type' => 'string',
                    'description' => 'Optional: Neues Faelligkeitsdatum (YYYY-MM-DD). Leerer String entfernt das Datum.',
                ],
                'is_done' => [
                    'type' => 'boolean',
                    'description' => 'Optional: Erledigt-Status.',
                ],
                'story_points' => [
                    'type' => 'string',
                    'enum' => ['xs', 's', 'm', 'l', 'xl', 'xxl'],
                    'description' => 'Optional: Story Points (T-Shirt Sizes: xs=1, s=2, m=3, l=5, xl=8, xxl=13). Leerer String entfernt.',
                ],
                'dod_items' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'text' => ['type' => 'string', 'description' => 'Text des DOD-Kriteriums.'],
                            'checked' => ['type' => 'boolean', 'description' => 'Erledigt-Status. Default: false.'],
                        ],
                        'required' => ['text'],
                    ],
                    'description' => 'Optional: DOD-Kriterien komplett überschreiben. Array von {text, checked?}.',
                ],
                'dod_items_update' => [
                    'type' => 'object',
                    'properties' => [
                        'toggle' => [
                            'type' => 'array',
                            'items' => ['type' => 'integer'],
                            'description' => 'Indices der Items, deren checked-Status getoggelt wird.',
                        ],
                        'set_checked' => [
                            'type' => 'array',
                            'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'index' => ['type' => 'integer'],
                                    'checked' => ['type' => 'boolean'],
                                ],
                                'required' => ['index', 'checked'],
                            ],
                            'description' => 'Checked-Status explizit setzen.',
                        ],
                        'add' => [
                            'type' => 'array',
                            'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'text' => ['type' => 'string'],
                                    'checked' => ['type' => 'boolean'],
                                ],
                                'required' => ['text'],
                            ],
                            'description' => 'Neue Items hinzufügen.',
                        ],
                        'remove' => [
                            'type' => 'array',
                            'items' => ['type' => 'integer'],
                            'description' => 'Items an diesen Indices entfernen.',
                        ],
                        'update_text' => [
                            'type' => 'array',
                            'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'index' => ['type' => 'integer'],
                                    'text' => ['type' => 'string'],
                                ],
                                'required' => ['index', 'text'],
                            ],
                            'description' => 'Text von Items aktualisieren.',
                        ],
                    ],
                    'description' => 'Optional: Granulare DOD-Updates (toggle, set_checked, add, remove, update_text). Hat Priorität über dod_items.',
                ],
            ],
            'required' => ['issue_id'],
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

            $found = $this->validateAndFindModel($arguments, $context, 'issue_id', DevIssue::class, 'NOT_FOUND', 'Issue nicht gefunden.');
            if ($found['error']) {
                return $found['error'];
            }
            $issue = $found['model'];

            if ((int) $issue->team_id !== $teamId) {
                return ToolResult::error('ACCESS_DENIED', 'Kein Zugriff auf dieses Issue.');
            }

            $payload = [];

            $fields = ['title', 'description', 'priority', 'dev_board_slot_id', 'user_in_charge_id'];
            foreach ($fields as $field) {
                if (array_key_exists($field, $arguments)) {
                    $payload[$field] = $arguments[$field] === '' ? null : $arguments[$field];
                }
            }

            if (array_key_exists('labels', $arguments)) {
                $payload['labels'] = $arguments['labels'];
            }

            // Story Points
            if (array_key_exists('story_points', $arguments)) {
                $sp = $arguments['story_points'];
                $payload['story_points'] = ($sp === '' || $sp === null) ? null : strtolower($sp);
            }

            if (array_key_exists('due_date', $arguments)) {
                $payload['due_date'] = $arguments['due_date'] === '' ? null : $arguments['due_date'];
            }

            // Handle status changes with automatic is_done/done_at
            if (array_key_exists('status', $arguments)) {
                $payload['status'] = $arguments['status'];
                if ($arguments['status'] === 'closed') {
                    $payload['is_done'] = true;
                    $payload['done_at'] = now();
                } elseif ($arguments['status'] === 'open') {
                    $payload['is_done'] = false;
                    $payload['done_at'] = null;
                }
            } elseif (array_key_exists('is_done', $arguments)) {
                $payload['is_done'] = $arguments['is_done'];
                if ($arguments['is_done']) {
                    $payload['done_at'] = now();
                } else {
                    $payload['done_at'] = null;
                }
            }

            // DOD / Acceptance Criteria
            if (!empty($arguments['dod_items_update'])) {
                $criteria = $issue->acceptance_criteria ?? [];
                $ops = $arguments['dod_items_update'];

                // Toggle
                if (!empty($ops['toggle'])) {
                    foreach ($ops['toggle'] as $idx) {
                        if (isset($criteria[$idx])) {
                            $criteria[$idx]['checked'] = !($criteria[$idx]['checked'] ?? false);
                        }
                    }
                }

                // Set checked
                if (!empty($ops['set_checked'])) {
                    foreach ($ops['set_checked'] as $item) {
                        $idx = $item['index'];
                        if (isset($criteria[$idx])) {
                            $criteria[$idx]['checked'] = (bool) $item['checked'];
                        }
                    }
                }

                // Add
                if (!empty($ops['add'])) {
                    foreach ($ops['add'] as $item) {
                        $criteria[] = [
                            'text' => $item['text'],
                            'checked' => (bool) ($item['checked'] ?? false),
                        ];
                    }
                }

                // Remove (descending to preserve indices)
                if (!empty($ops['remove'])) {
                    $removeIndices = $ops['remove'];
                    rsort($removeIndices);
                    foreach ($removeIndices as $idx) {
                        array_splice($criteria, $idx, 1);
                    }
                }

                // Update text
                if (!empty($ops['update_text'])) {
                    foreach ($ops['update_text'] as $item) {
                        $idx = $item['index'];
                        if (isset($criteria[$idx])) {
                            $criteria[$idx]['text'] = $item['text'];
                        }
                    }
                }

                $payload['acceptance_criteria'] = array_values($criteria);
            } elseif (array_key_exists('dod_items', $arguments)) {
                $payload['acceptance_criteria'] = collect($arguments['dod_items'])->map(fn($item) => [
                    'text' => $item['text'],
                    'checked' => (bool) ($item['checked'] ?? false),
                ])->toArray();
            }

            $service = new DevIssueService();
            $issue = $service->updateIssue($issue, $payload);

            return ToolResult::success([
                'issue' => [
                    'id' => $issue->id,
                    'uuid' => $issue->uuid,
                    'title' => $issue->title,
                    'priority' => $issue->priority instanceof \BackedEnum ? $issue->priority->value : $issue->priority,
                    'status' => $issue->status,
                    'dev_board_id' => $issue->dev_board_id,
                    'dev_board_slot_id' => $issue->dev_board_slot_id,
                    'is_done' => $issue->is_done,
                    'story_points' => $issue->story_points?->value,
                    'story_points_label' => $issue->story_points?->label(),
                    'story_points_numeric' => $issue->story_points?->points(),
                    'acceptance_criteria' => $issue->acceptance_criteria,
                ],
                'message' => 'Issue erfolgreich aktualisiert.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Aktualisieren des Issues: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => false,
            'category' => 'action',
            'tags' => ['dev', 'issues', 'update'],
            'risk_level' => 'write',
            'requires_auth' => true,
            'requires_team' => true,
            'idempotent' => true,
        ];
    }
}
