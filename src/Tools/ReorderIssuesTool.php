<?php

namespace Platform\Dev\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Dev\Models\DevBoard;
use Platform\Dev\Models\DevBoardSlot;
use Platform\Dev\Services\DevIssueService;
use Platform\Dev\Tools\Concerns\ResolvesDevTeam;

class ReorderIssuesTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesDevTeam;

    public function getName(): string
    {
        return 'dev.issues.reorder';
    }

    public function getDescription(): string
    {
        return 'POST /dev/issues/reorder - Setzt die Reihenfolge (slot_order) mehrerer Issues deterministisch anhand einer ID-Liste. Parameter: board_id (required), issue_ids (required, Array von Issue-IDs in Ziel-Reihenfolge; Index 0 = oberste Position, aufsteigende slot_order). Optional: dev_board_slot_id (wenn angegeben, werden ALLE Issues zusaetzlich in diesen Slot verschoben; null = Backlog). Boardfremde/unbekannte IDs werden uebersprungen und in skipped_ids gemeldet. Hinweis: In der Board-Ansicht sortiert slot_order Issues nur INNERHALB eines Slots; Backlog-Issues (ohne Slot) werden nach Erstelldatum angezeigt.';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                'team_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Team-ID. Default: aktuelles Team aus Kontext.',
                ],
                'board_id' => [
                    'type' => 'integer',
                    'description' => 'ID des Boards (ERFORDERLICH).',
                ],
                'issue_ids' => [
                    'type' => 'array',
                    'items' => ['type' => 'integer'],
                    'description' => 'ERFORDERLICH: Issue-IDs in gewuenschter Reihenfolge (Index 0 = oberste Position). slot_order wird aufsteigend 0,1,2,... gesetzt.',
                ],
                'dev_board_slot_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Ziel-Slot. Wenn gesetzt, werden alle Issues zusaetzlich in diesen Slot verschoben. Null = Backlog.',
                ],
            ],
            'required' => ['board_id', 'issue_ids'],
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

            $board = DevBoard::find((int) $arguments['board_id']);
            if (!$board) {
                return ToolResult::error('NOT_FOUND', 'Board nicht gefunden.');
            }

            if ((int) $board->team_id !== $teamId) {
                return ToolResult::error('ACCESS_DENIED', 'Kein Zugriff auf dieses Board.');
            }

            $issueIds = $arguments['issue_ids'] ?? [];
            if (!is_array($issueIds) || empty($issueIds)) {
                return ToolResult::error('VALIDATION_ERROR', 'issue_ids muss ein nicht-leeres Array von Issue-IDs sein.');
            }
            $issueIds = array_map('intval', $issueIds);

            // Ziel-Slot nur beruecksichtigen, wenn der Parameter uebergeben wurde.
            $moveToSlot = array_key_exists('dev_board_slot_id', $arguments);
            $slotId = null;
            if ($moveToSlot) {
                $raw = $arguments['dev_board_slot_id'];
                $slotId = ($raw === null || $raw === '' || (int) $raw === 0) ? null : (int) $raw;

                if ($slotId !== null) {
                    $slot = DevBoardSlot::where('dev_board_id', $board->id)->find($slotId);
                    if (!$slot) {
                        return ToolResult::error('NOT_FOUND', 'Ziel-Slot gehoert nicht zu diesem Board.');
                    }
                }
            }

            $service = new DevIssueService();
            $result = $service->reorderIssues($board->id, $issueIds, $slotId, $moveToSlot);

            $ordered = array_map(fn ($issue) => [
                'id' => $issue->id,
                'title' => $issue->title,
                'dev_board_slot_id' => $issue->dev_board_slot_id,
                'slot_order' => $issue->slot_order,
            ], $result['updated']);

            $payload = [
                'updated_count' => count($result['updated']),
                'issues' => $ordered,
                'message' => count($result['updated']) . ' Issues neu sortiert.',
            ];

            if (!empty($result['skipped'])) {
                $payload['skipped_ids'] = $result['skipped'];
                $payload['warnings'] = ['Uebersprungen (nicht gefunden oder anderes Board): ' . implode(', ', $result['skipped']) . '.'];
            }

            return ToolResult::success($payload);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Umsortieren der Issues: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => false,
            'category' => 'action',
            'tags' => ['dev', 'issues', 'reorder', 'sort'],
            'risk_level' => 'write',
            'requires_auth' => true,
            'requires_team' => true,
            'idempotent' => true,
        ];
    }
}
