<?php

namespace Platform\Dev\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Platform\Core\Health\Services\ConfidenceCalculator;
use Platform\Core\Health\Services\HealthCompositor;
use Platform\Core\Models\User;
use Platform\Dev\Enums\BoardType;
use Platform\Dev\Enums\IssuePriority;
use Platform\Dev\Models\DevBoard;
use Platform\Dev\Models\DevErrorOccurrence;
use Platform\Dev\Models\DevIssue;
use Platform\Dev\Models\DevPackage;
use Platform\Dev\Models\DevPackageSnapshot;

/**
 * Erstellt einen Tages-Snapshot fuer ein DevPackage.
 *
 * Achsen (Gewichte 30/30/25/15):
 *   - bug_pressure      : offene Bugs (Issues auf bug-Type-Boards), High-Prio, Alter
 *   - feature_velocity  : Done-Rate auf feature-Type-Boards + ueberfaellige Features
 *   - production_health : DevErrorOccurrences open/acknowledged + Hit-Rate + heute neu
 *   - doc_coverage      : Anzahl Doc-Pages + Stale-Anteil
 *
 * Confidence-Datenebenen:
 *   - has_bug_board, has_feature_board, has_issues, has_docs
 */
class DevPackageSnapshotService
{
    public function __construct(
        protected HealthCompositor $compositor,
        protected ConfidenceCalculator $confidence,
    ) {}

    public function snapshot(DevPackage $package, string $trigger = 'cron'): DevPackageSnapshot
    {
        return DB::transaction(function () use ($package, $trigger) {
            $now = now();
            $today = $now->toDateString();

            $package->loadMissing([
                'boards.issues',
                'docPages',
                'errorOccurrences',
            ]);

            $existing = DevPackageSnapshot::where('dev_package_id', $package->id)
                ->whereDate('taken_on', $today)
                ->first();

            if ($existing) {
                $existing->topIssues()->delete();
                $existing->topErrors()->delete();
                $existing->people()->delete();
                $existing->boards()->delete();
            }

            $payload = $this->computeScalars($package, $now);

            $prev = DevPackageSnapshot::where('dev_package_id', $package->id)
                ->whereDate('taken_on', '<', $today)
                ->orderByDesc('taken_on')
                ->first();

            if ($prev) {
                $payload['prev_snapshot_id'] = $prev->id;
                $payload['delta_health_score'] = $this->safeDelta($payload['health_score'], $prev->health_score);
            }

            $payload['trigger'] = $trigger;
            $payload['taken_at'] = $now;
            $payload['taken_on'] = $today;
            $payload['dev_package_id'] = $package->id;
            $payload['team_id'] = $package->team_id;

            if ($existing) {
                $existing->update($payload);
                $snapshot = $existing->fresh();
            } else {
                $snapshot = DevPackageSnapshot::create($payload);
            }

            $this->writeTopIssues($snapshot, $package);
            $this->writeTopErrors($snapshot, $package);
            $this->writePeople($snapshot, $package);
            $this->writeBoards($snapshot, $package);

            return $snapshot;
        });
    }

    private function safeDelta(?int $current, ?int $prev): ?int
    {
        if ($current === null || $prev === null) return null;
        return $current - $prev;
    }

