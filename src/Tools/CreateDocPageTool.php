<?php

namespace Platform\Dev\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Dev\Models\DevPackage;
use Platform\Dev\Services\DevDocService;
use Platform\Dev\Tools\Concerns\ResolvesDevTeam;

class CreateDocPageTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesDevTeam;

    public function getName(): string
    {
        return 'dev.docs.POST';
    }

    public function getDescription(): string
    {
        return 'POST /dev/docs - Erstellt eine neue Custom-Dokumentationsseite. ERFORDERLICH: package_id, title. Optional: content.';
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
                'title' => [
                    'type' => 'string',
                    'description' => 'Titel der Seite (ERFORDERLICH).',
                ],
                'content' => [
                    'type' => 'string',
                    'description' => 'Optional: Initialer Content (Markdown).',
                ],
            ],
            'required' => ['package_id', 'title'],
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

            $package = DevPackage::where('team_id', $teamId)->find($packageId);
            if (!$package) {
                return ToolResult::error('NOT_FOUND', 'Package nicht gefunden (oder kein Zugriff).');
            }

            $title = trim((string) ($arguments['title'] ?? ''));
            if ($title === '') {
                return ToolResult::error('VALIDATION_ERROR', 'title ist erforderlich.');
            }

            $docService = new DevDocService();
            $page = $docService->createPage([
                'team_id' => $teamId,
                'dev_package_id' => $packageId,
                'title' => $title,
                'content' => $arguments['content'] ?? null,
            ], $context->user->id);

            return ToolResult::success([
                'id' => $page->id,
                'uuid' => $page->uuid,
                'type' => 'custom',
                'title' => $page->title,
                'slug' => $page->slug,
                'status' => $page->status,
                'dev_package_id' => $page->dev_package_id,
                'message' => "Custom Doc-Page '{$page->title}' erstellt.",
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Erstellen: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => false,
            'category' => 'action',
            'tags' => ['dev', 'docs', 'create'],
            'risk_level' => 'write',
            'requires_auth' => true,
            'requires_team' => true,
            'idempotent' => false,
        ];
    }
}
