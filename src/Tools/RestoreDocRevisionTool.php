<?php

namespace Platform\Dev\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Dev\Models\DevDocPage;
use Platform\Dev\Services\DevDocService;
use Platform\Dev\Tools\Concerns\ResolvesDevTeam;

class RestoreDocRevisionTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesDevTeam;

    public function getName(): string
    {
        return 'dev.docs.revisions.restore';
    }

    public function getDescription(): string
    {
        return 'POST /dev/docs/{page_id}/revisions/restore - Stellt eine fruehere Version einer Doc-Page wieder her. ERFORDERLICH: page_id, version.';
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
                'version' => [
                    'type' => 'integer',
                    'description' => 'Version der Revision (ERFORDERLICH).',
                ],
            ],
            'required' => ['page_id', 'version'],
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

            $version = (int) ($arguments['version'] ?? 0);
            if ($version <= 0) {
                return ToolResult::error('VALIDATION_ERROR', 'version ist erforderlich.');
            }

            $page = DevDocPage::where('team_id', $teamId)->find($pageId);
            if (!$page) {
                return ToolResult::error('NOT_FOUND', 'Doc-Page nicht gefunden (oder kein Zugriff).');
            }

            $revision = $page->revisions()->where('version', $version)->first();
            if (!$revision) {
                return ToolResult::error('NOT_FOUND', "Revision Version {$version} nicht gefunden.");
            }

            $docService = new DevDocService();
            $page = $docService->restoreRevision($page, $revision, $context->user->id);

            $latestRevision = $page->revisions()->first();

            return ToolResult::success([
                'id' => $page->id,
                'uuid' => $page->uuid,
                'title' => $page->title,
                'status' => $page->status,
                'restored_from_version' => $version,
                'new_version' => $latestRevision?->version,
                'updated_at' => $page->updated_at?->toISOString(),
                'message' => "Doc-Page '{$page->title}' auf Version {$version} wiederhergestellt (neue Revision v{$latestRevision?->version}).",
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Wiederherstellen: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => false,
            'category' => 'action',
            'tags' => ['dev', 'docs', 'revisions', 'restore'],
            'risk_level' => 'write',
            'requires_auth' => true,
            'requires_team' => true,
            'idempotent' => false,
        ];
    }
}
