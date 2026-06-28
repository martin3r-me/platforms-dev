<?php

namespace Platform\Dev\Tools;

use Illuminate\Support\Facades\DB;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Dev\Models\DevPackageSnapshot;

class ListPackageSnapshotsSummaryTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'dev.package_snapshots.summary';
    }

    public function getDescription(): string
    {
        return 'GET /package-snapshots/summary - Aggregat-Sicht ueber die juengsten Dev-Package-Snapshots eines Teams. Health-Verteilung, Worst-Axis-Distribution, Production-Errors-Total, Top-N rote Packages, Top-N daten-arme.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'team_id' => ['type' => 'integer'],
                'top_n' => ['type' => 'integer', 'description' => 'Default 5, max 20.'],
            ],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            if (!$context->user) return ToolResult::error('AUTH_ERROR', 'Kein User.');

            $teamId = (int) ($arguments['team_id'] ?? ($context->team?->id ?? 0));
            if ($teamId <= 0) return ToolResult::error('VALIDATION_ERROR', 'Kein Team.');

            $topN = max(1, min(20, (int) ($arguments['top_n'] ?? 5)));

            $latestIds = DB::table('dev_package_snapshots as a')
                ->where('a.team_id', $teamId)
                ->whereRaw('a.taken_on = (
                    SELECT MAX(b.taken_on) FROM dev_package_snapshots b
                    WHERE b.dev_package_id = a.dev_package_id
                )')
                ->pluck('a.id');

            $latest = DevPackageSnapshot::with('package:id,name')
                ->whereIn('id', $latestIds)
                ->get();

            $total = $latest->count();
            if ($total === 0) {
                return ToolResult::success([
                    'team_id' => $teamId,
                    'total_packages' => 0,
                    'message' => 'Noch keine Package-Snapshots vorhanden.',
                ]);
            }

            $byColor = ['green' => 0, 'yellow' => 0, 'red' => 0, 'gray' => 0];
            $byAxis = ['bug_pressure' => 0, 'feature_velocity' => 0, 'production_health' => 0, 'doc_coverage' => 0];
            $byConfidence = ['high_75_100' => 0, 'medium_50_74' => 0, 'low_25_49' => 0, 'none_0_24' => 0];
            $errorsTotal = 0; $errorsHits = 0;

            foreach ($latest as $s) {
                $byColor[$s->health_color ?: 'gray'] = ($byColor[$s->health_color ?: 'gray'] ?? 0) + 1;
                if ($s->worst_axis && isset($byAxis[$s->worst_axis])) $byAxis[$s->worst_axis]++;
                $c = (int) $s->confidence_score;
                if ($c >= 75) $byConfidence['high_75_100']++;
                elseif ($c >= 50) $byConfidence['medium_50_74']++;
                elseif ($c >= 25) $byConfidence['low_25_49']++;
                else $byConfidence['none_0_24']++;
                $errorsTotal += (int) $s->errors_open + (int) $s->errors_acknowledged;
                $errorsHits += (int) $s->errors_total_hits;
            }

            $colorRank = ['red' => 0, 'yellow' => 1, 'green' => 2, 'gray' => 3];
            $worstHealth = $latest
                ->sort(function ($a, $b) use ($colorRank) {
                    $ra = $colorRank[$a->health_color ?? 'gray'] ?? 9;
                    $rb = $colorRank[$b->health_color ?? 'gray'] ?? 9;
                    if ($ra !== $rb) return $ra <=> $rb;
                    return (int) ($a->health_score ?? 999) <=> (int) ($b->health_score ?? 999);
                })
                ->take($topN)
                ->map(fn ($s) => $this->compact($s))
                ->values()->all();

            $lowConfidence = $latest
                ->sortBy('confidence_score')
                ->take($topN)
                ->map(fn ($s) => $this->compact($s))
                ->values()->all();

            return ToolResult::success([
                'team_id' => $teamId,
                'taken_on_range' => [
                    'from' => $latest->min('taken_on')?->toDateString(),
                    'to' => $latest->max('taken_on')?->toDateString(),
                ],
                'total_packages' => $total,
                'health_distribution' => $byColor,
                'worst_axis_distribution' => $byAxis,
                'confidence_distribution' => $byConfidence,
                'production_errors' => [
                    'total_live' => $errorsTotal,
                    'total_hits' => $errorsHits,
                ],
                'worst_health' => $worstHealth,
                'low_confidence' => $lowConfidence,
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    private function compact(DevPackageSnapshot $s): array
    {
        return [
            'package_id' => $s->dev_package_id,
            'package_name' => $s->package?->name,
            'health_score' => $s->health_score, 'health_color' => $s->health_color,
            'worst_axis' => $s->worst_axis,
            'confidence_score' => $s->confidence_score, 'confidence_reason' => $s->confidence_reason,
            'issues_open' => $s->issues_open, 'bugs_open' => $s->bugs_open,
            'features_open' => $s->features_open, 'errors_open' => $s->errors_open,
            'doc_pages_count' => $s->doc_pages_count,
            'taken_on' => $s->taken_on?->toDateString(),
        ];
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'query',
            'tags' => ['dev', 'package', 'snapshot', 'summary', 'aggregate'],
            'read_only' => true,
            'requires_auth' => true, 'requires_team' => false,
            'risk_level' => 'safe', 'idempotent' => true,
        ];
    }
}
