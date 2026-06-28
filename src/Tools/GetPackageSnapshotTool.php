<?php

namespace Platform\Dev\Tools;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Dev\Models\DevPackage;
use Platform\Dev\Models\DevPackageSnapshot;
use Platform\Dev\Services\DevPackageSnapshotService;

class GetPackageSnapshotTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'dev.package_snapshots.GET';
    }

    public function getDescription(): string
    {
        return 'GET /package-snapshots - Holt den juengsten Snapshot eines Dev-Packages (oder einen historischen via taken_on) inkl. Health-Ampel, Achsen (bug_pressure/feature_velocity/production_health/doc_coverage), Story-Points, Top-Issues, Top-Errors, Workload, Boards. Optional fresh=true erzwingt einen neuen Snapshot.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'package_id' => ['type' => 'integer', 'description' => 'Package-ID (ERFORDERLICH).'],
                'taken_on' => ['type' => 'string', 'description' => 'Optional: historischer Stichtag YYYY-MM-DD.'],
                'fresh' => ['type' => 'boolean', 'description' => 'Optional: wenn true wird ein neuer Snapshot erstellt.'],
            ],
            'required' => ['package_id'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            if (!$context->user) {
                return ToolResult::error('AUTH_ERROR', 'Kein User.');
            }
            if (empty($arguments['package_id'])) {
                return ToolResult::error('VALIDATION_ERROR', 'package_id erforderlich.');
            }

            $package = DevPackage::find($arguments['package_id']);
            if (!$package) {
                return ToolResult::error('PACKAGE_NOT_FOUND', 'Dev-Package nicht gefunden.');
            }

            if (!empty($arguments['fresh'])) {
                $snapshot = app(DevPackageSnapshotService::class)->snapshot($package, 'manual');
            } else {
                $query = DevPackageSnapshot::where('dev_package_id', $package->id);
                if (!empty($arguments['taken_on'])) {
                    $query->whereDate('taken_on', $arguments['taken_on']);
                }
                $snapshot = $query->orderByDesc('taken_on')->first();
            }

            if (!$snapshot) {
                return ToolResult::success([
                    'package_id' => $package->id,
                    'snapshot' => null,
                    'message' => 'Noch kein Snapshot. Setze fresh=true um den ersten zu erstellen.',
                ]);
            }

            $snapshot->load(['topIssues', 'topErrors', 'people', 'boards']);

            return ToolResult::success([
                'package_id' => $package->id,
                'package_name' => $package->name,
                'snapshot' => self::serializeSnapshot($snapshot),
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public static function serializeSnapshot(DevPackageSnapshot $s): array
    {
        return [
            'id' => $s->id,
            'uuid' => $s->uuid,
            'taken_at' => $s->taken_at?->toIso8601String(),
            'taken_on' => $s->taken_on?->toDateString(),
            'trigger' => $s->trigger,
            'frozen_context' => $s->frozen_context,
            'issues' => [
                'total' => $s->issues_total, 'open' => $s->issues_open, 'done' => $s->issues_done,
                'overdue' => $s->issues_overdue, 'high_priority_open' => $s->issues_high_priority_open,
            ],
            'bugs' => [
                'total' => $s->bugs_total, 'open' => $s->bugs_open, 'done' => $s->bugs_done,
            ],
            'features' => [
                'total' => $s->features_total, 'open' => $s->features_open, 'done' => $s->features_done,
            ],
            'story_points' => [
                'total' => $s->story_points_total, 'open' => $s->story_points_open, 'done' => $s->story_points_done,
            ],
            'errors' => [
                'open' => $s->errors_open, 'acknowledged' => $s->errors_acknowledged,
                'total_hits' => $s->errors_total_hits, 'seen_today' => $s->errors_seen_today,
                'latest_seen_at' => $s->latest_error_seen_at?->toIso8601String(),
            ],
            'boards' => [
                'count' => $s->boards_count,
                'has_bug_board' => (bool) $s->has_bug_board,
                'has_feature_board' => (bool) $s->has_feature_board,
            ],
            'docs' => [
                'count' => $s->doc_pages_count, 'stale' => $s->doc_pages_stale,
                'published' => $s->doc_pages_published,
            ],
            'workload' => [
                'active_users' => $s->active_users_count,
                'unassigned' => $s->unassigned_open_issues,
            ],
            'health' => [
                'score' => $s->health_score, 'color' => $s->health_color,
                'worst_axis' => $s->worst_axis, 'axis_scores' => $s->axis_scores,
            ],
            'confidence' => ['score' => $s->confidence_score, 'reason' => $s->confidence_reason],
            'movement' => [
                'prev_snapshot_id' => $s->prev_snapshot_id,
                'delta_health_score' => $s->delta_health_score,
                'last_movement_at' => $s->last_movement_at?->toIso8601String(),
            ],
            'top_issues' => $s->topIssues->map(fn ($x) => [
                'issue_id' => $x->issue_id, 'issue_uuid' => $x->issue_uuid, 'title' => $x->issue_title,
                'board_type' => $x->board_type, 'board_name' => $x->board_name,
                'priority' => $x->priority, 'story_points' => $x->story_points,
                'due_date' => $x->due_date?->toDateString(), 'is_overdue' => $x->is_overdue, 'is_done' => $x->is_done,
                'user_in_charge' => $x->user_in_charge_name, 'rank' => $x->rank,
            ])->all(),
            'top_errors' => $s->topErrors->map(fn ($x) => [
                'error_occurrence_id' => $x->error_occurrence_id,
                'exception_class' => $x->exception_class, 'message_excerpt' => $x->message_excerpt,
                'occurrence_count' => $x->occurrence_count, 'status' => $x->status,
                'first_seen_at' => $x->first_seen_at?->toIso8601String(),
                'last_seen_at' => $x->last_seen_at?->toIso8601String(),
                'rank' => $x->rank,
            ])->all(),
            'people' => $s->people->map(fn ($x) => [
                'user_id' => $x->user_id, 'name' => $x->user_name,
                'open_issues' => $x->open_issues, 'done_issues' => $x->done_issues,
                'open_bugs' => $x->open_bugs, 'open_features' => $x->open_features,
                'overdue_issues' => $x->overdue_issues,
                'sp_open' => $x->sp_open, 'sp_done' => $x->sp_done,
            ])->all(),
            'boards_breakdown' => $s->boards->map(fn ($x) => [
                'board_id' => $x->board_id, 'name' => $x->board_name, 'type' => $x->board_type,
                'open' => $x->issues_open, 'done' => $x->issues_done, 'total' => $x->issues_total,
            ])->all(),
        ];
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'query',
            'tags' => ['dev', 'package', 'snapshot', 'health'],
            'read_only' => false,
            'requires_auth' => true, 'requires_team' => false,
            'risk_level' => 'safe', 'idempotent' => true,
        ];
    }
}
