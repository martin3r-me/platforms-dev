<?php

namespace Platform\Dev\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Dev\Models\DevBoard;
use Platform\Dev\Tools\Concerns\ResolvesDevTeam;

class GetBoardTool implements ToolContract, ToolMetadataContract
{
    use ResolvesDevTeam;

    public function getName(): string
    {
        return 'dev.board.GET';
    }

    public function getDescription(): string
    {
        return 'GET /dev/board - Zeigt Board-Details mit Slots und Issues. ERFORDERLICH: board_id.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
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
        ];
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
                ->with(['slots.issues'])
                ->withCount(['slots', 'issues'])
                ->find($boardId);

            if (!$board) {
                return ToolResult::error('NOT_FOUND', 'Board nicht gefunden (oder kein Zugriff).');
            }

            $slots = $board->slots->map(function ($slot) {
                return [
                    'id' => $slot->id,
                    'uuid' => $slot->uuid,
                    'name' => $slot->name,
                    'description' => $slot->description,
                    'order' => $slot->order,
                    'issues' => $slot->issues->map(function ($issue) {
                        return [
                            'id' => $issue->id,
                            'uuid' => $issue->uuid,
                            'title' => $issue->title ?? $issue->name ?? null,
                            'status' => $issue->status instanceof \BackedEnum ? $issue->status->value : $issue->status,
                            'priority' => $issue->priority instanceof \BackedEnum ? $issue->priority->value : $issue->priority,
                            'slot_order' => $issue->slot_order,
                            'updated_at' => $issue->updated_at?->toISOString(),
                        ];
                    })->values()->toArray(),
                ];
            })->values()->toArray();

            return ToolResult::success([
                'id' => $board->id,
                'uuid' => $board->uuid,
                'name' => $board->name,
                'type' => $board->type instanceof \BackedEnum ? $board->type->value : $board->type,
                'description' => $board->description,
                'order' => $board->order,
                'slots_count' => $board->slots_count,
                'issues_count' => $board->issues_count,
                'slots' => $slots,
                'dev_package_id' => $board->dev_package_id,
                'team_id' => $board->team_id,
                'created_at' => $board->created_at?->toISOString(),
                'updated_at' => $board->updated_at?->toISOString(),
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden des Boards: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => true,
            'category' => 'read',
            'tags' => ['dev', 'board', 'get'],
            'risk_level' => 'safe',
            'requires_auth' => true,
            'requires_team' => true,
            'idempotent' => true,
        ];
    }
}
