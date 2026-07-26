<?php

namespace Platform\Dev\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Platform\Dev\Enums\IssueStoryPoints;
use Platform\Dev\Models\DevIssue;
use Platform\Dev\Models\DevPackage;

class AgentController extends Controller
{
    /**
     * Fetch and lock the next open issue for a package.
     *
     * POST /api/dev/agent/packages/{slug}/next-issue
     */
    public function nextIssue(Request $request, string $slug): JsonResponse
    {
        $package = $this->resolvePackage($slug);

        if (!$package) {
            return response()->json(['message' => 'Package not found'], 404);
        }

        // Gate: the worker may only pull issues from packages that were
        // explicitly released for the agent.
        if (!$package->agent_enabled) {
            return response()->json(['message' => 'Package not released for agent'], 403);
        }

        // Find next open, unlocked issue on feature/bug boards (not backlog).
        // Order: slot position (board column order), then issue order within slot.
        $query = DevIssue::query()
            ->whereHas('board', fn ($q) => $q
                ->where('dev_package_id', $package->id)
                ->whereIn('type', ['feature', 'bug'])
            )
            ->whereNotNull('dev_board_slot_id') // not in backlog
            ->where('status', 'open')
            ->where('is_done', false)
            ->where(function ($q) {
                $q->whereNull('agent_locked_at')
                  ->orWhere('agent_locked_at', '<', now()->subMinutes(30));
            });

        // Filter by max story points (worker sends this from local config)
        $maxPoints = $request->input('max_story_points');
        if ($maxPoints !== null) {
            $allowed = collect(IssueStoryPoints::cases())
                ->filter(fn ($sp) => $sp->points() <= (int) $maxPoints)
                ->pluck('value')
                ->all();
            $query->where(function ($q) use ($allowed) {
                $q->whereNull('story_points')
                  ->orWhereIn('story_points', $allowed);
            });
        }

        $issue = $query
            ->join('dev_board_slots', 'dev_issues.dev_board_slot_id', '=', 'dev_board_slots.id')
            ->orderBy('dev_board_slots.order')  // slot position on board
            ->orderBy('dev_issues.slot_order')  // issue position within slot
            ->select('dev_issues.*')
            ->first();

        if (!$issue) {
            return response()->json(null, 204);
        }

        // Lock the issue
        $issue->update([
            'agent_locked_at' => now(),
            'agent_locked_by' => 'worker:' . $slug,
        ]);

        return response()->json([
            'data' => [
                'id' => $issue->id,
                'uuid' => $issue->uuid,
                'title' => $issue->title,
                'description' => $issue->description,
                'priority' => $issue->priority?->value ?? $issue->priority,
                'acceptance_criteria' => $issue->acceptance_criteria,
                'dev_board_id' => $issue->dev_board_id,
                'dev_package_id' => $package->id,
                'labels' => $issue->labels,
            ],
        ]);
    }