    private function computeScalars(DevPackage $package, Carbon $now): array
    {
        // Boards nach Type unterteilen
        $bugBoards = $package->boards->where('type', BoardType::Bug);
        $featureBoards = $package->boards->where('type', BoardType::Feature);

        $allIssues = $package->boards->flatMap(fn ($b) => $b->issues);
        $bugIssues = $bugBoards->flatMap(fn ($b) => $b->issues);
        $featureIssues = $featureBoards->flatMap(fn ($b) => $b->issues);

        // Issue-Counts
        $issuesOpen = $allIssues->where('is_done', false);
        $issuesDone = $allIssues->where('is_done', true);
        $issuesOverdue = $issuesOpen->filter(fn ($i) => $i->due_date && $i->due_date->isPast());
        $highPrioOpen = $issuesOpen->filter(fn ($i) => $i->priority === IssuePriority::High);

        $bugsOpen = $bugIssues->where('is_done', false);
        $bugsDone = $bugIssues->where('is_done', true);
        $featuresOpen = $featureIssues->where('is_done', false);
        $featuresDone = $featureIssues->where('is_done', true);

        // Story Points
        $spTotal = $allIssues->sum(fn ($i) => $i->story_points?->points() ?? 0);
        $spOpen = $issuesOpen->sum(fn ($i) => $i->story_points?->points() ?? 0);
        $spDone = $issuesDone->sum(fn ($i) => $i->story_points?->points() ?? 0);

        // Errors
        $errors = $package->errorOccurrences;
        $errorsOpen = $errors->where('status', DevErrorOccurrence::STATUS_OPEN);
        $errorsAck = $errors->where('status', DevErrorOccurrence::STATUS_ACKNOWLEDGED);
        $errorsLive = $errorsOpen->merge($errorsAck);
        $errorsTotalHits = $errorsLive->sum('occurrence_count');
        $errorsSeenToday = $errorsLive->filter(fn ($e) => $e->last_seen_at && $e->last_seen_at->isToday())->count();
        $latestErrorSeen = $errors->max('last_seen_at');

        // Boards
        $boardsCount = $package->boards->count();
        $hasBugBoard = $bugBoards->isNotEmpty();
        $hasFeatureBoard = $featureBoards->isNotEmpty();

        // Docs
        $docs = $package->docPages;
        $docsCount = $docs->count();
        $docsStale = $docs->filter(fn ($d) => $d->updated_at && $d->updated_at->lt($now->copy()->subDays(90)))->count();
        $docsPublished = $docs->filter(fn ($d) => $d->status === 'published')->count();

        // Workload
        $byUser = $issuesOpen->whereNotNull('user_in_charge_id')->groupBy('user_in_charge_id');
        $activeUsers = $byUser->count();
        $unassigned = $issuesOpen->whereNull('user_in_charge_id')->count();

        // Aelteste open issue (für Bug-Druck)
        $oldestOpenDays = 0;
        if ($issuesOpen->isNotEmpty()) {
            $oldestCreated = $issuesOpen->min('created_at');
            if ($oldestCreated) {
                $oldestOpenDays = (int) Carbon::parse($oldestCreated)->startOfDay()->diffInDays($now->copy()->startOfDay());
            }
        }

        // ── Achsen ──
        $axes = [];

        if ($hasBugBoard) {
            $axes['bug_pressure'] = $this->bugPressureScore(
                $bugsOpen->count(),
                $highPrioOpen->filter(fn ($i) => $bugIssues->contains('id', $i->id))->count(),
                $oldestOpenDays,
            );
        }

        if ($hasFeatureBoard) {
            $axes['feature_velocity'] = $this->featureVelocityScore(
                $featureIssues->count(),
                $featuresDone->count(),
                $featuresOpen->filter(fn ($i) => $i->due_date && $i->due_date->isPast())->count(),
            );
        }

        // Production-Health — auch ohne Errors berechnet (dann 100 = alles ruhig)
        $axes['production_health'] = $this->productionHealthScore(
            $errorsOpen->count(),
            $errorsAck->count(),
            $errorsTotalHits,
            $errorsSeenToday,
        );

        // Doku-Coverage — nur wenn Docs da, sonst Achse fehlt (Confidence sinkt)
        if ($docsCount > 0) {
            $axes['doc_coverage'] = $this->docCoverageScore($docsCount, $docsStale, $docsPublished);
        }

        // ── Confidence ──
        [$confScore, $confReason] = array_values($this->confidence->compute([
            'bug_board' => $hasBugBoard,
            'feature_board' => $hasFeatureBoard,
            'issues' => $allIssues->isNotEmpty(),
            'docs' => $docsCount > 0,
        ]));

        // ── Composite ──
        $weights = [
            'bug_pressure' => 30,
            'feature_velocity' => 30,
            'production_health' => 25,
            'doc_coverage' => 15,
        ];
        $composed = $this->compositor->compose($axes, $weights, $confScore);

        return [
            'health_score' => $composed['score'],
            'health_color' => $composed['color'],
            'worst_axis' => $composed['worst_axis'],
            'axis_scores' => empty($composed['axis_scores']) ? null : $composed['axis_scores'],
            'confidence_score' => $confScore,
            'confidence_reason' => $confReason,
            'frozen_context' => [
                'name' => $package->name,
                'github_repo_full_name' => $package->github_repo_full_name,
                'status' => $package->status,
                'icon' => $package->icon,
            ],
            // Issue-Counts
            'issues_total' => $allIssues->count(),
            'issues_open' => $issuesOpen->count(),
            'issues_done' => $issuesDone->count(),
            'issues_overdue' => $issuesOverdue->count(),
            'issues_high_priority_open' => $highPrioOpen->count(),
            // Bugs
            'bugs_total' => $bugIssues->count(),
            'bugs_open' => $bugsOpen->count(),
            'bugs_done' => $bugsDone->count(),
            // Features
            'features_total' => $featureIssues->count(),
            'features_open' => $featuresOpen->count(),
            'features_done' => $featuresDone->count(),
            // SP
            'story_points_total' => $spTotal,
            'story_points_open' => $spOpen,
            'story_points_done' => $spDone,
            // Errors
            'errors_open' => $errorsOpen->count(),
            'errors_acknowledged' => $errorsAck->count(),
            'errors_total_hits' => $errorsTotalHits,
            'errors_seen_today' => $errorsSeenToday,
            'latest_error_seen_at' => $latestErrorSeen,
            // Boards
            'boards_count' => $boardsCount,
            'has_bug_board' => $hasBugBoard,
            'has_feature_board' => $hasFeatureBoard,
            // Docs
            'doc_pages_count' => $docsCount,
            'doc_pages_stale' => $docsStale,
            'doc_pages_published' => $docsPublished,
            // Workload
            'active_users_count' => $activeUsers,
            'unassigned_open_issues' => $unassigned,
            // Movement
            'last_movement_at' => $this->computeLastMovement($package),
        ];
    }

