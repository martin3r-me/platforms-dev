<?php

namespace Platform\Dev\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Dev\Models\DevPackage;
use Platform\Dev\Services\DevPackageService;
use Platform\Dev\Tools\Concerns\ResolvesDevTeam;

class DeactivatePackageTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesDevTeam;

    public function getName(): string
    {
        return 'dev.packages.deactivate.POST';
    }

    public function getDescription(): string
    {
        return 'POST /dev/packages/deactivate - Archiviert ein Package. ERFORDERLICH: package_id.';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                'team_id' => ['type' => 'integer', 'description' => 'Optional: Team-ID.'],
                'package_id' => ['type' => 'integer', 'description' => 'ID des Packages (ERFORDERLICH).'],
            ],
            'required' => ['package_id'],
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

            $package = DevPackage::where('team_id', $teamId)->find($arguments['package_id'] ?? null);
            if (!$package) {
                return ToolResult::error('NOT_FOUND', 'Package nicht gefunden.');
            }

            $service = new DevPackageService();
            $package = $service->deactivate($package);

            return ToolResult::success([
                'id' => $package->id,
                'name' => $package->name,
                'status' => $package->status,
                'message' => "Package '{$package->name}' erfolgreich archiviert.",
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Deaktivieren: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['dev', 'packages', 'deactivate', 'archive'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'write',
            'idempotent' => true,
        ];
    }
}