    /**
     * Mark an issue as completed by the agent.
     *
     * POST /api/dev/agent/issues/{id}/complete
     */
    public function complete(Request $request, int $id): JsonResponse
    {
        $issue = DevIssue::find($id);

        if (!$issue) {
            return response()->json(['message' => 'Issue not found'], 404);
        }

        $data = $request->validate([
            'branch' => 'nullable|string|max:255',
            'summary' => 'nullable|string|max:5000',
        ]);

        $branch = $data['branch'] ?? null;
        $summary = $data['summary'] ?? null;

        // Store agent metadata
        $issue->update([
            'agent_branch' => $branch,
            'agent_summary' => $summary,
            'agent_completed_at' => now(),
            'agent_locked_at' => null,
            'agent_locked_by' => null,
        ]);

        // Log activity on the issue (visible in UI activity feed)
        $activityMessage = "Agent hat dieses Issue bearbeitet.";
        if ($branch) {
            $activityMessage .= "\nBranch: {$branch}";
        }
        if ($summary) {
            $activityMessage .= "\n\n{$summary}";
        }
        $issue->logActivity($activityMessage, [
            'source' => 'agent',
            'branch' => $branch,
        ]);

        // Close the issue
        $issue->close();

        Log::info('[Dev Agent] Issue completed', [
            'issue_id' => $issue->id,
            'branch' => $branch,
        ]);

        return response()->json([
            'message' => 'Issue completed and closed',
            'data' => [
                'id' => $issue->id,
                'status' => 'closed',
                'agent_branch' => $issue->agent_branch,
                'agent_completed_at' => $issue->agent_completed_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * Mark an issue as failed by the agent.
     *
     * POST /api/dev/agent/issues/{id}/fail
     */
    public function fail(Request $request, int $id): JsonResponse
    {
        $issue = DevIssue::find($id);

        if (!$issue) {
            return response()->json(['message' => 'Issue not found'], 404);
        }

        $data = $request->validate([
            'error' => 'nullable|string|max:5000',
        ]);

        $error = $data['error'] ?? 'Unknown error';

        $issue->update([
            'agent_summary' => 'FAILED: ' . $error,
            'agent_locked_at' => null,
            'agent_locked_by' => null,
        ]);

        // Log failure as activity on the issue
        $issue->logActivity("Agent konnte dieses Issue nicht bearbeiten.\n\nFehler: {$error}", [
            'source' => 'agent',
            'status' => 'failed',
        ]);

        Log::warning('[Dev Agent] Issue failed', [
            'issue_id' => $issue->id,
            'error' => $error,
        ]);

        return response()->json([
            'message' => 'Issue marked as failed',
            'data' => ['id' => $issue->id],
        ]);
    }

    /**
     * Unlock a stuck issue (e.g. worker crashed).
     *
     * POST /api/dev/agent/issues/{id}/unlock
     */
    public function unlock(Request $request, int $id): JsonResponse
    {
        $issue = DevIssue::find($id);

        if (!$issue) {
            return response()->json(['message' => 'Issue not found'], 404);
        }

        $issue->update([
            'agent_locked_at' => null,
            'agent_locked_by' => null,
        ]);

        Log::info('[Dev Agent] Issue unlocked', ['issue_id' => $issue->id]);

        return response()->json([
            'message' => 'Issue unlocked',
            'data' => ['id' => $issue->id],
        ]);
    }

    /**
     * List all agent-enabled packages with their repo mapping.
     *
     * GET /api/dev/agent/packages
     */
    public function packages(Request $request): JsonResponse
    {
        $packages = DevPackage::agentEnabled()
            ->orderBy('order')
            ->get(['id', 'name', 'github_repo_full_name', 'status']);

        return response()->json([
            'data' => $packages->map(fn (DevPackage $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'github_repo_full_name' => $p->github_repo_full_name,
            ])->values(),
        ]);
    }

    /**
     * Resolve a DevPackage by slug.
     *
     * Accepts:
     *  - Composer name without vendor: "platform-planner" (from composer.json)
     *  - DB name: "platforms-planner"
     *  - Short folder name: "planner"
     *
     * Matching order: exact -> s/platforms-/platform-/ -> s/platform-/platforms-/ -> suffix
     */
    protected function resolvePackage(string $slug): ?DevPackage
    {
        // 1. Exact match (covers both composer name and DB name)
        $package = DevPackage::where('name', $slug)->active()->first();
        if ($package) {
            return $package;
        }

        // 2. Swap prefix: platform- <-> platforms-
        if (str_starts_with($slug, 'platforms-')) {
            $alt = 'platform-' . substr($slug, 10);
        } elseif (str_starts_with($slug, 'platform-')) {
            $alt = 'platforms-' . substr($slug, 9);
        } else {
            $alt = null;
        }

        if ($alt) {
            $package = DevPackage::where('name', $alt)->active()->first();
            if ($package) {
                return $package;
            }
        }

        // 3. Short slug: try both prefixes
        if (!str_contains($slug, '-') || (!str_starts_with($slug, 'platform') && !str_starts_with($slug, 'platforms'))) {
            foreach (['platforms-', 'platform-'] as $prefix) {
                $package = DevPackage::where('name', $prefix . $slug)->active()->first();
                if ($package) {
                    return $package;
                }
            }
        }

        // 4. Suffix match as last resort
        return DevPackage::where('name', 'like', '%-' . $slug)->active()->first();
    }
}
