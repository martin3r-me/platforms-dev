<?php

namespace Platform\Dev\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Dev\Enums\DocPageType;
use Platform\Dev\Models\DevDocPage;
use Platform\Dev\Services\DevDocService;
use Platform\Dev\Tools\Concerns\ResolvesDevTeam;

class DeleteDocPageTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesDevTeam;

    public function getName(): string
    {
        return 'dev.docs.DELETE';
    }

    public function getDescription(): string
    {
        return 'DELETE /dev/docs/{page_id} - Loescht eine Custom-Dokumentationsseite. Nur type=custom ist loeschbar. ERFORDERLICH: page_id.';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                'team_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Team-ID. Default: aktuelles Team aus Kontext.',
                ],
                'page_id' => [
                    'type' => 'integer',
                    'description' => 'ID der Doc-Page (ERFORDERLICH).',
                ],
            ],
            'required' => ['page_id'],
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

            $pageId = (int) ($arguments['page_id'] ?? 0);
            if ($pageId <= 0) {
                return ToolResult::error('VALIDATION_ERROR', 'page_id ist erforderlich.');
            }

            $page = DevDocPage::where('team_id', $teamId)->find($pageId);
            if (!$page) {
                return ToolResult::error('NOT_FOUND', 'Doc-Page nicht gefunden (oder kein Zugriff).');
            }

            if ($page->type !== DocPageType::Custom) {
                return ToolResult::error('VALIDATION_ERROR', 'Nur Custom-Seiten koennen geloescht werden. Vordefinierte Kapitel (type=' . ($page->type instanceof \BackedEnum ? $page->type->value : $page->type) . ') sind geschuetzt.');
            }

            $title = $page->title;
            $docService = new DevDocService();
            $docService->deletePage($page);

            return ToolResult::success([
                'message' => "Doc-Page '{$title}' geloescht.",
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Loeschen: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => false,
            'category' => 'action',
            'tags' => ['dev', 'docs', 'delete'],
            'risk_level' => 'write',
            'requires_auth' => true,
            'requires_team' => true,
            'idempotent' => false,
        ];
    }
}
