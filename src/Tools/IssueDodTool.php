<?php

namespace Platform\Dev\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Dev\Models\DevIssue;
use Platform\Dev\Tools\Concerns\ResolvesDevTeam;

class IssueDodTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesDevTeam;

    public function getName(): string
    {
        return 'dev.issue.dod';
    }

    public function getDescription(): string
    {
        return 'Verwaltet die Definition of Done (DoD) eines Issues. ERFORDERLICH: issue_id (integer) + operation (string). Operationen: add (text), update (index + text), toggle (index), remove (index), reorder (order Array).';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                'team_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Team-ID. Default: aktuelles Team aus Kontext.',
                ],
                'issue_id' => [
                    'type' => 'integer',
                    'description' => 'ID des Issues (ERFORDERLICH).',
                ],
                'operation' => [
                    'type' => 'string',
                    'enum' => ['add', 'update', 'toggle', 'remove', 'reorder'],
                    'description' => 'ERFORDERLICH: Operation. add=neues Item, update=Text aendern, toggle=done umschalten, remove=entfernen, reorder=Reihenfolge aendern.',
                ],
                'text' => [
                    'type' => 'string',
                    'description' => 'Text fuer add/update.',
                ],
                'index' => [
                    'type' => 'integer',
                    'description' => 'Index (0-basiert) fuer update/toggle/remove.',
                ],
                'order' => [
                    'type' => 'array',
                    'items' => ['type' => 'integer'],
                    'description' => 'Neue Reihenfolge als Array von Indizes fuer reorder.',
                ],
            ],
            'required' => ['issue_id', 'operation'],
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

            $found = $this->validateAndFindModel($arguments, $context, 'issue_id', DevIssue::class, 'NOT_FOUND', 'Issue nicht gefunden.');
            if ($found['error']) {
                return $found['error'];
            }
            $issue = $found['model'];

            if ((int) $issue->team_id !== $teamId) {
                return ToolResult::error('ACCESS_DENIED', 'Kein Zugriff auf dieses Issue.');
            }

            $operation = $arguments['operation'] ?? null;
            $criteria = $issue->acceptance_criteria ?? [];

            switch ($operation) {
                case 'add':
                    $text = trim($arguments['text'] ?? '');
                    if ($text === '') {
                        return ToolResult::error('VALIDATION_ERROR', 'text ist erforderlich fuer add.');
                    }
                    $criteria[] = ['text' => $text, 'done' => false];
                    break;

                case 'update':
                    $index = $arguments['index'] ?? null;
                    $text = trim($arguments['text'] ?? '');
                    if ($index === null || !isset($criteria[$index])) {
                        return ToolResult::error('VALIDATION_ERROR', 'Ungueltiger index.');
                    }
                    if ($text === '') {
                        return ToolResult::error('VALIDATION_ERROR', 'text ist erforderlich fuer update.');
                    }
                    $criteria[$index]['text'] = $text;
                    break;

                case 'toggle':
                    $index = $arguments['index'] ?? null;
                    if ($index === null || !isset($criteria[$index])) {
                        return ToolResult::error('VALIDATION_ERROR', 'Ungueltiger index.');
                    }
                    $criteria[$index]['done'] = !($criteria[$index]['done'] ?? false);
                    break;

                case 'remove':
                    $index = $arguments['index'] ?? null;
                    if ($index === null || !isset($criteria[$index])) {
                        return ToolResult::error('VALIDATION_ERROR', 'Ungueltiger index.');
                    }
                    array_splice($criteria, $index, 1);
                    break;

                case 'reorder':
                    $order = $arguments['order'] ?? null;
                    if (!is_array($order) || count($order) !== count($criteria)) {
                        return ToolResult::error('VALIDATION_ERROR', 'order muss ein Array mit allen Indizes sein.');
                    }
                    $reordered = [];
                    foreach ($order as $i) {
                        if (!isset($criteria[$i])) {
                            return ToolResult::error('VALIDATION_ERROR', "Index {$i} existiert nicht.");
                        }
                        $reordered[] = $criteria[$i];
                    }
                    $criteria = $reordered;
                    break;

                default:
                    return ToolResult::error('VALIDATION_ERROR', 'Unbekannte Operation: ' . $operation);
            }

            $issue->update(['acceptance_criteria' => array_values($criteria)]);
            $issue->refresh();

            $total = count($criteria);
            $done = count(array_filter($criteria, fn ($c) => $c['done'] ?? false));

            return ToolResult::success([
                'issue_id' => $issue->id,
                'acceptance_criteria' => $issue->acceptance_criteria,
                'dod_progress' => [
                    'done' => $done,
                    'total' => $total,
                    'percent' => $total > 0 ? round($done / $total * 100) : 0,
                ],
                'message' => "DoD-Operation '{$operation}' erfolgreich. {$done}/{$total} erledigt.",
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler bei DoD-Operation: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => false,
            'category' => 'action',
            'tags' => ['dev', 'issues', 'dod'],
            'risk_level' => 'write',
            'requires_auth' => true,
            'requires_team' => true,
            'idempotent' => false,
        ];
    }
}
