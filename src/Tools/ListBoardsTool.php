<?php

namespace Platform\Dev\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardGetOperations;
use Platform\Dev\Models\DevBoard;
use Platform\Dev\Models\DevPackage;
use Platform\Dev\Tools\Concerns\ResolvesDevTeam;

class ListBoardsTool implements ToolContract, ToolMetadataContract
{
    use HasStandardGetOperations;
    use ResolvesDevTeam;

    public function getName(): string
    {
        return 'dev.boards.GET';
    }

    public function getDescription(): string
    {
        return 'GET /dev/boards - Listet Boards eines Packages. ERFORDERLICH: package_id. Optional: type (feature, bug, custom), search/sort/limit/offset.';
    }

    public function getSchema(): array
    {
        return $this->mergeSchemas(
            $this->getStandardGetSchema(),
            [
                'properties' => [
                    'team_id' => [
                        'type' => 'integer',
                        'description' => 'Optional: Team-ID. Default: aktuelles Team aus Kontext.',
                    ],
                    'package_id' => [
                        'type' => 'integer',
                        'description' => 'ID des Packages (ERFORDERLICH).',
                    ],
                    'type' => [
                        'type' => 'string',
                        'enum' => ['feature', 'bug', 'custom'],
                        'description' => 'Optional: Filter nach Board-Typ (feature, bug, custom).',
                    ],
                ],
                'required' => ['package_id'],
            ]
        );
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $arguments = array_merge([
                'query' => null,
                'search' => null,
                'filters' => [],
                'sort' => null,
                'limit' => null,
                'offset' => null,
            ], $arguments);

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

            $query = DevBoard::query()
                ->withCount(['slots', 'issues'])
                ->where('dev_package_id', $packageId)
                ->where('team_id', $teamId);

            if (isset($arguments['type'])) {
                $query->where('type', $arguments['type']);
            }

            $this->applyStandardFilters($query, $arguments, [
                'name', 'type', 'created_at', 'updated_at',
            ]);
            $this->applyStandardSearch($query, $arguments, ['name', 'description']);
            $this->applyStandardSort($query, $arguments, [
                'name', 'type', 'order', 'created_at', 'updated_at',
            ], 'order', 'asc');

            $result = $this->applyStandardPaginationResult($query, $arguments);

            $data = collect($result['data'])->map(function (DevBoard $board) {
                return [
                    'id' => $board->id,
                    'uuid' => $board->uuid,
                    'name' => $board->name,
                    'type' => $board->type instanceof \BackedEnum ? $board->type->value : $board->type,
                    'description' => $board->description,
                    'order' => $board->order,
                    'slots_count' => $board->slots_count,
                    'issues_count' => $board->issues_count,
                    'dev_package_id' => $board->dev_package_id,
                    'team_id' => $board->team_id,
                    'created_at' => $board->created_at?->toISOString(),
                    'updated_at' => $board->updated_at?->toISOString(),
                ];
            })->values()->toArray();

            return ToolResult::success([
                'data' => $data,
                'pagination' => $result['pagination'] ?? null,
                'team_id' => $teamId,
                'package_id' => $packageId,
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden der Boards: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => true,
            'category' => 'read',
            'tags' => ['dev', 'boards', 'list'],
            'risk_level' => 'safe',
            'requires_auth' => true,
            'requires_team' => true,
            'idempotent' => true,
        ];
    }
}
