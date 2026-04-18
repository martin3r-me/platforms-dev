<?php

namespace Platform\Dev\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Dev\Models\DevDiscussion;
use Platform\Dev\Models\DevDiscussionReply;
use Platform\Dev\Tools\Concerns\ResolvesDevTeam;

class ReplyToDiscussionTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesDevTeam;

    public function getName(): string
    {
        return 'dev.discussions.reply.POST';
    }

    public function getDescription(): string
    {
        return 'POST /dev/discussions/reply - Antwortet auf eine Discussion. ERFORDERLICH: discussion_id, body. Optional: parent_id (fuer verschachtelte Antwort).';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                'team_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Team-ID. Default: aktuelles Team.',
                ],
                'discussion_id' => [
                    'type' => 'integer',
                    'description' => 'ERFORDERLICH: ID der Discussion.',
                ],
                'body' => [
                    'type' => 'string',
                    'description' => 'ERFORDERLICH: Inhalt der Antwort.',
                ],
                'parent_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: ID einer bestehenden Reply fuer verschachtelte Antwort.',
                ],
            ],
            'required' => ['discussion_id', 'body'],
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

            if (!$context->user) {
                return ToolResult::error('AUTH_ERROR', 'Kein User im Kontext gefunden.');
            }

            if (empty($arguments['discussion_id'])) {
                return ToolResult::error('VALIDATION_ERROR', 'discussion_id ist erforderlich.');
            }

            $body = trim((string) ($arguments['body'] ?? ''));
            if ($body === '') {
                return ToolResult::error('VALIDATION_ERROR', 'body ist erforderlich.');
            }

            $discussion = DevDiscussion::where('id', (int) $arguments['discussion_id'])
                ->where('team_id', $teamId)
                ->first();

            if (!$discussion) {
                return ToolResult::error('NOT_FOUND', 'Discussion nicht gefunden.');
            }

            if ($discussion->is_locked) {
                return ToolResult::error('LOCKED', 'Diese Discussion ist gesperrt. Es koennen keine neuen Antworten erstellt werden.');
            }

            $parentId = null;
            if (!empty($arguments['parent_id'])) {
                $parentReply = DevDiscussionReply::where('id', (int) $arguments['parent_id'])
                    ->where('dev_discussion_id', $discussion->id)
                    ->first();

                if (!$parentReply) {
                    return ToolResult::error('NOT_FOUND', 'Parent-Reply nicht gefunden.');
                }
                $parentId = $parentReply->id;
            }

            $reply = DevDiscussionReply::create([
                'team_id' => $teamId,
                'created_by_user_id' => $context->user->id,
                'dev_discussion_id' => $discussion->id,
                'parent_id' => $parentId,
                'body' => $body,
            ]);

            return ToolResult::success([
                'id' => $reply->id,
                'message' => 'Antwort erstellt.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Erstellen der Antwort: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['dev', 'discussions', 'reply', 'create'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'write',
            'idempotent' => false,
        ];
    }
}
