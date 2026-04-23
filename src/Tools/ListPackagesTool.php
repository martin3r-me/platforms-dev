<?php

namespace Platform\Dev\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardGetOperations;
use Platform\Dev\Models\DevPackage;
use Platform\Dev\Tools\Concerns\ResolvesDevTeam;

class ListPackagesTool implements ToolContract, ToolMetadataContract
{
    use HasStandardGetOperations;
    use ResolvesDevTeam;

    public function getName(): string
    {
        return 'dev.packages.GET';
    }

    public function getDescription(): string
    {
        return 'GET /dev/packages - Listet alle Packages des Teams. Optional: status, search, sort, limit, offset.';
    }

    public function getSchema(): array
    {
        return $this->mergeSchemas($this->getStandardGetSchema(), [
            'properties' => [
                'team_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Team-ID. Default: aktuelles Team.',
                ],
                'status' => [
                    'type' => 'string',
                    'enum' => ['active', 'archived'],
                    'description' => 'Optional: Nach Status filtern.',
                ],
            ],
        ]);
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

            $query = DevPackage::where('team_id', $teamId);

            if (!empty($arguments['status'])) {
                $query->where('status', $arguments['status']);
            }

            $this->applyStandardSearch($query, $arguments, ['name', 'description', 'github_repo_full_name']);
            $this->applyStandardSort($query, $arguments, ['name', 'status', 'order', 'created_at', 'updated_at'], 'order', 'asc');

            $query->withCount(['boards', 'discussions']);
            $result = $this->applyStandardPaginationResult($query, $arguments);

            $packages = $result['data']->map(fn ($p) => [
                'id' => $p->id,
                'uuid' => $p->uuid,
                'name' => $p->name,
                'description' => $p->description,
                'github_repo_full_name' => $p->github_repo_full_name,
                'status' => $p->status,
                'icon' => $p->icon,
                'boards_count' => $p->boards_count,
                'discussions_count' => $p->discussions_count,
                'created_at' => $p->created_at?->toISOString(),
            ])->toArray();

            return ToolResult::success([
                'packages' => $packages,
                'pagination' => $result['pagination'],
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden der Packages: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'read',
            'tags' => ['dev', 'packages', 'list'],
            'read_only' => true,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'safe',
            'idempotent' => true,
        ];
    }
}
