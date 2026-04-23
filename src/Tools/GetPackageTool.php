<?php

namespace Platform\Dev\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Dev\Models\DevPackage;
use Platform\Dev\Tools\Concerns\ResolvesDevTeam;

class GetPackageTool implements ToolContract, ToolMetadataContract
{
    use ResolvesDevTeam;

    public function getName(): string
    {
        return 'dev.package.GET';
    }

    public function getDescription(): string
    {
        return 'GET /dev/package - Zeigt Details eines Packages mit Boards und Stats. ERFORDERLICH: package_id.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'team_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Team-ID.',
                ],
                'package_id' => [
                    'type' => 'integer',
                    'description' => 'ID des Packages (ERFORDERLICH).',
                ],
            ],
            'required' => ['package_id'],
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

            $packageId = $arguments['package_id'] ?? null;
            if (!$packageId) {
                return ToolResult::error('VALIDATION_ERROR', 'package_id ist erforderlich.');
            }

            $package = DevPackage::where('team_id', $teamId)
                ->with(['boards.slots', 'boards' => fn ($q) => $q->withCount('issues'), 'userInCharge', 'lockedByUser'])
                ->find($packageId);

            if (!$package) {
                return ToolResult::error('NOT_FOUND', 'Package nicht gefunden.');
            }

            $openIssues = 0;
            $closedIssues = 0;
            foreach ($package->boards as $board) {
                $openIssues += $board->issues()->where('status', 'open')->count();
                $closedIssues += $board->issues()->where('status', 'closed')->count();
            }

            return ToolResult::success([
                'package' => [
                    'id' => $package->id,
                    'uuid' => $package->uuid,
                    'name' => $package->name,
                    'description' => $package->description,
                    'github_repo_full_name' => $package->github_repo_full_name,
                    'github_repo_id' => $package->github_repo_id,
                    'status' => $package->status,
                    'icon' => $package->icon,
                    'user_in_charge' => $package->userInCharge ? [
                        'id' => $package->userInCharge->id,
                        'name' => $package->userInCharge->name,
                    ] : null,
                    'lock' => $package->isLocked() ? [
                        'locked_by' => [
                            'id' => $package->lockedByUser?->id,
                            'name' => $package->lockedByUser?->name,
                        ],
                        'locked_at' => $package->locked_at?->toISOString(),
                        'reason' => $package->lock_reason,
                    ] : null,
                    'created_at' => $package->created_at?->toISOString(),
                    'updated_at' => $package->updated_at?->toISOString(),
                ],
                'boards' => $package->boards->map(fn ($b) => [
                    'id' => $b->id,
                    'name' => $b->name,
                    'type' => $b->type instanceof \BackedEnum ? $b->type->value : $b->type,
                    'issues_count' => $b->issues_count,
                    'slots' => $b->slots->map(fn ($s) => [
                        'id' => $s->id,
                        'name' => $s->name,
                        'order' => $s->order,
                    ])->toArray(),
                ])->toArray(),
                'stats' => [
                    'open_issues' => $openIssues,
                    'closed_issues' => $closedIssues,
                    'discussions' => $package->discussions()->count(),
                ],
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden des Packages: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'read',
            'tags' => ['dev', 'packages', 'get'],
            'read_only' => true,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'safe',
            'idempotent' => true,
        ];
    }
}
