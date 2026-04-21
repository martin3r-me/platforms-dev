<?php

namespace Platform\Dev\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardGetOperations;
use Platform\Dev\Models\DevIssue;
use Platform\Dev\Tools\Concerns\ResolvesDevTeam;

class ListIssuesTool implements ToolContract, ToolMetadataContract
{
    use HasStandardGetOperations;
    use ResolvesDevTeam;

    public function getName(): string
    {
        return 'dev.issues.GET';
    }

    public function getDescription(): string
    {
        return 'GET /dev/issues - Listet Issues. Optional: board_id, slot_id, status (open/closed), priority (low/normal/high), user_in_charge_id, search, sort, limit, offset.';
    }

    public function getSchema(): array
    {
        return $this->mergeSchemas($this->getStandardGetSchema(), [
            'properties' => [
                'team_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Team-ID. Default: aktuelles Team.',
                ],
                'board_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Nach Board filtern.',
                ],
                'slot_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Nach Slot (Spalte) filtern.',
                ],
                'status' => [
                    'type' => 'string',
                    'enum' => ['open', 'closed'],
                    'description' => 'Optional: Nach Status filtern.',
                ],
                'priority' => [
                    'type' => 'string',
                    'enum' => ['low', 'normal', 'high'],
                    'description' => 'Optional: Nach Prioritaet filtern.',
                ],
                'user_in_charge_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Nach zustaendigem User filtern.',
                ],
            ],
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

            $query = DevIssue::where('team_id', $teamId);

            if (!empty($arguments['board_id'])) {
                $query->where('dev_board_id', (int) $arguments['board_id']);
            }

            if (array_key_exists('slot_id', $arguments ?? [])) {
                if ($arguments['slot_id'] === null) {
                    $query->whereNull('dev_board_slot_id');
                } else {
                    $query->where('dev_board_slot_id', (int) $arguments['slot_id']);
                }
            }

            if (!empty($arguments['status'])) {
                $query->where('status', $arguments['status']);
            }

            if (!empty($arguments['priority'])) {
                $query->where('priority', $arguments['priority']);
            }

            if (!empty($arguments['user_in_charge_id'])) {
                $query->where('user_in_charge_id', (int) $arguments['user_in_charge_id']);
            }

            $this->applyStandardSearch($query, $arguments, ['title', 'description']);
            $this->applyStandardSort($query, $arguments, ['title', 'priority', 'status', 'order', 'slot_order', 'created_at', 'updated_at', 'due_date'], 'created_at', 'desc');

            $result = $this->applyStandardPaginationResult($query, $arguments);

            $issues = $result['data']->map(fn ($issue) => [
                'id' => $issue->id,
                'uuid' => $issue->uuid,
                'title' => $issue->title,
                'priority' => $issue->priority instanceof \BackedEnum ? $issue->priority->value : $issue->priority,
                'status' => $issue->status,
                'dev_board_id' => $issue->dev_board_id,
                'dev_board_slot_id' => $issue->dev_board_slot_id,
                'user_in_charge_id' => $issue->user_in_charge_id,
                'labels' => $issue->labels,
                'is_done' => $issue->is_done,
                'due_date' => $issue->due_date?->toDateString(),
                'created_at' => $issue->created_at?->toISOString(),
            ])->toArray();

            return ToolResult::success([
                'issues' => $issues,
                'pagination' => $result['pagination'],
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden der Issues: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'read',
            'tags' => ['dev', 'issues', 'list'],
            'read_only' => true,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'safe',
            'idempotent' => true,
        ];
    }
}
