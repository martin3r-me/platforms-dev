<?php

namespace Platform\Dev\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Dev\Models\DevPackage;
use Platform\Dev\Services\DevBoardService;
use Platform\Dev\Tools\Concerns\ResolvesDevTeam;

class CreateBoardTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesDevTeam;

    public function getName(): string
    {
        return 'dev.boards.POST';
    }

    public function getDescription(): string
    {
        return 'POST /dev/boards - Erstellt ein neues Board. ERFORDERLICH: package_id, name. Optional: type (default: custom), description.';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                'team_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Team-ID. Default: aktuelles Team aus Kontext.',
                ],
                'package_id' => [
                    'type' => 'integer',
                    'description' => 'ID des Packages (ERFORDERLICH).',
                ],
                'name' => [
                    'type' => 'string',
                    'description' => 'Name des Boards (ERFORDERLICH).',
                ],
                'type' => [
                    'type' => 'string',
                    'enum' => ['feature', 'bug', 'custom'],
                    'description' => 'Optional: Board-Typ (feature, bug, custom). Default: custom.',
                ],
                'description' => [
                    'type' => 'string',
                    'description' => 'Optional: Beschreibung des Boards.',
                ],
            ],
            'required' => ['package_id', 'name'],
        ]);
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            if (!$context->user) {
                return ToolResult::error('AUTH_ERROR', 'Kein User im Kontext gefunden.');
            }

            $resolved = $this->resolveTeam($arguments, $context);
            if ($resolved['error']) {
                return $resolved['error'];
            }
            $teamId = (int) $resolved['team_id'];

            $packageId = (int) ($arguments['package_id'] ?? 0);
            if ($packageId <= 0) {
                return ToolResult::error('VALIDATION_ERROR', 'package_id ist erforderlich.');
            }

            $package = DevPackage::query()
                ->where('team_id', $teamId)
                ->find($packageId);

            if (!$package) {
                return ToolResult::error('NOT_FOUND', 'Package nicht gefunden (oder kein Zugriff).');
            }

            $name = trim((string) ($arguments['name'] ?? ''));
            if ($name === '') {
                return ToolResult::error('VALIDATION_ERROR', 'name ist erforderlich.');
            }

            $boardService = new DevBoardService();
            $board = $boardService->createBoard([
                'name' => $name,
                'type' => $arguments['type'] ?? 'custom',
                'description' => $arguments['description'] ?? null,
                'dev_package_id' => $packageId,
                'team_id' => $teamId,
                'created_by_user_id' => $context->user->id,
            ]);

            $board->loadCount(['slots', 'issues']);

            return ToolResult::success([
                'id' => $board->id,
                'uuid' => $board->uuid,
                'name' => $board->name,
                'type' => $board->type instanceof \BackedEnum ? $board->type->value : $board->type,
                'description' => $board->description,
                'slots_count' => $board->slots_count,
                'issues_count' => $board->issues_count,
                'dev_package_id' => $board->dev_package_id,
                'team_id' => $board->team_id,
                'message' => "Board '{$board->name}' erfolgreich erstellt.",
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Erstellen des Boards: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => false,
            'category' => 'action',
            'tags' => ['dev', 'boards', 'create'],
            'risk_level' => 'write',
            'requires_auth' => true,
            'requires_team' => true,
            'idempotent' => false,
        ];
    }
}
