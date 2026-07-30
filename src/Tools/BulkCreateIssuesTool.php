<?php

namespace Platform\Dev\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Dev\Models\DevBoard;
use Platform\Dev\Services\DevIssueService;
use Platform\Dev\Tools\Concerns\ResolvesDevTeam;

class BulkCreateIssuesTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesDevTeam;

    public function getName(): string
    {
        return 'dev.issues.bulk.POST';
    }

    public function getDescription(): string
    {
        return 'POST /dev/issues/bulk - Erstellt mehrere Issues auf einmal. Parameter: board_id (required), issues (required, Array). Pro Issue: title (ERFORDERLICH), optional: description (NICHT "content"), priority (low/normal/high), dev_board_slot_id (Null = Backlog), slot_order (Position im Slot, 0 = oberste; ohne Angabe ans Ende), labels, user_in_charge_id, due_date, story_points (xs/s/m/l/xl/xxl), dod_items (Array von {text, checked?} — NICHT "acceptance_criteria"). Unbekannte Felder pro Issue fuehren zu einem Fehler.';
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
                'issues' => [
                    'type' => 'array',
                    'description' => 'ERFORDERLICH: Array von Issues.',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'title' => [
                                'type' => 'string',
                                'description' => 'Titel des Issues (ERFORDERLICH).',
                            ],
                            'description' => [
                                'type' => 'string',
                                'description' => 'Optional: Beschreibung.',
                            ],
                            'priority' => [
                                'type' => 'string',
                                'enum' => ['low', 'normal', 'high'],
                                'description' => 'Optional: Prioritaet. Default: normal.',
                            ],
                            'dev_board_slot_id' => [
                                'type' => 'integer',
                                'description' => 'Optional: Slot-ID (Spalte). Null = Backlog.',
                            ],
                            'slot_order' => [
                                'type' => 'integer',
                                'description' => 'Optional: Position innerhalb des Slots (0 = oberste). Ohne Angabe wird das Issue ans Ende des Slots angehaengt.',
                            ],
                            'labels' => [
                                'type' => 'array',
                                'items' => ['type' => 'string'],
                                'description' => 'Optional: Labels.',
                            ],
                            'user_in_charge_id' => [
                                'type' => 'integer',
                                'description' => 'Optional: ID des zustaendigen Users.',
                            ],
                            'due_date' => [
                                'type' => 'string',
                                'description' => 'Optional: Faelligkeitsdatum (YYYY-MM-DD).',
                            ],
                            'story_points' => [
                                'type' => 'string',
                                'enum' => ['xs', 's', 'm', 'l', 'xl', 'xxl'],
                                'description' => 'Optional: Story Points (T-Shirt Sizes: xs=1, s=2, m=3, l=5, xl=8, xxl=13).',
                            ],
                            'dod_items' => [
                                'type' => 'array',
                                'items' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'text' => ['type' => 'string', 'description' => 'Text des DOD-Kriteriums.'],
                                        'checked' => ['type' => 'boolean', 'description' => 'Erledigt-Status. Default: false.'],
                                    ],
                                    'required' => ['text'],
                                ],
                                'description' => 'Optional: DOD-Kriterien (Definition of Done). Array von {text, checked?}.',
                            ],
                        ],
                        'required' => ['title'],
                    ],
                ],
            ],
            'required' => ['board_id', 'issues'],
        ]);
    }

    /**
     * Known parameter aliases pro Issue: mappt haeufige falsche Namen auf die korrekten.
     */
    private const ITEM_PARAMETER_ALIASES = [
        'content' => 'description',
        'acceptance_criteria' => 'dod_items',
        'position' => 'slot_order',
    ];

    /**
     * Alle bekannten Feldnamen pro Issue.
     */
    private const ITEM_KNOWN_PARAMETERS = [
        'title', 'description', 'priority',
        'dev_board_slot_id', 'slot_order', 'labels', 'user_in_charge_id', 'due_date',
        'story_points', 'dod_items',
        // Aliases (werden vor der Ausfuehrung aufgeloest)
        'content', 'acceptance_criteria', 'position',
    ];

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

            $issuesData = $arguments['issues'] ?? [];
            if (empty($issuesData)) {
                return ToolResult::error('VALIDATION_ERROR', 'Mindestens ein Issue muss angegeben werden.');
            }

            $service = new DevIssueService();

            // Lokale slot_order-Zaehler pro Slot, damit sequentiell angelegte
            // Issues (ohne explizite slot_order) am Ende des jeweiligen Slots landen
            // statt alle auf denselben Wert. Key: (int) slotId bzw. 'backlog'.
            $slotCursors = [];

            $warnings = [];
            $preparedIssues = [];
            foreach ($issuesData as $index => $issueData) {
                if (!is_array($issueData)) {
                    return ToolResult::error('VALIDATION_ERROR', 'Issue #' . ($index + 1) . ' ist kein gueltiges Objekt.');
                }

                // Aliases aufloesen (content -> description usw.).
                foreach (self::ITEM_PARAMETER_ALIASES as $alias => $canonical) {
                    if (array_key_exists($alias, $issueData) && !array_key_exists($canonical, $issueData)) {
                        $issueData[$canonical] = $issueData[$alias];
                        unset($issueData[$alias]);
                        $warnings[] = "Issue #" . ($index + 1) . ": Parameter '{$alias}' wurde automatisch auf '{$canonical}' gemappt. Bitte direkt '{$canonical}' verwenden.";
                    }
                }

                // Unbekannte Felder fuehren zu einem Fehler statt still verworfen zu werden.
                $unknown = array_diff(array_keys($issueData), self::ITEM_KNOWN_PARAMETERS);
                if (!empty($unknown)) {
                    $knownList = implode(', ', array_diff(self::ITEM_KNOWN_PARAMETERS, ['content', 'acceptance_criteria', 'position']));
                    return ToolResult::error(
                        'VALIDATION_ERROR',
                        'Issue #' . ($index + 1) . ': Unbekannte Felder: ' . implode(', ', $unknown) . '. Erlaubte Felder: ' . $knownList . '.'
                    );
                }

                if (empty($issueData['title'])) {
                    return ToolResult::error('VALIDATION_ERROR', 'Jedes Issue benoetigt einen Titel.');
                }

                $prepared = [
                    'status' => 'open',
                    'title' => $issueData['title'],
                ];

                if (array_key_exists('description', $issueData)) {
                    $prepared['description'] = $issueData['description'];
                }
                if (array_key_exists('priority', $issueData)) {
                    $prepared['priority'] = $issueData['priority'];
                }
                if (array_key_exists('dev_board_slot_id', $issueData)) {
                    $prepared['dev_board_slot_id'] = $issueData['dev_board_slot_id'];
                }
                if (array_key_exists('labels', $issueData)) {
                    $prepared['labels'] = $issueData['labels'];
                }
                if (array_key_exists('user_in_charge_id', $issueData)) {
                    $prepared['user_in_charge_id'] = $issueData['user_in_charge_id'];
                }
                if (array_key_exists('due_date', $issueData)) {
                    $prepared['due_date'] = $issueData['due_date'];
                }
                if (array_key_exists('story_points', $issueData)) {
                    $sp = $issueData['story_points'];
                    $prepared['story_points'] = ($sp === '' || $sp === null) ? null : strtolower($sp);
                }
                if (array_key_exists('dod_items', $issueData)) {
                    $prepared['acceptance_criteria'] = collect($issueData['dod_items'])->map(fn ($item) => [
                        'text' => $item['text'],
                        'checked' => (bool) ($item['checked'] ?? false),
                    ])->toArray();
                }

                // Position im Slot: explizit gesetzt oder ans Ende anhaengen.
                $slotId = $prepared['dev_board_slot_id'] ?? null;
                if (array_key_exists('slot_order', $issueData) && $issueData['slot_order'] !== null && $issueData['slot_order'] !== '') {
                    $order = (int) $issueData['slot_order'];
                } else {
                    $cursorKey = $slotId === null ? 'backlog' : (int) $slotId;
                    if (!array_key_exists($cursorKey, $slotCursors)) {
                        $slotCursors[$cursorKey] = $service->nextSlotOrder($board->id, $slotId === null ? null : (int) $slotId);
                    }
                    $order = $slotCursors[$cursorKey]++;
                }
                $prepared['slot_order'] = $order;
                $prepared['order'] = $order;

                $preparedIssues[] = $prepared;
            }

            $created = $service->bulkCreate(
                $board->id,
                $teamId,
                $context->user?->id,
                $preparedIssues
            );

            $createdIds = array_map(fn ($issue) => [
                'id' => $issue->id,
                'uuid' => $issue->uuid,
                'title' => $issue->title,
                'dev_board_slot_id' => $issue->dev_board_slot_id,
                'slot_order' => $issue->slot_order,
                'story_points' => $issue->story_points?->value,
                'acceptance_criteria' => $issue->acceptance_criteria,
            ], $created);

            $result = [
                'created_count' => count($created),
                'issues' => $createdIds,
                'message' => count($created) . ' Issues erfolgreich erstellt.',
            ];

            if (!empty($warnings)) {
                $result['warnings'] = $warnings;
            }

            return ToolResult::success($result);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Erstellen der Issues: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => false,
            'category' => 'action',
            'tags' => ['dev', 'issues', 'bulk', 'create'],
            'risk_level' => 'write',
            'requires_auth' => true,
            'requires_team' => true,
            'idempotent' => false,
        ];
    }
}
