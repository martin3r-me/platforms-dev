<?php

namespace Platform\Dev\Organization;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Platform\Dev\Models\DevDocPage;
use Platform\Dev\Models\DevErrorOccurrence;
use Platform\Dev\Models\DevIssue;
use Platform\Dev\Models\DevPackage;
use Platform\Organization\Contracts\EntityLinkProvider;
use Platform\Organization\Contracts\HasMetricDefinitions;
use Platform\Organization\Contracts\HasPersonMetrics;

class DevEntityLinkProvider implements EntityLinkProvider, HasMetricDefinitions, HasPersonMetrics
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
        return [
            'dev_package' => [DevPackage::class, ['boards.issues']],
            'dev_issue'   => [DevIssue::class, []],
        ];
    }

    public function activityChildren(string $morphAlias, array $linkableIds): array
    {
        return [];
    }

    public function metrics(string $morphAlias, array $linksByEntity): array
    {
        return match ($morphAlias) {
            'dev_issue'   => $this->issueMetrics($linksByEntity),
            'dev_package' => $this->packageMetrics($linksByEntity),
            default       => [],
        };
    }

    protected function issueMetrics(array $linksByEntity): array
    {
        $allIds = array_values(array_unique(array_merge(...array_values($linksByEntity))));

        if (empty($allIds)) {
            return [];
        }

        $issues = DevIssue::whereIn('id', $allIds)
            ->select('id', 'is_done', 'story_points', 'created_at', 'done_at', 'agent_completed_at')
            ->get()
            ->keyBy('id');

        $now = now();
        $window7d = $now->copy()->subDays(7);

        $result = [];
        foreach ($linksByEntity as $entityId => $ids) {
            $total = 0;
            $done = 0;
            $spTotal = 0;
            $spDone = 0;
            $issuesClosed7d = 0;
            $agentCompleted7d = 0;
            $ageSumDays = 0.0;
            $openCount = 0;

            foreach ($ids as $id) {
                $issue = $issues[$id] ?? null;
                if (! $issue) {
                    continue;
                }
                $total++;
                $sp = $issue->story_points?->points() ?? 0;
                $spTotal += $sp;

                if ($issue->is_done) {
                    $done++;
                    $spDone += $sp;
                    if ($issue->done_at && $issue->done_at->greaterThanOrEqualTo($window7d)) {
                        $issuesClosed7d++;
                    }
                } else {
                    $openCount++;
                    if ($issue->created_at) {
                        $ageSumDays += (float) $issue->created_at->diffInDays($now, false);
                    }
                }

                if ($issue->agent_completed_at && $issue->agent_completed_at->greaterThanOrEqualTo($window7d)) {
                    $agentCompleted7d++;
                }
            }

            $backlogAge = $openCount > 0 ? round($ageSumDays / $openCount, 1) : 0.0;

            $result[$entityId] = [
                'items_total' => $total,
                'items_done' => $done,
                'story_points_total' => $spTotal,
                'story_points_done' => $spDone,
                'dev_issues_closed_7d' => $issuesClosed7d,
                'dev_agent_completed_7d' => $agentCompleted7d,
                'dev_backlog_age_days' => $backlogAge,
            ];
        }

        return $result;
    }

    protected function packageMetrics(array $linksByEntity): array
    {
        $allPackageIds = array_values(array_unique(array_merge(...array_values($linksByEntity))));

        if (empty($allPackageIds)) {
            return [];
        }

        // Issues by board type (bug vs feature), grouped by package
        $issueCounts = DevIssue::join('dev_boards', 'dev_issues.dev_board_id', '=', 'dev_boards.id')
            ->whereIn('dev_boards.dev_package_id', $allPackageIds)
            ->whereNull('dev_boards.deleted_at')
            ->select('dev_boards.dev_package_id', 'dev_boards.type')
            ->selectRaw('SUM(CASE WHEN dev_issues.is_done = 0 THEN 1 ELSE 0 END) as open_count')
            ->selectRaw('SUM(CASE WHEN dev_issues.is_done = 1 THEN 1 ELSE 0 END) as done_count')
            ->groupBy('dev_boards.dev_package_id', 'dev_boards.type')
            ->get();

        // Index: packageId => [type => [open, done]]
        $issuesByPackage = [];
        foreach ($issueCounts as $row) {
            $issuesByPackage[$row->dev_package_id][$row->type] = [
                'open' => (int) $row->open_count,
                'done' => (int) $row->done_count,
            ];
        }

        // Open errors per package
        $errorCounts = DevErrorOccurrence::whereIn('dev_package_id', $allPackageIds)
            ->whereIn('status', [DevErrorOccurrence::STATUS_OPEN, DevErrorOccurrence::STATUS_ACKNOWLEDGED])
            ->select('dev_package_id')
            ->selectRaw('COUNT(*) as error_count')
            ->selectRaw('SUM(occurrence_count) as total_occurrences')
            ->groupBy('dev_package_id')
            ->get()
            ->keyBy('dev_package_id');

        // Neu / erhoehte Errors im 7d-Fenster (Regression-Signal): first_seen_at im Fenster.
        $window7d = now()->subDays(7);
        $errorsNew7d = DB::table('dev_error_occurrences')
            ->whereIn('dev_package_id', $allPackageIds)
            ->where('first_seen_at', '>=', $window7d)
            ->select('dev_package_id')
            ->selectRaw('COUNT(*) as new_errors')
            ->selectRaw('SUM(occurrence_count) as new_hits')
            ->groupBy('dev_package_id')
            ->get()
            ->keyBy('dev_package_id');

        // Aktive Packages (status='active')
        $activePackageIds = DB::table('dev_packages')
            ->whereIn('id', $allPackageIds)
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->pluck('id')
            ->flip()
            ->all();

        // Published doc pages per package
        $docCounts = DevDocPage::whereIn('dev_package_id', $allPackageIds)
            ->where('status', 'published')
            ->whereNull('deleted_at')
            ->selectRaw('dev_package_id, COUNT(*) as page_count')
            ->groupBy('dev_package_id')
            ->get()
            ->keyBy('dev_package_id');

        // Assemble per entity
        $result = [];
        foreach ($linksByEntity as $entityId => $packageIds) {
            $bugsOpen = 0;
            $bugsClosed = 0;
            $featuresOpen = 0;
            $featuresDone = 0;
            $errorsOpen = 0;
            $errorsOccurrences = 0;
            $errorsNew7dCount = 0;
            $errorsHits7d = 0;
            $docPages = 0;
            $packagesActive = 0;

            foreach ($packageIds as $pid) {
                $bugs = $issuesByPackage[$pid]['bug'] ?? ['open' => 0, 'done' => 0];
                $bugsOpen += $bugs['open'];
                $bugsClosed += $bugs['done'];

                $features = $issuesByPackage[$pid]['feature'] ?? ['open' => 0, 'done' => 0];
                $featuresOpen += $features['open'];
                $featuresDone += $features['done'];

                $err = $errorCounts[$pid] ?? null;
                $errorsOpen += $err ? (int) $err->error_count : 0;
                $errorsOccurrences += $err ? (int) $err->total_occurrences : 0;

                $errNew = $errorsNew7d[$pid] ?? null;
                $errorsNew7dCount += $errNew ? (int) $errNew->new_errors : 0;
                $errorsHits7d += $errNew ? (int) $errNew->new_hits : 0;

                $doc = $docCounts[$pid] ?? null;
                $docPages += $doc ? (int) $doc->page_count : 0;

                if (isset($activePackageIds[$pid])) {
                    $packagesActive++;
                }
            }

            $result[$entityId] = [
                'dev_bugs_open'         => $bugsOpen,
                'dev_bugs_closed'       => $bugsClosed,
                'dev_features_open'     => $featuresOpen,
                'dev_features_done'     => $featuresDone,
                'dev_errors_open'       => $errorsOpen,
                'dev_errors_hits'       => $errorsOccurrences,
                'dev_errors_new_7d'     => $errorsNew7dCount,
                'dev_errors_hits_7d'    => $errorsHits7d,
                'dev_doc_pages'         => $docPages,
                'dev_packages_active'   => $packagesActive,
            ];
        }

        return $result;
    }

    public function metricDefinitions(): array
    {
        return [
            // Generic work metrics (from dev_issue links) — basis explizit gesetzt.
            'items_total'        => ['label' => 'Items (gesamt)', 'group' => 'work', 'direction' => 'neutral', 'unit' => 'count', 'dimension' => 'complexity', 'type' => 'stock', 'aggregation_mode' => 'rolled_up', 'basis' => 'stichtag'],
            'items_done'         => ['label' => 'Items (erledigt)', 'group' => 'work', 'direction' => 'up', 'unit' => 'count', 'pair' => 'items_total', 'dimension' => 'throughput', 'type' => 'flow', 'aggregation_mode' => 'rolled_up', 'basis' => 'cumulative_since_start'],
            'story_points_total' => ['label' => 'Story Points (gesamt)', 'group' => 'work', 'direction' => 'neutral', 'unit' => 'points', 'dimension' => 'complexity', 'type' => 'stock', 'aggregation_mode' => 'rolled_up', 'basis' => 'stichtag'],
            'story_points_done'  => ['label' => 'Story Points (erledigt)', 'group' => 'work', 'direction' => 'up', 'unit' => 'points', 'pair' => 'story_points_total', 'dimension' => 'throughput', 'type' => 'flow', 'aggregation_mode' => 'rolled_up', 'basis' => 'cumulative_since_start'],

            // Dev-specific metrics (from dev_package links) — basis explizit gesetzt.
            'dev_bugs_open'       => ['label' => 'Bugs (offen)', 'group' => 'dev', 'direction' => 'down', 'unit' => 'count', 'dimension' => 'quality', 'type' => 'stock', 'aggregation_mode' => 'rolled_up', 'basis' => 'stichtag'],
            'dev_bugs_closed'     => ['label' => 'Bugs (geschlossen)', 'group' => 'dev', 'direction' => 'up', 'unit' => 'count', 'pair' => 'dev_bugs_open', 'dimension' => 'throughput', 'type' => 'flow', 'aggregation_mode' => 'rolled_up', 'basis' => 'cumulative_since_start'],
            'dev_features_open'   => ['label' => 'Features (offen)', 'group' => 'dev', 'direction' => 'neutral', 'unit' => 'count', 'dimension' => 'complexity', 'type' => 'stock', 'aggregation_mode' => 'rolled_up', 'basis' => 'stichtag'],
            'dev_features_done'   => ['label' => 'Features (erledigt)', 'group' => 'dev', 'direction' => 'up', 'unit' => 'count', 'pair' => 'dev_features_open', 'dimension' => 'throughput', 'type' => 'flow', 'aggregation_mode' => 'rolled_up', 'basis' => 'cumulative_since_start'],
            'dev_errors_open'     => ['label' => 'Errors (offen)', 'group' => 'dev', 'direction' => 'down', 'unit' => 'count', 'dimension' => 'quality', 'type' => 'stock', 'aggregation_mode' => 'rolled_up', 'basis' => 'stichtag'],
            'dev_errors_hits'     => ['label' => 'Error-Aufkommen', 'group' => 'dev', 'direction' => 'down', 'unit' => 'count', 'dimension' => 'quality', 'type' => 'flow', 'aggregation_mode' => 'rolled_up', 'basis' => 'cumulative_since_start'],
            'dev_doc_pages'       => ['label' => 'Dokumentation', 'group' => 'dev', 'direction' => 'up', 'unit' => 'count', 'dimension' => 'org_capital', 'type' => 'stock', 'aggregation_mode' => 'rolled_up', 'basis' => 'stichtag'],

            // Neu — Delivery-Puls (Wochen-Windows).
            'dev_issues_closed_7d'   => ['label' => 'Issues geschlossen (7 Tage)', 'group' => 'dev', 'direction' => 'up', 'unit' => 'count', 'dimension' => 'throughput', 'type' => 'flow', 'aggregation_mode' => 'rolled_up', 'basis' => 'window_7d'],
            'dev_agent_completed_7d' => ['label' => 'Von Agenten erledigt (7 Tage)', 'group' => 'dev', 'direction' => 'up', 'unit' => 'count', 'dimension' => 'throughput', 'type' => 'flow', 'aggregation_mode' => 'rolled_up', 'basis' => 'window_7d'],
            'dev_errors_new_7d'      => ['label' => 'Neue Errors (7 Tage)', 'group' => 'dev', 'direction' => 'down', 'unit' => 'count', 'dimension' => 'quality', 'type' => 'flow', 'aggregation_mode' => 'rolled_up', 'basis' => 'window_7d'],
            'dev_errors_hits_7d'     => ['label' => 'Error-Aufkommen (7 Tage)', 'group' => 'dev', 'direction' => 'down', 'unit' => 'count', 'dimension' => 'quality', 'type' => 'flow', 'aggregation_mode' => 'rolled_up', 'basis' => 'window_7d'],
            'dev_backlog_age_days'   => ['label' => 'Ø-Alter offener Issues (Tage)', 'group' => 'dev', 'direction' => 'down', 'unit' => 'days', 'dimension' => 'energy', 'type' => 'modulator', 'aggregation_mode' => 'rolled_up', 'roll_up_function' => 'avg', 'basis' => 'modulator_factor'],
            'dev_packages_active'    => ['label' => 'Aktive Packages', 'group' => 'dev', 'direction' => 'up', 'unit' => 'count', 'dimension' => 'org_capital', 'type' => 'stock', 'aggregation_mode' => 'rolled_up', 'basis' => 'stichtag'],
        ];
    }

    public function personMetrics(array $userIds, int $teamId): array
    {
        if (empty($userIds)) {
            return [];
        }

        $rows = DevIssue::whereIn('user_in_charge_id', $userIds)
            ->where('team_id', $teamId)
            ->select(
                'user_in_charge_id',
                DB::raw('SUM(CASE WHEN is_done = 0 THEN 1 ELSE 0 END) as active_items'),
                DB::raw('SUM(CASE WHEN is_done = 1 THEN 1 ELSE 0 END) as completed_items'),
                DB::raw('SUM(CASE WHEN is_done = 0 THEN COALESCE(story_points, 0) ELSE 0 END) as story_points_total'),
                DB::raw('SUM(CASE WHEN is_done = 1 THEN COALESCE(story_points, 0) ELSE 0 END) as story_points_done'),
            )
            ->groupBy('user_in_charge_id')
            ->get();

        $result = [];
        foreach ($rows as $row) {
            $result[$row->user_in_charge_id] = [
                'active_items' => (int) $row->active_items,
                'completed_items' => (int) $row->completed_items,
                'story_points_total' => (int) $row->story_points_total,
                'story_points_done' => (int) $row->story_points_done,
            ];
        }

        return $result;
    }

    public function personMetricDefinitions(): array
    {
        return [
            'active_items'       => ['label' => 'Aktive Items', 'group' => 'persons', 'direction' => 'neutral', 'unit' => 'count'],
            'completed_items'    => ['label' => 'Erledigte Items', 'group' => 'persons', 'direction' => 'up', 'unit' => 'count'],
            'story_points_total' => ['label' => 'Story Points gesamt', 'group' => 'persons', 'direction' => 'neutral', 'unit' => 'points'],
            'story_points_done'  => ['label' => 'Story Points erledigt', 'group' => 'persons', 'direction' => 'up', 'unit' => 'points'],
        ];
    }
}
