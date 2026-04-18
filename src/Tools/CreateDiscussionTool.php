<?php

namespace Platform\Dev\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Dev\Models\DevDiscussion;
use Platform\Dev\Models\DevPackage;
use Platform\Dev\Tools\Concerns\ResolvesDevTeam;

class CreateDiscussionTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesDevTeam;

    public function getName(): string
    {
        return 'dev.discussions.POST';
    }

    public function getDescription(): string
    {
        return 'POST /dev/discussions - Erstellt eine neue Discussion. ERFORDERLICH: package_id, title. Optional: body.';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                'team_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Team-ID. Default: aktuelles Team.',
                ],
                'package_id' => [
                    'type' => 'integer',
                    'description' => 'ERFORDERLICH: ID des Packages.',
                ],
                'title' => [
                    'type' => 'string',
                    'description' => 'ERFORDERLICH: Titel der Discussion.',
                ],
                'body' => [
                    'type' => 'string',
                    'description' => 'Optional: Inhalt der Discussion.',
                ],
            ],
            'required' => ['package_id', 'title'],
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

            if (empty($arguments['package_id'])) {
                return ToolResult::error('VALIDATION_ERROR', 'package_id ist erforderlich.');
            }

            $title = trim((string) ($arguments['title'] ?? ''));
            if ($title === '') {
                return ToolResult::error('VALIDATION_ERROR', 'title ist erforderlich.');
            }

            $package = DevPackage::where('id', (int) $arguments['package_id'])
                ->where('team_id', $teamId)
                ->first();

            if (!$package) {
                return ToolResult::error('NOT_FOUND', 'Package nicht gefunden.');
            }

            $discussion = DevDiscussion::create([
                'team_id' => $teamId,
                'created_by_user_id' => $context->user->id,
                'dev_package_id' => $package->id,
                'title' => $title,
                'body' => $arguments['body'] ?? null,
                'is_pinned' => false,
                'is_locked' => false,
            ]);

            return ToolResult::success([
                'id' => $discussion->id,
                'uuid' => $discussion->uuid,
                'title' => $discussion->title,
                'message' => 'Discussion erstellt.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Erstellen der Discussion: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['dev', 'discussions', 'create'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'write',
            'idempotent' => false,
        ];
    }
}
