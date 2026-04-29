<?php

namespace Platform\Dev\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Dev\Models\DevBoard;
use Platform\Dev\Services\DevIssueService;
use Platform\Dev\Tools\Concerns\ResolvesDevTeam;

class CreateIssueTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesDevTeam;

    public function getName(): string
    {
        return 'dev.issues.POST';
    }

    public function getDescription(): string
    {
        return 'POST /dev/issues - Erstellt ein neues Issue. Parameter: board_id (required), title (required). Optional: description, priority, dev_board_slot_id, labels, user_in_charge_id, due_date.';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                'team_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Team-ID. Default: aktuelles Team aus Kontext.',
                ],
                'board_id' => [
                    'type' => 'integer',
                    'description' => 'ID des Boards (ERFORDERLICH).',
                ],
                'title' => [
                    'type' => 'string',
                    'description' => 'Titel des Issues (ERFORDERLICH).',
                ],
                'description' => [
                    'type' => 'string',
                    'description' => 'Optional: Beschreibung des Issues.',
                ],
                'priority' => [
                    'type' => 'string',
                    'enum' => ['low', 'normal', 'high'],
                    'description' => 'Optional: Prioritaet. Default: normal.',
                ],
                'dev_board_slot_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Slot-ID (Spalte). Null = Backlog.',
                ],
                'labels' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                    'description' => 'Optional: Labels als Array von Strings.',
                ],
                'user_in_charge_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: ID des zustaendigen Users.',
                ],
                'due_date' => [
                    'type' => 'string',
                    'description' => 'Optional: Faelligkeitsdatum (YYYY-MM-DD).',
                ],
                'story_points' => [
                    'type' => 'string',
                    'enum' => ['xs', 's', 'm', 'l', 'xl', 'xxl'],
                    'description' => 'Optional: Story Points (T-Shirt Size). xs=1, s=2, m=3, l=5, xl=8, xxl=13.',
                ],
                'acceptance_criteria' => [
                    'type' => 'array',
                    'description' => 'Optional: Definition of Done Items.',
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
            'required' => ['board_id', 'title'],
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

            $board = DevBoard::find((int) $arguments['board_id']);
            if (!$board) {
                return ToolResult::error('NOT_FOUND', 'Board nicht gefunden.');
            }

            if ((int) $board->team_id !== $teamId) {
                return ToolResult::error('ACCESS_DENIED', 'Kein Zugriff auf dieses Board.');
            }

            $data = [
                'team_id' => $teamId,
                'created_by_user_id' => $context->user?->id,
                'dev_board_id' => $board->id,
                'title' => $arguments['title'],
                'status' => 'open',
            ];

            if (array_key_exists('description', $arguments)) {
                $data['description'] = $arguments['description'];
            }
            if (array_key_exists('priority', $arguments)) {
                $data['priority'] = $arguments['priority'];
            }
            if (array_key_exists('dev_board_slot_id', $arguments)) {
                $data['dev_board_slot_id'] = $arguments['dev_board_slot_id'];
            }
            if (array_key_exists('labels', $arguments)) {
                $data['labels'] = $arguments['labels'];
            }
            if (array_key_exists('user_in_charge_id', $arguments)) {
                $data['user_in_charge_id'] = $arguments['user_in_charge_id'];
            }
            if (array_key_exists('due_date', $arguments)) {
                $data['due_date'] = $arguments['due_date'];
            }
            if (array_key_exists('story_points', $arguments)) {
                $data['story_points'] = $arguments['story_points'];
            }
            if (array_key_exists('acceptance_criteria', $arguments)) {
                $data['acceptance_criteria'] = array_map(fn ($c) => [
                    'text' => $c['text'] ?? '',
                    'done' => $c['done'] ?? false,
                ], $arguments['acceptance_criteria']);
            }

            $service = new DevIssueService();
            $issue = $service->createIssue($data);

            return ToolResult::success([
                'issue' => [
                    'id' => $issue->id,
                    'uuid' => $issue->uuid,
                    'title' => $issue->title,
                    'priority' => $issue->priority instanceof \BackedEnum ? $issue->priority->value : $issue->priority,
                    'status' => $issue->status,
                    'dev_board_id' => $issue->dev_board_id,
                    'dev_board_slot_id' => $issue->dev_board_slot_id,
                ],
                'message' => 'Issue erfolgreich erstellt.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Erstellen des Issues: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => false,
            'category' => 'action',
            'tags' => ['dev', 'issues', 'create'],
            'risk_level' => 'write',
            'requires_auth' => true,
            'requires_team' => true,
            'idempotent' => false,
        ];
    }
}
