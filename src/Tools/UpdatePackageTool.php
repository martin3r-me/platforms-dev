<?php

namespace Platform\Dev\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Dev\Models\DevPackage;
use Platform\Dev\Tools\Concerns\ResolvesDevTeam;

class UpdatePackageTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesDevTeam;

    public function getName(): string
    {
        return 'dev.packages.PUT';
    }

    public function getDescription(): string
    {
        return 'PUT /dev/packages - Aktualisiert ein Package. ERFORDERLICH: package_id. Optional: name, description, icon, github_repo_full_name.';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                'team_id' => ['type' => 'integer', 'description' => 'Optional: Team-ID.'],
                'package_id' => ['type' => 'integer', 'description' => 'ID des Packages (ERFORDERLICH).'],
                'name' => ['type' => 'string', 'description' => 'Optional: Neuer Name.'],
                'description' => ['type' => 'string', 'description' => 'Optional: Neue Beschreibung.'],
                'icon' => ['type' => 'string', 'description' => 'Optional: Neues Icon.'],
                'github_repo_full_name' => ['type' => 'string', 'description' => 'Optional: GitHub Repo.'],
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

            $payload = [];
            foreach (['name', 'description', 'icon', 'github_repo_full_name'] as $field) {
                if (isset($arguments[$field])) {
                    $payload[$field] = $arguments[$field];
                }
            }

            if (empty($payload)) {
                return ToolResult::error('NO_CHANGE', 'Keine Aenderungen angegeben.');
            }

            $package->update($payload);

            return ToolResult::success([
                'id' => $package->id,
                'name' => $package->name,
                'message' => "Package '{$package->name}' erfolgreich aktualisiert.",
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Aktualisieren: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['dev', 'packages', 'update'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'write',
            'idempotent' => true,
        ];
    }
}
