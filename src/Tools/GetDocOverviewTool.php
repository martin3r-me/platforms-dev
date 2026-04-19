<?php

namespace Platform\Dev\Tools;

use Illuminate\Support\Str;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Dev\Models\DevDocPage;
use Platform\Dev\Models\DevPackage;
use Platform\Dev\Tools\Concerns\ResolvesDevTeam;

class GetDocOverviewTool implements ToolContract, ToolMetadataContract
{
    use ResolvesDevTeam;

    public function getName(): string
    {
        return 'dev.docs.overview';
    }

    public function getDescription(): string
    {
        return 'GET /dev/docs/overview - Zeigt alle Dokumentations-Kapitel eines Packages. ERFORDERLICH: package_id.';
    }

    public function getSchema(): array
    {
        return [
            'properties' => [
                'team_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Team-ID. Default: aktuelles Team aus Kontext.',
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

            $package = DevPackage::where('team_id', $teamId)->find($packageId);
            if (!$package) {
                return ToolResult::error('NOT_FOUND', 'Package nicht gefunden (oder kein Zugriff).');
            }

            $pages = DevDocPage::where('dev_package_id', $packageId)
                ->orderBy('position')
                ->orderBy('title')
                ->get();

            $data = $pages->map(function (DevDocPage $page) {
                $latestRevision = $page->revisions()->first();

                return [
                    'id' => $page->id,
                    'uuid' => $page->uuid,
                    'type' => $page->type instanceof \BackedEnum ? $page->type->value : $page->type,
                    'title' => $page->title,
                    'status' => $page->status,
                    'excerpt' => Str::limit(strip_tags($page->content ?? ''), 200),
                    'version' => $latestRevision?->version ?? 0,
                    'updated_at' => $page->updated_at?->toISOString(),
                ];
            })->toArray();

            return ToolResult::success([
                'package_id' => $packageId,
                'package_name' => $package->name,
                'pages' => $data,
                'total' => count($data),
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => true,
            'category' => 'query',
            'tags' => ['dev', 'docs', 'overview'],
            'risk_level' => 'read',
            'requires_auth' => true,
            'requires_team' => true,
            'idempotent' => true,
        ];
    }
}
