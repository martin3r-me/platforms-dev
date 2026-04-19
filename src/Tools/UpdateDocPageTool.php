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

class UpdateDocPageTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesDevTeam;

    public function getName(): string
    {
        return 'dev.docs.PUT';
    }

    public function getDescription(): string
    {
        return 'PUT /dev/docs/{page_id} - Aktualisiert eine Dokumentations-Seite (erstellt automatisch neue Revision). ERFORDERLICH: page_id. Optional: title, content, status, op+text/old/new/heading/level/mode/start/end, change_summary. Content-Operationen via op: append, prepend, replace_exact, upsert_heading, replace_between.';
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
                'title' => [
                    'type' => 'string',
                    'description' => 'Optional: Neuer Titel.',
                ],
                'content' => [
                    'type' => 'string',
                    'description' => 'Optional: Neuer Content (ersetzt komplett). Wird ignoriert wenn op gesetzt ist.',
                ],
                'status' => [
                    'type' => 'string',
                    'enum' => ['draft', 'published'],
                    'description' => 'Optional: Neuer Status.',
                ],
                'op' => [
                    'type' => 'string',
                    'enum' => ['append', 'prepend', 'replace_exact', 'upsert_heading', 'replace_between'],
                    'description' => 'Optional: Content-Operation statt vollstaendigem Ersetzen.',
                ],
                'text' => [
                    'type' => 'string',
                    'description' => 'Text fuer op=append/prepend/upsert_heading/replace_between.',
                ],
                'old' => [
                    'type' => 'string',
                    'description' => 'Alter Text fuer op=replace_exact.',
                ],
                'new' => [
                    'type' => 'string',
                    'description' => 'Neuer Text fuer op=replace_exact.',
                ],
                'heading' => [
                    'type' => 'string',
                    'description' => 'Heading-Text fuer op=upsert_heading.',
                ],
                'level' => [
                    'type' => 'integer',
                    'description' => 'Heading-Level (1-6) fuer op=upsert_heading. Default: 2.',
                ],
                'mode' => [
                    'type' => 'string',
                    'enum' => ['append', 'replace'],
                    'description' => 'Modus fuer op=upsert_heading: append (Standard) oder replace.',
                ],
                'start' => [
                    'type' => 'string',
                    'description' => 'Start-Marker fuer op=replace_between.',
                ],
                'end' => [
                    'type' => 'string',
                    'description' => 'End-Marker fuer op=replace_between.',
                ],
                'change_summary' => [
                    'type' => 'string',
                    'description' => 'Optional: Zusammenfassung der Aenderung fuer die Revision.',
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

            $docService = new DevDocService();
            $payload = [];

            // Handle content operation
            $op = array_key_exists('op', $arguments) ? (string) $arguments['op'] : null;
            if ($op !== null) {
                $op = trim($op);
                if ($op === '') {
                    $op = null;
                }
            }

            if ($op !== null) {
                $allowedOps = ['append', 'prepend', 'replace_exact', 'upsert_heading', 'replace_between'];
                if (!in_array($op, $allowedOps, true)) {
                    return ToolResult::error('VALIDATION_ERROR', 'Unbekannte op: ' . $op . '. Erlaubt: ' . implode(', ', $allowedOps));
                }

                $currentContent = (string) ($page->content ?? '');
                $result = $docService->applyContentOp($currentContent, $op, $arguments);

                if (!$result['success']) {
                    return ToolResult::error('VALIDATION_ERROR', $result['error']);
                }

                $newContent = (string) $result['content'];
                if ($newContent === $currentContent) {
                    return ToolResult::error('NO_CHANGE', 'Keine Aenderung: Inhalt unveraendert.');
                }

                $payload['content'] = $newContent;
            }

            // Title
            if (array_key_exists('title', $arguments) && $arguments['title'] !== null) {
                $title = trim((string) $arguments['title']);
                if ($title === '') {
                    return ToolResult::error('VALIDATION_ERROR', 'Titel darf nicht leer sein.');
                }
                $payload['title'] = $title;
            }

            // Content (direct replace, only if no op)
            if ($op === null && array_key_exists('content', $arguments) && $arguments['content'] !== null) {
                $payload['content'] = (string) $arguments['content'];
            }

            // Status
            if (array_key_exists('status', $arguments) && $arguments['status'] !== null) {
                if (!in_array($arguments['status'], ['draft', 'published'])) {
                    return ToolResult::error('VALIDATION_ERROR', 'Status muss draft oder published sein.');
                }
                $payload['status'] = $arguments['status'];
            }

            if (empty($payload)) {
                return ToolResult::error('NO_CHANGE', 'Keine Aenderungen uebergeben.');
            }

            $page = $docService->updatePage($page, $payload, $context->user->id);

            // Create revision
            $changeSummary = $arguments['change_summary'] ?? null;
            $revision = $docService->createRevision($page, $context->user->id, $changeSummary);

            return ToolResult::success([
                'id' => $page->id,
                'uuid' => $page->uuid,
                'title' => $page->title,
                'status' => $page->status,
                'revision_version' => $revision->version,
                'updated_at' => $page->updated_at?->toISOString(),
                'message' => "Doc-Page '{$page->title}' aktualisiert (Revision v{$revision->version}).",
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Aktualisieren: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => false,
            'category' => 'action',
            'tags' => ['dev', 'docs', 'update'],
            'risk_level' => 'write',
            'requires_auth' => true,
            'requires_team' => true,
            'idempotent' => false,
        ];
    }
}
