<?php

namespace Platform\Dev\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Dev\Services\DevPackageService;
use Platform\Dev\Tools\Concerns\ResolvesDevTeam;

class ActivatePackageTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesDevTeam;

    public function getName(): string
    {
        return 'dev.packages.activate.POST';
    }

    public function getDescription(): string
    {
        return 'POST /dev/packages/activate - Aktiviert ein neues Package (erstellt es mit Default-Boards). ERFORDERLICH: name. Optional: description, github_repo_id (FK zu integration_github_repos), icon. Der full_name wird automatisch aus dem Repo geladen.';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                'team_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Team-ID.',
                ],
                'name' => [
                    'type' => 'string',
                    'description' => 'Name des Packages (ERFORDERLICH).',
                ],
                'description' => [
                    'type' => 'string',
                    'description' => 'Optional: Beschreibung.',
                ],
                'github_repo_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: FK zu integration_github_repos. Der full_name wird automatisch aufgeloest.',
                ],
                'icon' => [
                    'type' => 'string',
                    'description' => 'Optional: Heroicon key.',
                ],
            ],
            'required' => ['name'],
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

            $name = trim((string) ($arguments['name'] ?? ''));
            if ($name === '') {
                return ToolResult::error('VALIDATION_ERROR', 'name ist erforderlich.');
            }

            // Auto-resolve full_name from repo FK
            $githubRepoId = $arguments['github_repo_id'] ?? null;
            $githubRepoFullName = null;
            if ($githubRepoId) {
                try {
                    $repo = \Platform\Integrations\Models\IntegrationGithubRepo::find($githubRepoId);
                    if ($repo) {
                        $githubRepoFullName = $repo->full_name;
                    }
                } catch (\Throwable $e) {
                    // Integrations module not loaded
                }
            }

            $service = new DevPackageService();
            $package = $service->activate([
                'name' => $name,
                'description' => $arguments['description'] ?? null,
                'github_repo_full_name' => $githubRepoFullName,
                'github_repo_id' => $githubRepoId,
                'icon' => $arguments['icon'] ?? null,
                'team_id' => $teamId,
                'created_by_user_id' => $context->user->id,
            ]);

            $package->load('boards.slots');

            return ToolResult::success([
                'id' => $package->id,
                'uuid' => $package->uuid,
                'name' => $package->name,
                'status' => $package->status,
                'boards' => $package->boards->map(fn ($b) => [
                    'id' => $b->id,
                    'name' => $b->name,
                    'type' => $b->type instanceof \BackedEnum ? $b->type->value : $b->type,
                    'slots' => $b->slots->pluck('name')->toArray(),
                ])->toArray(),
                'message' => "Package '{$package->name}' erfolgreich aktiviert mit " . $package->boards->count() . " Boards.",
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Aktivieren des Packages: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['dev', 'packages', 'activate', 'create'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'write',
            'idempotent' => false,
        ];
    }
}
