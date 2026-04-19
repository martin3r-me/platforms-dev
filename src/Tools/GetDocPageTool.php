<?php

namespace Platform\Dev\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Dev\Models\DevDocPage;
use Platform\Dev\Tools\Concerns\ResolvesDevTeam;

class GetDocPageTool implements ToolContract, ToolMetadataContract
{
    use ResolvesDevTeam;

    public function getName(): string
    {
        return 'dev.docs.GET';
    }

    public function getDescription(): string
    {
        return 'GET /dev/docs/{page_id} - Zeigt eine Dokumentations-Seite mit vollem Content. ERFORDERLICH: page_id.';
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

            $latestRevision = $page->revisions()->first();

            return ToolResult::success([
                'id' => $page->id,
                'uuid' => $page->uuid,
                'type' => $page->type instanceof \BackedEnum ? $page->type->value : $page->type,
                'type_label' => $page->type instanceof \BackedEnum ? $page->type->label() : $page->type,
                'type_description' => $page->type instanceof \BackedEnum ? $page->type->description() : '',
                'title' => $page->title,
                'slug' => $page->slug,
                'content' => $page->content,
                'status' => $page->status,
                'position' => $page->position,
                'dev_package_id' => $page->dev_package_id,
                'created_by_user_id' => $page->created_by_user_id,
                'last_edited_by_user_id' => $page->last_edited_by_user_id,
                'latest_revision' => $latestRevision ? [
                    'version' => $latestRevision->version,
                    'change_summary' => $latestRevision->change_summary,
                    'created_at' => $latestRevision->created_at?->toISOString(),
                ] : null,
                'created_at' => $page->created_at?->toISOString(),
                'updated_at' => $page->updated_at?->toISOString(),
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
            'tags' => ['dev', 'docs', 'get'],
            'risk_level' => 'read',
            'requires_auth' => true,
            'requires_team' => true,
            'idempotent' => true,
        ];
    }
}
