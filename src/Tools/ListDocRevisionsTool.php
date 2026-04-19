<?php

namespace Platform\Dev\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Dev\Models\DevDocPage;
use Platform\Dev\Tools\Concerns\ResolvesDevTeam;

class ListDocRevisionsTool implements ToolContract, ToolMetadataContract
{
    use ResolvesDevTeam;

    public function getName(): string
    {
        return 'dev.docs.revisions.GET';
    }

    public function getDescription(): string
    {
        return 'GET /dev/docs/{page_id}/revisions - Zeigt die Revisionshistorie einer Dokumentations-Seite. ERFORDERLICH: page_id.';
    }

    public function getSchema(): array
    {
        return [
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

            $pageId = (int) ($arguments['page_id'] ?? 0);
            if ($pageId <= 0) {
                return ToolResult::error('VALIDATION_ERROR', 'page_id ist erforderlich.');
            }

            $page = DevDocPage::where('team_id', $teamId)->find($pageId);
            if (!$page) {
                return ToolResult::error('NOT_FOUND', 'Doc-Page nicht gefunden (oder kein Zugriff).');
            }

            $revisions = $page->revisions()
                ->with('createdBy:id,name')
                ->orderByDesc('version')
                ->get()
                ->map(fn ($rev) => [
                    'version' => $rev->version,
                    'change_summary' => $rev->change_summary,
                    'created_by' => $rev->createdBy?->name,
                    'created_by_user_id' => $rev->created_by_user_id,
                    'created_at' => $rev->created_at?->toISOString(),
                ])
                ->toArray();

            return ToolResult::success([
                'page_id' => $page->id,
                'page_title' => $page->title,
                'revisions' => $revisions,
                'total' => count($revisions),
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
            'tags' => ['dev', 'docs', 'revisions'],
            'risk_level' => 'read',
            'requires_auth' => true,
            'requires_team' => true,
            'idempotent' => true,
        ];
    }
}
