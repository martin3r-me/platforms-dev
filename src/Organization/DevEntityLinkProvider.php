<?php

namespace Platform\Dev\Organization;

use Illuminate\Database\Eloquent\Builder;
use Platform\Dev\Models\DevIssue;
use Platform\Organization\Contracts\EntityLinkProvider;

class DevEntityLinkProvider implements EntityLinkProvider
{
    public function morphAliases(): array
    {
        return ['dev_package', 'dev_issue'];
    }

    public function linkTypeConfig(): array
    {
        return [
            'dev_package' => ['label' => 'Packages', 'singular' => 'Package', 'icon' => 'cube', 'route' => null],
            'dev_issue'   => ['label' => 'Issues', 'singular' => 'Issue', 'icon' => 'ticket', 'route' => null],
        ];
    }

    public function applyEagerLoading(Builder $query, string $morphAlias, string $fqcn): void
    {
        match ($morphAlias) {
            'dev_package' => $query->withCount(['boards', 'discussions']),
            'dev_issue'   => $query->with(['board:id,name', 'slot:id,name']),
            default       => null,
        };
    }

    public function extractMetadata(string $morphAlias, mixed $model): array
    {
        return match ($morphAlias) {
            'dev_package' => [
                'status'           => $model->status ?? null,
                'boards_count'     => (int) ($model->boards_count ?? 0),
                'discussions_count' => (int) ($model->discussions_count ?? 0),
            ],
            'dev_issue' => [
                'status'   => $model->status ?? null,
                'priority' => $model->priority instanceof \BackedEnum ? $model->priority->value : $model->priority,
                'is_done'  => (bool) $model->is_done,
                'board'    => $model->board?->name,
                'slot'     => $model->slot?->name ?? 'Backlog',
            ],
            default => [],
        };
    }

    public function metadataDisplayRules(): array
    {
        return [
            'dev_package' => [
                ['field' => 'status', 'format' => 'badge'],
                ['field' => 'boards_count', 'format' => 'count', 'suffix' => 'Boards'],
            ],
            'dev_issue' => [
                ['field' => 'status', 'format' => 'badge'],
                ['field' => 'priority', 'format' => 'badge'],
                ['field' => 'is_done', 'format' => 'boolean_done'],
            ],
        ];
    }

    public function timeTrackableCascades(): array
    {
        return [];
    }

    public function activityChildren(string $morphAlias, array $linkableIds): array
    {
        return [];
    }

    public function metrics(string $morphAlias, array $linksByEntity): array
    {
        if ($morphAlias !== 'dev_issue') {
            return [];
        }

        $allIds = [];
        foreach ($linksByEntity as $ids) {
            $allIds = array_merge($allIds, $ids);
        }
        $allIds = array_values(array_unique($allIds));

        if (empty($allIds)) {
            return [];
        }

        $issues = DevIssue::whereIn('id', $allIds)
            ->select('id', 'is_done', 'story_points')
            ->get()
            ->keyBy('id');

        $result = [];
        foreach ($linksByEntity as $entityId => $ids) {
            $total = 0;
            $done = 0;
            $spTotal = 0;
            $spDone = 0;

            foreach ($ids as $id) {
                $issue = $issues[$id] ?? null;
                if (!$issue) {
                    continue;
                }
                $total++;
                $sp = (int) ($issue->story_points ?? 0);
                $spTotal += $sp;

                if ($issue->is_done) {
                    $done++;
                    $spDone += $sp;
                }
            }

            $result[$entityId] = [
                'dev_issues_total' => $total,
                'dev_issues_done' => $done,
                'dev_story_points_total' => $spTotal,
                'dev_story_points_done' => $spDone,
            ];
        }

        return $result;
    }
}
