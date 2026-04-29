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
        return 'PUT /dev/issues/{id} - Aktualisiert ein Issue. Parameter: issue_id (required). Optional: title, description, priority, status, dev_board_slot_id, labels, user_in_charge_id, due_date, is_done.';
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
                    'description' => 'Optional: Story Points (T-Shirt Size). xs=1, s=2, m=3, l=5, xl=8, xxl=13. Leerer String entfernt.',
                ],
                'acceptance_criteria' => [
                    'type' => 'array',
                    'description' => 'Optional: Definition of Done Items (ersetzt komplett).',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'text' => ['type' => 'string', 'description' => 'Kriterium-Text.'],
                            'done' => ['type' => 'boolean', 'description' => 'Erledigt? Default: false.'],
                        ],
                        'required' => ['text'],
                    ],
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

            if (array_key_exists('due_date', $arguments)) {
                $payload['due_date'] = $arguments['due_date'] === '' ? null : $arguments['due_date'];
            }

            if (array_key_exists('story_points', $arguments)) {
                $payload['story_points'] = $arguments['story_points'] === '' ? null : $arguments['story_points'];
            }

            if (array_key_exists('acceptance_criteria', $arguments)) {
                $payload['acceptance_criteria'] = array_map(fn ($c) => [
                    'text' => $c['text'] ?? '',
                    'done' => $c['done'] ?? false,
                ], $arguments['acceptance_criteria']);
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
