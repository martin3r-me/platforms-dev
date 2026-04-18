<?php

namespace Platform\Dev\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardGetOperations;
use Platform\Dev\Models\DevDiscussion;
use Platform\Dev\Tools\Concerns\ResolvesDevTeam;

class GetDiscussionTool implements ToolContract, ToolMetadataContract
{
    use HasStandardGetOperations;
    use ResolvesDevTeam;

    public function getName(): string
    {
        return 'dev.discussion.GET';
    }

    public function getDescription(): string
    {
        return 'GET /dev/discussion/:id - Zeigt eine Discussion mit allen Replies (threaded). ERFORDERLICH: discussion_id.';
    }

    public function getSchema(): array
    {
        return $this->mergeSchemas($this->getStandardGetSchema(), [
            'properties' => [
                'team_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Team-ID. Default: aktuelles Team.',
                ],
                'discussion_id' => [
                    'type' => 'integer',
                    'description' => 'ERFORDERLICH: ID der Discussion.',
                ],
            ],
            'required' => ['discussion_id'],
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

            if (empty($arguments['discussion_id'])) {
                return ToolResult::error('VALIDATION_ERROR', 'discussion_id ist erforderlich.');
            }

            $discussion = DevDiscussion::where('id', (int) $arguments['discussion_id'])
                ->where('team_id', $teamId)
                ->with(['createdBy', 'rootReplies.createdBy', 'rootReplies.children.createdBy'])
                ->first();

            if (!$discussion) {
                return ToolResult::error('NOT_FOUND', 'Discussion nicht gefunden.');
            }

            return ToolResult::success([
                'discussion' => [
                    'id' => $discussion->id,
                    'uuid' => $discussion->uuid,
                    'title' => $discussion->title,
                    'body' => $discussion->body,
                    'is_pinned' => $discussion->is_pinned,
                    'is_locked' => $discussion->is_locked,
                    'created_by' => $discussion->createdBy?->name,
                    'created_at' => $discussion->created_at?->toISOString(),
                    'replies' => $this->mapReplies($discussion->rootReplies),
                ],
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden der Discussion: ' . $e->getMessage());
        }
    }

    protected function mapReplies($replies): array
    {
        return $replies->map(fn ($reply) => [
            'id' => $reply->id,
            'body' => $reply->body,
            'created_by' => $reply->createdBy?->name,
            'children' => $this->mapReplies($reply->children),
            'created_at' => $reply->created_at?->toISOString(),
        ])->toArray();
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'read',
            'tags' => ['dev', 'discussion', 'detail'],
            'read_only' => true,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'safe',
            'idempotent' => true,
        ];
    }
}
