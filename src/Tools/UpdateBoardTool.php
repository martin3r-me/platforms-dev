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

class UpdateBoardTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesDevTeam;

    public function getName(): string
    {
        return 'dev.boards.PUT';
    }

    public function getDescription(): string
    {
        return 'PUT /dev/boards - Aktualisiert ein Board. ERFORDERLICH: board_id. Optional: name, description.';
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
                'name' => [
                    'type' => 'string',
                    'description' => 'Optional: Neuer Name.',
                ],
                'description' => [
                    'type' => 'string',
                    'description' => 'Optional: Neue Beschreibung.',
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

            $payload = [];
            if (array_key_exists('name', $arguments) && $arguments['name'] !== null) {
                $name = trim((string) $arguments['name']);
                if ($name === '') {
                    return ToolResult::error('VALIDATION_ERROR', 'Name darf nicht leer sein.');
                }
                $payload['name'] = $name;
            }
            if (array_key_exists('description', $arguments)) {
                $payload['description'] = $arguments['description'];
            }

            if (empty($payload)) {
                return ToolResult::error('NO_CHANGE', 'Keine Aenderungen uebergeben.');
            }

            $boardService = new DevBoardService();
            $board = $boardService->updateBoard($board, $payload);

            return ToolResult::success([
                'id' => $board->id,
                'uuid' => $board->uuid,
                'name' => $board->name,
                'type' => $board->type instanceof \BackedEnum ? $board->type->value : $board->type,
                'description' => $board->description,
                'dev_package_id' => $board->dev_package_id,
                'team_id' => $board->team_id,
                'message' => "Board '{$board->name}' erfolgreich aktualisiert.",
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Aktualisieren des Boards: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => false,
            'category' => 'action',
            'tags' => ['dev', 'boards', 'update'],
            'risk_level' => 'write',
            'requires_auth' => true,
            'requires_team' => true,
            'idempotent' => true,
        ];
    }
}
