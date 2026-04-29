<?php

namespace Platform\Dev\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Dev\Models\DevIssue;
use Platform\Dev\Tools\Concerns\ResolvesDevTeam;

class BulkDeleteIssuesTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesDevTeam;

    public function getName(): string
    {
        return 'dev.issues.bulk.DELETE';
    }

    public function getDescription(): string
    {
        return 'DELETE /dev/issues/bulk - Loescht mehrere Issues auf einmal. Parameter: issue_ids (required, Array von Issue-IDs).';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                'team_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Team-ID. Default: aktuelles Team aus Kontext.',
                ],
                'issue_ids' => [
                    'type' => 'array',
                    'items' => ['type' => 'integer'],
                    'description' => 'ERFORDERLICH: Array von Issue-IDs zum Loeschen.',
                ],
            ],
            'required' => ['issue_ids'],
        ]);
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $resolved = $this->resolveTeam($arguments, $context);
            if ($resolved['error']) {
                return $resolved['error'];
            }
            $teamId = (int) $resolved['team_id'];

            $ids = $arguments['issue_ids'] ?? [];
            if (empty($ids)) {
                return ToolResult::error('VALIDATION_ERROR', 'Mindestens eine Issue-ID angeben.');
            }

            $issues = DevIssue::where('team_id', $teamId)->whereIn('id', $ids)->get();
            $deletedIds = [];

            foreach ($issues as $issue) {
                $issue->delete();
                $deletedIds[] = $issue->id;
            }

            $notFound = array_diff($ids, $deletedIds);

            return ToolResult::success([
                'deleted_count' => count($deletedIds),
                'deleted_ids' => $deletedIds,
                'not_found' => array_values($notFound),
                'message' => count($deletedIds) . ' Issues geloescht.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Bulk-Delete: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => false,
            'category' => 'action',
            'tags' => ['dev', 'issues', 'bulk', 'delete'],
            'risk_level' => 'destructive',
            'requires_auth' => true,
            'requires_team' => true,
            'idempotent' => false,
        ];
    }
}
