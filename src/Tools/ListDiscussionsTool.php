<?php

namespace Platform\Dev\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardGetOperations;
use Platform\Dev\Models\DevDiscussion;
use Platform\Dev\Models\DevPackage;
use Platform\Dev\Tools\Concerns\ResolvesDevTeam;

class ListDiscussionsTool implements ToolContract, ToolMetadataContract
{
    use HasStandardGetOperations;
    use ResolvesDevTeam;

    public function getName(): string
    {
        return 'dev.discussions.GET';
    }

    public function getDescription(): string
    {
        return 'GET /dev/discussions - Listet Discussions eines Packages. ERFORDERLICH: package_id. Optional: search, sort, limit, offset.';
    }

    public function getSchema(): array
    {
        return $this->mergeSchemas($this->getStandardGetSchema(), [
            'properties' => [
                'team_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Team-ID. Default: aktuelles Team.',
                ],
                'package_id' => [
                    'type' => 'integer',
                    'description' => 'ERFORDERLICH: ID des Packages.',
                ],
            ],
            'required' => ['package_id'],
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

            if (empty($arguments['package_id'])) {
                return ToolResult::error('VALIDATION_ERROR', 'package_id ist erforderlich.');
            }

            $package = DevPackage::where('id', (int) $arguments['package_id'])
                ->where('team_id', $teamId)
                ->first();

            if (!$package) {
                return ToolResult::error('NOT_FOUND', 'Package nicht gefunden.');
            }

            $query = DevDiscussion::where('dev_package_id', $package->id)
                ->where('team_id', $teamId);

            $this->applyStandardSearch($query, $arguments, ['title', 'body']);
            $this->applyStandardSort($query, $arguments, ['title', 'is_pinned', 'created_at', 'updated_at'], 'is_pinned', 'desc');

            // Secondary default sort: updated_at desc
            if (empty($arguments['sort'])) {
                $query->orderBy('updated_at', 'desc');
            }

            $result = $this->applyStandardPaginationResult($query, $arguments);

            $discussions = $result['query']->withCount('replies')->with('createdBy')->get()->map(fn ($d) => [
                'id' => $d->id,
                'uuid' => $d->uuid,
                'title' => $d->title,
                'body' => $d->body ? mb_substr($d->body, 0, 200) : null,
                'is_pinned' => $d->is_pinned,
                'is_locked' => $d->is_locked,
                'replies_count' => $d->replies_count,
                'created_by' => $d->createdBy?->name,
                'created_at' => $d->created_at?->toISOString(),
            ])->toArray();

            return ToolResult::success([
                'discussions' => $discussions,
                'pagination' => $result['pagination'],
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden der Discussions: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'read',
            'tags' => ['dev', 'discussions', 'list'],
            'read_only' => true,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'safe',
            'idempotent' => true,
        ];
    }
}
