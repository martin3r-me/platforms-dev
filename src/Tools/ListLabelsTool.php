<?php

namespace Platform\Dev\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Dev\Models\DevIssue;
use Platform\Dev\Tools\Concerns\ResolvesDevTeam;

class ListLabelsTool implements ToolContract, ToolMetadataContract
{
    use ResolvesDevTeam;

    public function getName(): string
    {
        return 'dev.labels.GET';
    }

    public function getDescription(): string
    {
        return 'GET /dev/labels - Listet alle verwendeten Labels im Team. Optional: package_id, board_id zum Einschraenken.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'team_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Team-ID. Default: aktuelles Team.',
                ],
                'package_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Nur Labels aus Issues dieses Packages.',
                ],
                'board_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Nur Labels aus Issues dieses Boards.',
                ],
            ],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $resolved = $this->resolveTeam($arguments, $context);
            if ($resolved['error']) {
                return $resolved['error'];
            }
            $teamId = (int) $resolved['team_id'];

            $query = DevIssue::where('team_id', $teamId)
                ->whereNotNull('labels');

            if (!empty($arguments['board_id'])) {
                $query->where('dev_board_id', (int) $arguments['board_id']);
            } elseif (!empty($arguments['package_id'])) {
                $query->whereHas('board', function ($q) use ($arguments) {
                    $q->where('dev_package_id', (int) $arguments['package_id']);
                });
            }

            $allLabels = $query->pluck('labels')
                ->flatten()
                ->filter()
                ->countBy()
                ->sortDesc();

            return ToolResult::success([
                'labels' => $allLabels->map(fn ($count, $label) => [
                    'label' => $label,
                    'count' => $count,
                ])->values()->toArray(),
                'total_unique' => $allLabels->count(),
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden der Labels: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'read',
            'tags' => ['dev', 'labels', 'list'],
            'read_only' => true,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'safe',
            'idempotent' => true,
        ];
    }
}
