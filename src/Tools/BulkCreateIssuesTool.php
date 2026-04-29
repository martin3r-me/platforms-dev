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

class BulkCreateIssuesTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesDevTeam;

    public function getName(): string
    {
        return 'dev.issues.bulk.POST';
    }

    public function getDescription(): string
    {
        return 'POST /dev/issues/bulk - Erstellt mehrere Issues auf einmal. Parameter: board_id (required), issues (required, Array mit title, optional: description, priority, labels, due_date).';
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
                'issues' => [
                    'type' => 'array',
                    'description' => 'ERFORDERLICH: Array von Issues.',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'title' => [
                                'type' => 'string',
                                'description' => 'Titel des Issues (ERFORDERLICH).',
                            ],
                            'description' => [
                                'type' => 'string',
                                'description' => 'Optional: Beschreibung.',
                            ],
                            'priority' => [
                                'type' => 'string',
                                'enum' => ['low', 'normal', 'high'],
                                'description' => 'Optional: Prioritaet. Default: normal.',
                            ],
                            'labels' => [
                                'type' => 'array',
                                'items' => ['type' => 'string'],
                                'description' => 'Optional: Labels.',
                            ],
                            'due_date' => [
                                'type' => 'string',
                                'description' => 'Optional: Faelligkeitsdatum (YYYY-MM-DD).',
                            ],
                            'story_points' => [
                                'type' => 'string',
                                'enum' => ['xs', 's', 'm', 'l', 'xl', 'xxl'],
                                'description' => 'Optional: Story Points.',
                            ],
                            'acceptance_criteria' => [
                                'type' => 'array',
                                'description' => 'Optional: DoD Items.',
                                'items' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'text' => ['type' => 'string'],
                                        'done' => ['type' => 'boolean'],
                                    ],
                                    'required' => ['text'],
                                ],
                            ],
                        ],
                        'required' => ['title'],
                    ],
                ],
            ],
            'required' => ['board_id', 'issues'],
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

            $issuesData = $arguments['issues'] ?? [];
            if (empty($issuesData)) {
                return ToolResult::error('VALIDATION_ERROR', 'Mindestens ein Issue muss angegeben werden.');
            }

            // Prepare issues data (filter to allowed fields)
            $allowedFields = ['title', 'description', 'priority', 'labels', 'due_date', 'story_points', 'acceptance_criteria'];
            $preparedIssues = [];
            foreach ($issuesData as $issueData) {
                if (empty($issueData['title'])) {
                    return ToolResult::error('VALIDATION_ERROR', 'Jedes Issue benoetigt einen Titel.');
                }
                $prepared = ['status' => 'open'];
                foreach ($allowedFields as $field) {
                    if (array_key_exists($field, $issueData)) {
                        $prepared[$field] = $issueData[$field];
                    }
                }
                $preparedIssues[] = $prepared;
            }

            $service = new DevIssueService();
            $created = $service->bulkCreate(
                $board->id,
                $teamId,
                $context->user?->id,
                $preparedIssues
            );

            $createdIds = array_map(fn ($issue) => [
                'id' => $issue->id,
                'uuid' => $issue->uuid,
                'title' => $issue->title,
            ], $created);

            return ToolResult::success([
                'created_count' => count($created),
                'issues' => $createdIds,
                'message' => count($created) . ' Issues erfolgreich erstellt.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Erstellen der Issues: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => false,
            'category' => 'action',
            'tags' => ['dev', 'issues', 'bulk', 'create'],
            'risk_level' => 'write',
            'requires_auth' => true,
            'requires_team' => true,
            'idempotent' => false,
        ];
    }
}