    private function bugPressureScore(int $bugsOpen, int $highPrioBugs, int $oldestDays): int
    {
        $score = 100;
        // Anzahl offener Bugs
        if ($bugsOpen > 30)       $score -= 40;
        elseif ($bugsOpen > 15)   $score -= 25;
        elseif ($bugsOpen > 5)    $score -= 10;

        // High-priority Bugs sind doppelt schmerzhaft
        $score -= min(30, $highPrioBugs * 10);

        // Alter
        if ($oldestDays > 90)     $score -= 20;
        elseif ($oldestDays > 30) $score -= 10;

        return max(0, $score);
    }

    private function featureVelocityScore(int $totalFeatures, int $doneFeatures, int $overdueFeatures): int
    {
        if ($totalFeatures === 0) return 50; // keine Features = neutral

        $doneRatio = $doneFeatures / $totalFeatures;
        $score = (int) round($doneRatio * 100);

        // Penalty fuer ueberfaellige Features
        $score -= min(40, $overdueFeatures * 5);

        return max(0, min(100, $score));
    }

    private function productionHealthScore(int $errorsOpen, int $errorsAck, int $totalHits, int $seenToday): int
    {
        $score = 100;

        // Offene Errors sind alarmierender als acknowledged
        $score -= min(50, $errorsOpen * 3);
        $score -= min(20, $errorsAck * 1);

        // Hohe Hit-Rate = wiederkehrende Errors
        if ($totalHits > 1000)     $score -= 20;
        elseif ($totalHits > 200)  $score -= 10;

        // Heute neu = besonders alarmierend
        if ($seenToday > 0) $score -= min(20, $seenToday * 5);

        return max(0, $score);
    }

    private function docCoverageScore(int $count, int $stale, int $published): int
    {
        $score = 100;

        if ($count < 5)            $score -= 30;
        elseif ($count < 10)       $score -= 15;

        if ($count > 0) {
            $staleRatio = $stale / $count;
            if ($staleRatio > 0.5)      $score -= 30;
            elseif ($staleRatio > 0.25) $score -= 15;
        }

        return max(0, $score);
    }

    private function computeLastMovement(DevPackage $package): ?Carbon
    {
        $candidates = [];

        $lastIssueUpdate = DevIssue::whereIn('dev_board_id', $package->boards->pluck('id'))->max('updated_at');
        if ($lastIssueUpdate) $candidates[] = Carbon::parse($lastIssueUpdate);

        $lastDocUpdate = $package->docPages->max('updated_at');
        if ($lastDocUpdate) $candidates[] = Carbon::parse($lastDocUpdate);

        $lastErrorSeen = $package->errorOccurrences->max('last_seen_at');
        if ($lastErrorSeen) $candidates[] = Carbon::parse($lastErrorSeen);

        if (empty($candidates)) return null;
        return collect($candidates)->max();
    }

