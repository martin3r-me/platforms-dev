<?php

namespace Platform\Dev\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Dev\Models\DevIssue;
use Platform\Dev\Tools\Concerns\ResolvesDevTeam;

class GetIssueTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesDevTeam;

    public function getName(): string
    {
        return 'dev.issue.GET';
    }

    public function getDescription(): string
    {
        return 'GET /dev/issues/{id} - Zeigt Details eines Issues. Parameter: issue_id (required).';
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

            $issue->load(['board', 'slot', 'userInCharge', 'createdBy']);

            return ToolResult::success([
                'issue' => [
                    'id' => $issue->id,
                    'uuid' => $issue->uuid,
                    'title' => $issue->title,
                    'description' => $issue->description,
                    'priority' => $issue->priority instanceof \BackedEnum ? $issue->priority->value : $issue->priority,
                    'status' => $issue->status,
                    'dev_board_id' => $issue->dev_board_id,
                    'dev_board_slot_id' => $issue->dev_board_slot_id,
                    'board' => $issue->board ? [
                        'id' => $issue->board->id,
                        'name' => $issue->board->name,
                        'type' => $issue->board->type,
                    ] : null,
                    'slot' => $issue->slot ? [
                        'id' => $issue->slot->id,
                        'name' => $issue->slot->name,
                    ] : null,
                    'user_in_charge' => $issue->userInCharge ? [
                        'id' => $issue->userInCharge->id,
                        'name' => $issue->userInCharge->name,
                    ] : null,
                    'created_by' => $issue->createdBy ? [
                        'id' => $issue->createdBy->id,
                        'name' => $issue->createdBy->name,
                    ] : null,
                    'labels' => $issue->labels,
                    'story_points' => $issue->story_points?->value,
                    'story_points_label' => $issue->story_points?->label(),
                    'story_points_numeric' => $issue->story_points?->points(),
                    'acceptance_criteria' => $issue->acceptance_criteria,
                    'order' => $issue->order,
                    'slot_order' => $issue->slot_order,
                    'is_done' => $issue->is_done,
                    'done_at' => $issue->done_at?->toISOString(),
                    'due_date' => $issue->due_date?->toDateString(),
                    'created_at' => $issue->created_at?->toISOString(),
                    'updated_at' => $issue->updated_at?->toISOString(),
                ],
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden des Issues: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'read',
            'tags' => ['dev', 'issues', 'get'],
            'read_only' => true,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'safe',
            'idempotent' => true,
        ];
    }
}
