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

class CreateIssueTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesDevTeam;

    public function getName(): string
    {
        return 'dev.issues.POST';
    }

    public function getDescription(): string
    {
        return 'POST /dev/issues - Erstellt ein neues Issue. Parameter: board_id (required), title (required). Optional: description (NICHT "content"), priority (low/normal/high), dev_board_slot_id, slot_order (Position im Slot, 0 = oberste; ohne Angabe wird ans Ende des Slots angehaengt — sequentielles Anlegen ergibt so eine stabile, aufsteigende Reihenfolge), labels, user_in_charge_id, due_date, story_points (xs/s/m/l/xl/xxl), dod_items (Array von {text, checked?} — NICHT "acceptance_criteria"). Fuer Umsortieren vorhandener Issues: dev.issues.reorder.';
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
                'title' => [
                    'type' => 'string',
                    'description' => 'Titel des Issues (ERFORDERLICH).',
                ],
                'description' => [
                    'type' => 'string',
                    'description' => 'Optional: Beschreibung des Issues.',
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
                    'description' => 'Optional: Labels als Array von Strings.',
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
            'required' => ['board_id', 'title'],
        ]);
    }

    /**
     * Known parameter aliases: maps common wrong names to correct ones.
     */
    private const PARAMETER_ALIASES = [
        'content' => 'description',
        'acceptance_criteria' => 'dod_items',
        'position' => 'slot_order',
    ];

    /**
     * All known parameter names for this tool.
     */
    private const KNOWN_PARAMETERS = [
        'team_id', 'board_id', 'title', 'description', 'priority',
        'dev_board_slot_id', 'slot_order', 'labels', 'user_in_charge_id', 'due_date',
        'story_points', 'dod_items',
        // Aliases (resolved before execution)
        'content', 'acceptance_criteria', 'position',
        // Internal/meta fields
        '_write_confirmation',
    ];

    private function resolveAliasesAndWarn(array &$arguments): array
    {
        $warnings = [];

        // Resolve aliases
        foreach (self::PARAMETER_ALIASES as $alias => $canonical) {
            if (array_key_exists($alias, $arguments) && !array_key_exists($canonical, $arguments)) {
                $arguments[$canonical] = $arguments[$alias];
                unset($arguments[$alias]);
                $warnings[] = "Parameter '{$alias}' wurde automatisch auf '{$canonical}' gemappt. Bitte direkt '{$canonical}' verwenden.";
            }
        }

        // Detect unknown parameters
        $unknown = array_diff(array_keys($arguments), self::KNOWN_PARAMETERS);
        if (!empty($unknown)) {
            $knownList = implode(', ', array_diff(self::KNOWN_PARAMETERS, ['_write_confirmation', 'content', 'acceptance_criteria', 'position']));
            $warnings[] = 'Unbekannte Parameter ignoriert: ' . implode(', ', $unknown) . '. Erlaubte Parameter: ' . $knownList . '.';
        }

        return $warnings;
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $warnings = $this->resolveAliasesAndWarn($arguments);

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

            $data = [
                'team_id' => $teamId,
                'created_by_user_id' => $context->user?->id,
                'dev_board_id' => $board->id,
                'title' => $arguments['title'],
                'status' => 'open',
            ];

            if (array_key_exists('description', $arguments)) {
                $data['description'] = $arguments['description'];
            }
            if (array_key_exists('priority', $arguments)) {
                $data['priority'] = $arguments['priority'];
            }
            if (array_key_exists('dev_board_slot_id', $arguments)) {
                $data['dev_board_slot_id'] = $arguments['dev_board_slot_id'];
            }
            if (array_key_exists('labels', $arguments)) {
                $data['labels'] = $arguments['labels'];
            }
            if (array_key_exists('user_in_charge_id', $arguments)) {
                $data['user_in_charge_id'] = $arguments['user_in_charge_id'];
            }
            if (array_key_exists('due_date', $arguments)) {
                $data['due_date'] = $arguments['due_date'];
            }
            if (array_key_exists('story_points', $arguments)) {
                $sp = $arguments['story_points'];
                $data['story_points'] = ($sp === '' || $sp === null) ? null : strtolower($sp);
            }
            if (array_key_exists('dod_items', $arguments)) {
                $data['acceptance_criteria'] = collect($arguments['dod_items'])->map(fn($item) => [
                    'text' => $item['text'],
                    'checked' => (bool) ($item['checked'] ?? false),
                ])->toArray();
            }

            $service = new DevIssueService();

            // Position im Slot: explizit gesetzt oder ans Ende anhaengen,
            // damit sequentielles Anlegen eine stabile Reihenfolge ergibt.
            if (array_key_exists('slot_order', $arguments) && $arguments['slot_order'] !== null && $arguments['slot_order'] !== '') {
                $data['slot_order'] = (int) $arguments['slot_order'];
                $data['order'] = (int) $arguments['slot_order'];
            } else {
                $next = $service->nextSlotOrder($board->id, $data['dev_board_slot_id'] ?? null);
                $data['slot_order'] = $next;
                $data['order'] = $next;
            }

            $issue = $service->createIssue($data);

            $result = [
                'issue' => [
                    'id' => $issue->id,
                    'uuid' => $issue->uuid,
                    'title' => $issue->title,
                    'priority' => $issue->priority instanceof \BackedEnum ? $issue->priority->value : $issue->priority,
                    'status' => $issue->status,
                    'dev_board_id' => $issue->dev_board_id,
                    'dev_board_slot_id' => $issue->dev_board_slot_id,
                    'slot_order' => $issue->slot_order,
                    'story_points' => $issue->story_points?->value,
                    'story_points_label' => $issue->story_points?->label(),
                    'story_points_numeric' => $issue->story_points?->points(),
                    'acceptance_criteria' => $issue->acceptance_criteria,
                ],
                'message' => 'Issue erfolgreich erstellt.',
            ];

            if (!empty($warnings)) {
                $result['warnings'] = $warnings;
            }

            return ToolResult::success($result);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Erstellen des Issues: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => false,
            'category' => 'action',
            'tags' => ['dev', 'issues', 'create'],
            'risk_level' => 'write',
            'requires_auth' => true,
            'requires_team' => true,
            'idempotent' => false,
        ];
    }
}