    private function writeTopIssues(DevPackageSnapshot $snapshot, DevPackage $package): void
    {
        // Top-5 brennende Issues: High-Prio-Bugs zuerst, dann ueberfaellig, dann aelteste
        $allIssues = $package->boards->flatMap(fn ($b) => $b->issues->each(function ($i) use ($b) {
            $i->board_type_cached = $b->type?->value;
            $i->board_name_cached = $b->name;
        }));

        $sortedOpen = $allIssues
            ->where('is_done', false)
            ->sort(function ($a, $b) {
                // High-Prio-Bugs zuerst
                $aHigh = ($a->board_type_cached === 'bug' && $a->priority === IssuePriority::High) ? 0 : 1;
                $bHigh = ($b->board_type_cached === 'bug' && $b->priority === IssuePriority::High) ? 0 : 1;
                if ($aHigh !== $bHigh) return $aHigh <=> $bHigh;
                // Ueberfaellig
                $aOver = ($a->due_date && $a->due_date->isPast()) ? 0 : 1;
                $bOver = ($b->due_date && $b->due_date->isPast()) ? 0 : 1;
                if ($aOver !== $bOver) return $aOver <=> $bOver;
                // Aelteste
                return $a->created_at <=> $b->created_at;
            })
            ->take(5)
            ->values();

        $userIds = $sortedOpen->pluck('user_in_charge_id')->filter()->unique()->values();
        $userMap = $userIds->isNotEmpty()
            ? User::whereIn('id', $userIds)->pluck('name', 'id')
            : collect();

        foreach ($sortedOpen as $idx => $i) {
            $snapshot->topIssues()->create([
                'issue_id' => $i->id,
                'issue_uuid' => $i->uuid,
                'issue_title' => mb_substr((string) ($i->title ?? '—'), 0, 500),
                'board_type' => $i->board_type_cached,
                'board_name' => $i->board_name_cached,
                'priority' => $i->priority?->value,
                'story_points' => $i->story_points?->value,
                'due_date' => $i->due_date,
                'is_overdue' => $i->due_date && $i->due_date->isPast(),
                'is_done' => (bool) $i->is_done,
                'user_in_charge_id' => $i->user_in_charge_id,
                'user_in_charge_name' => $userMap[$i->user_in_charge_id] ?? null,
                'rank' => $idx + 1,
            ]);
        }
    }

    private function writeTopErrors(DevPackageSnapshot $snapshot, DevPackage $package): void
    {
        $errors = $package->errorOccurrences
            ->whereIn('status', [DevErrorOccurrence::STATUS_OPEN, DevErrorOccurrence::STATUS_ACKNOWLEDGED])
            ->sortByDesc('occurrence_count')
            ->take(5)
            ->values();

        foreach ($errors as $idx => $e) {
            $snapshot->topErrors()->create([
                'error_occurrence_id' => $e->id,
                'exception_class' => mb_substr((string) ($e->exception_class ?? '—'), 0, 255),
                'message_excerpt' => mb_substr((string) ($e->message ?? ''), 0, 500),
                'occurrence_count' => (int) ($e->occurrence_count ?? 0),
                'status' => $e->status,
                'first_seen_at' => $e->first_seen_at,
                'last_seen_at' => $e->last_seen_at,
                'rank' => $idx + 1,
            ]);
        }
    }

    private function writePeople(DevPackageSnapshot $snapshot, DevPackage $package): void
    {
        $allIssues = $package->boards->flatMap(fn ($b) => $b->issues->each(function ($i) use ($b) {
            $i->board_type_cached = $b->type?->value;
        }));

        $byUser = $allIssues->whereNotNull('user_in_charge_id')->groupBy('user_in_charge_id');
        if ($byUser->isEmpty()) return;

        $userIds = $byUser->keys()->all();
        $userNames = User::whereIn('id', $userIds)->pluck('name', 'id');

        foreach ($byUser as $userId => $userIssues) {
            $open = $userIssues->where('is_done', false);
            if ($open->isEmpty()) continue;

            $done = $userIssues->where('is_done', true);
            $openBugs = $open->filter(fn ($i) => $i->board_type_cached === 'bug')->count();
            $openFeatures = $open->filter(fn ($i) => $i->board_type_cached === 'feature')->count();
            $overdue = $open->filter(fn ($i) => $i->due_date && $i->due_date->isPast())->count();

            $snapshot->people()->create([
                'user_id' => $userId,
                'user_name' => mb_substr((string) ($userNames[$userId] ?? ('User #' . $userId)), 0, 255),
                'open_issues' => $open->count(),
                'done_issues' => $done->count(),
                'open_bugs' => $openBugs,
                'open_features' => $openFeatures,
                'overdue_issues' => $overdue,
                'sp_open' => $open->sum(fn ($i) => $i->story_points?->points() ?? 0),
                'sp_done' => $done->sum(fn ($i) => $i->story_points?->points() ?? 0),
            ]);
        }
    }

    private function writeBoards(DevPackageSnapshot $snapshot, DevPackage $package): void
    {
        foreach ($package->boards as $board) {
            $snapshot->boards()->create([
                'board_id' => $board->id,
                'board_name' => mb_substr((string) ($board->name ?? '—'), 0, 255),
                'board_type' => $board->type?->value ?? 'custom',
                'issues_open' => $board->issues->where('is_done', false)->count(),
                'issues_done' => $board->issues->where('is_done', true)->count(),
                'issues_total' => $board->issues->count(),
            ]);
        }
    }
}
