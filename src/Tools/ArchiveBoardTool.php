<?php

namespace Platform\Dev\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Dev\Models\DevBoard;
use Platform\Dev\Services\DevBoardService;
use Platform\Dev\Tools\Concerns\ResolvesDevTeam;

class ArchiveBoardTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesDevTeam;

    public function getName(): string
    {
        return 'dev.boards.archive';
    }

    public function getDescription(): string
    {
        return 'POST /dev/boards/archive - Archiviert ein Board oder reaktiviert es. ERFORDERLICH: board_id. Optional: reactivate (boolean, default: false).';
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
                'reactivate' => [
                    'type' => 'boolean',
                    'description' => 'Optional: true = reaktivieren statt archivieren. Default: false.',
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
                ->find($boardId);

            if (!$board) {
                return ToolResult::error('NOT_FOUND', 'Board nicht gefunden (oder kein Zugriff).');
            }

            $boardService = new DevBoardService();
            $reactivate = (bool) ($arguments['reactivate'] ?? false);

            if ($reactivate) {
                $board = $boardService->reactivateBoard($board);
                $action = 'reaktiviert';
            } else {
                $board = $boardService->archiveBoard($board);
                $action = 'archiviert';
            }

            return ToolResult::success([
                'id' => $board->id,
                'uuid' => $board->uuid,
                'name' => $board->name,
                'type' => $board->type instanceof \BackedEnum ? $board->type->value : $board->type,
                'status' => $board->status,
                'dev_package_id' => $board->dev_package_id,
                'team_id' => $board->team_id,
                'message' => "Board '{$board->name}' erfolgreich {$action}.",
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Archivieren/Reaktivieren des Boards: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => false,
            'category' => 'action',
            'tags' => ['dev', 'boards', 'archive'],
            'risk_level' => 'write',
            'requires_auth' => true,
            'requires_team' => true,
            'idempotent' => true,
        ];
    }
}
