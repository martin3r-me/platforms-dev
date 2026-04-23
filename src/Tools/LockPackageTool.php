<?php

namespace Platform\Dev\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Dev\Models\DevPackage;
use Platform\Dev\Tools\Concerns\ResolvesDevTeam;

class LockPackageTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesDevTeam;

    public function getName(): string
    {
        return 'dev.packages.lock.POST';
    }

    public function getDescription(): string
    {
        return 'POST /dev/packages/lock - Sperrt ein Package ("Ich arbeite daran"). ERFORDERLICH: package_id. Optional: reason.';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                'team_id' => ['type' => 'integer', 'description' => 'Optional: Team-ID.'],
                'package_id' => ['type' => 'integer', 'description' => 'ID des Packages (ERFORDERLICH).'],
                'reason' => ['type' => 'string', 'description' => 'Optional: Grund der Sperre.'],
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

            if ($package->isLocked()) {
                return ToolResult::error('ALREADY_LOCKED', "Package ist bereits gesperrt von User #{$package->locked_by_user_id}.");
            }

            $package->update([
                'locked_by_user_id' => $context->user->id,
                'locked_at' => now(),
                'lock_reason' => trim($arguments['reason'] ?? '') ?: null,
            ]);

            return ToolResult::success([
                'id' => $package->id,
                'name' => $package->name,
                'locked_by' => $context->user->id,
                'locked_at' => $package->locked_at->toISOString(),
                'message' => "Package '{$package->name}' erfolgreich gesperrt.",
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Sperren: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['dev', 'packages', 'lock'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'write',
            'idempotent' => false,
        ];
    }
}
