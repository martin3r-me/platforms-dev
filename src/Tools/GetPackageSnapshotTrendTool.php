<?php

namespace Platform\Dev\Tools;

use Carbon\Carbon;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Dev\Models\DevPackage;
use Platform\Dev\Models\DevPackageSnapshot;

class GetPackageSnapshotTrendTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'dev.package_snapshots.trend';
    }

    public function getDescription(): string
    {
        return 'GET /package-snapshots/trend - Zeitreihe Health-Score + Achsen + Issue-Counts + Errors eines Dev-Packages. Default: letzte 30 Tage.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'package_id' => ['type' => 'integer', 'description' => 'Package-ID (ERFORDERLICH).'],
                'days' => ['type' => 'integer', 'description' => 'Optional: letzte N Tage (1..365). Default 30.'],
                'from' => ['type' => 'string', 'description' => 'Optional: Startdatum YYYY-MM-DD.'],
                'to' => ['type' => 'string', 'description' => 'Optional: Enddatum YYYY-MM-DD.'],
            ],
            'required' => ['package_id'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            if (!$context->user) return ToolResult::error('AUTH_ERROR', 'Kein User.');
            if (empty($arguments['package_id'])) return ToolResult::error('VALIDATION_ERROR', 'package_id erforderlich.');

            $package = DevPackage::find($arguments['package_id']);
            if (!$package) return ToolResult::error('PACKAGE_NOT_FOUND', 'Package nicht gefunden.');

            $to = !empty($arguments['to']) ? Carbon::parse($arguments['to']) : now();
            if (!empty($arguments['from'])) {
                $from = Carbon::parse($arguments['from']);
            } else {
                $days = max(1, min(365, (int) ($arguments['days'] ?? 30)));
                $from = $to->copy()->subDays($days - 1);
            }

            $snapshots = DevPackageSnapshot::where('dev_package_id', $package->id)
                ->whereBetween('taken_on', [$from->toDateString(), $to->toDateString()])
                ->orderBy('taken_on')
                ->get();

            $points = $snapshots->map(fn (DevPackageSnapshot $s) => [
                'taken_on' => $s->taken_on?->toDateString(),
                'health_score' => $s->health_score, 'health_color' => $s->health_color,
                'worst_axis' => $s->worst_axis, 'axis_scores' => $s->axis_scores,
                'confidence_score' => $s->confidence_score,
                'issues_open' => $s->issues_open, 'issues_done' => $s->issues_done,
                'bugs_open' => $s->bugs_open, 'features_open' => $s->features_open,
                'errors_open' => $s->errors_open, 'errors_total_hits' => $s->errors_total_hits,
                'story_points_open' => $s->story_points_open, 'story_points_done' => $s->story_points_done,
            ])->all();

            return ToolResult::success([
                'package_id' => $package->id,
                'package_name' => $package->name,
                'from' => $from->toDateString(), 'to' => $to->toDateString(),
                'count' => count($points),
                'points' => $points,
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'query',
            'tags' => ['dev', 'package', 'snapshot', 'trend', 'timeseries'],
            'read_only' => true,
            'requires_auth' => true, 'requires_team' => false,
            'risk_level' => 'safe', 'idempotent' => true,
        ];
    }
}
