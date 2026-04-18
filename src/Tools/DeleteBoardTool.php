<?php

namespace Platform\Dev\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Dev\Models\DevBoard;
use Platform\Dev\Tools\Concerns\ResolvesDevTeam;

class DeleteBoardTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesDevTeam;

    public function getName(): string
    {
        return 'dev.boards.DELETE';
    }

    public function getDescription(): string
    {
        return 'DELETE /dev/boards - Loescht ein Board mit allen Slots und Issues. ERFORDERLICH: board_id.';
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
            ],
            'required' => ['board_id'],
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

            $boardId = (int) ($arguments['board_id'] ?? 0);
            if ($boardId <= 0) {
                return ToolResult::error('VALIDATION_ERROR', 'board_id ist erforderlich.');
            }

            $board = DevBoard::query()
                ->where('team_id', $teamId)
                ->withCount(['slots', 'issues'])
                ->find($boardId);

            if (!$board) {
                return ToolResult::error('NOT_FOUND', 'Board nicht gefunden (oder kein Zugriff).');
            }

            $name = $board->name;
            $slotsCount = $board->slots_count;
            $issuesCount = $board->issues_count;

            // Delete issues first, then slots, then board
            $board->issues()->delete();
            $board->slots()->delete();
            $board->delete();

            return ToolResult::success([
                'id' => $boardId,
                'deleted_slots_count' => $slotsCount,
                'deleted_issues_count' => $issuesCount,
                'message' => "Board '{$name}' mit {$slotsCount} Slots und {$issuesCount} Issues geloescht.",
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Loeschen des Boards: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => false,
            'category' => 'action',
            'tags' => ['dev', 'boards', 'delete'],
            'risk_level' => 'write',
            'requires_auth' => true,
            'requires_team' => true,
            'idempotent' => false,
        ];
    }
}
