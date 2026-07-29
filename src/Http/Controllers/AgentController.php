<?php

namespace Platform\Dev\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Platform\Dev\Enums\IssueStoryPoints;
use Platform\Dev\Models\DevBoardSlot;
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

        // Nächstes claimbares Issue bestimmen:
        //  - nur agent-freigegebene bug/feature-Boards des Packages (per-Board-Flag)
        //  - Features: nur aus "Ready"-Slots. Bugs: zusätzlich aus dem Backlog —
        //    jeder gemeldete Bug soll drankommen, ohne manuelles Vorsortieren.
        //  - offen, nicht erledigt, nicht (frisch) gesperrt
        // Priorität: Bugs vor Features -> Board -> Ready vor Backlog -> Slot -> Issue.
        $query = DevIssue::query()
            ->join('dev_boards', 'dev_issues.dev_board_id', '=', 'dev_boards.id')
            ->join('dev_board_slots', 'dev_issues.dev_board_slot_id', '=', 'dev_board_slots.id')
            ->whereNull('dev_boards.deleted_at')
            ->whereNull('dev_board_slots.deleted_at')
            ->where('dev_boards.dev_package_id', $package->id)
            ->whereIn('dev_boards.type', ['bug', 'feature'])
            ->where(function ($q) {
                // Ready ist immer claimbar; bei Bug-Boards zusätzlich der Backlog (agent_role NULL).
                $q->where('dev_board_slots.agent_role', 'ready')
                  ->orWhere(function ($q2) {
                      $q2->where('dev_boards.type', 'bug')
                         ->whereNull('dev_board_slots.agent_role');
                  });
            })
            ->where('dev_issues.status', 'open')
            ->where('dev_issues.is_done', false)
            ->where(function ($q) {
                $q->whereNull('dev_issues.agent_locked_at')
                  ->orWhere('dev_issues.agent_locked_at', '<', now()->subMinutes(30));
            });

        // Filter by max story points (worker sends this from local config)
        $maxPoints = $request->input('max_story_points');
        if ($maxPoints !== null) {
            $allowed = collect(IssueStoryPoints::cases())
                ->filter(fn ($sp) => $sp->points() <= (int) $maxPoints)
                ->pluck('value')
                ->all();
            $query->where(function ($q) use ($allowed) {
                $q->whereNull('dev_issues.story_points')
                  ->orWhereIn('dev_issues.story_points', $allowed);
            });
        }

        $issue = $query
            ->orderByRaw("CASE dev_boards.type WHEN 'bug' THEN 0 WHEN 'feature' THEN 1 ELSE 2 END")
            ->orderBy('dev_boards.order')       // Board-Reihenfolge (bei mehreren Boards gleichen Typs)
            ->orderByRaw("CASE WHEN dev_board_slots.agent_role = 'ready' THEN 0 ELSE 1 END") // Ready vor Backlog
            ->orderBy('dev_board_slots.order')  // Slot-Position
            ->orderBy('dev_issues.slot_order')  // Issue-Position im Slot
            ->select('dev_issues.*', 'dev_boards.type as board_type')
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
                'board_type' => $issue->board_type, // bug | feature — für den Feature-Sweep im Worker
                'dev_package_id' => $package->id,
                'package_name' => $package->name,
                'github_repo' => $package->github_repo_full_name,
                'labels' => $issue->labels,
                // Kontext-Thread (Rückfragen/Antworten), falls der Worker Mitglied ist.
                'thread' => $this->contextThread($issue, (int) $request->user()?->id),
            ],
        ]);
    }

    /**
     * Nachrichten des Context-Threads dieses Issues — nur wenn der Worker Mitglied ist.
     * Liefert den bisherigen Rückfrage-/Antwort-Verlauf als Kontext fürs nächste Claimen.
     *
     * @return array<int, array{user_id:int, author:string, body:?string, at:?string}>|null
     */
    protected function contextThread(DevIssue $issue, int $workerId): ?array
    {
        if ($workerId < 1) {
            return null;
        }
        $channel = \Platform\Core\Models\TerminalChannel::forTeam((int) $issue->team_id)
            ->forContext(DevIssue::class, $issue->id)
            ->first();
        if (! $channel) {
            return null;
        }
        $isMember = \Platform\Core\Models\TerminalChannelMember::where('channel_id', $channel->id)
            ->where('user_id', $workerId)->exists();
        if (! $isMember) {
            return null;
        }

        return $channel->messages()
            ->with('user:id,name')
            ->orderBy('id')
            ->limit(80)
            ->get()
            ->map(fn ($m) => [
                'user_id' => (int) $m->user_id,
                'author' => $m->user?->name ?? ('User #'.$m->user_id),
                'body' => $m->body_plain,
                'at' => optional($m->created_at)->toIso8601String(),
            ])->values()->all();
    }

    /**
     * Postet eine Nachricht in den Context-Thread des Issues (Thread anlegen, falls
     * keiner da) — Absender = Worker, erwähnt IMMER den Verantwortlichen des Dev-
     * Packages (DevPackage.user_in_charge_id; Fallback Package-/Issue-Ersteller).
     */
    protected function postToIssueThread(DevIssue $issue, int $senderId, string $body): void
    {
        $package = DevPackage::find((int) $issue->board?->dev_package_id);
        $recipientId = (int) ($package?->user_in_charge_id ?: $package?->created_by_user_id ?: $issue->created_by_user_id);
        $recipients = array_values(array_filter([$recipientId]));

        app(\Platform\Core\Services\PostContextMessage::class)->post(
            teamId: (int) $issue->team_id,
            contextType: DevIssue::class,
            contextId: $issue->id,
            contextName: $issue->title ?: 'Issue',
            senderId: $senderId,
            body: $body,
            memberIds: $recipients,
            mentionUserIds: $recipients,
        );
    }

    /**
     * Rückfrage stellen: die Frage in den Context-Thread des Issues posten (Thread
     * anlegen, falls keiner) — erwähnt den Package-Verantwortlichen — und das Issue
     * in den human-Slot zurückstellen (Park). Reclaim macht der Mensch (zurück in Ready).
     *
     * POST /api/dev/agent/issues/{id}/ask  { question, branch? }
     */
    public function ask(Request $request, int $id): JsonResponse
    {
        $issue = DevIssue::find($id);
        if (!$issue) {
            return response()->json(['message' => 'Issue not found'], 404);
        }
        $data = $request->validate([
            'question' => 'required|string|max:5000',
            'branch' => 'nullable|string|max:255',
        ]);
        $question = $data['question'];

        $this->postToIssueThread($issue, (int) $request->user()?->id, $question);

        // Park: in den "Rückfrage"-Slot (agent_role=human) desselben Boards zurückstellen.
        $humanSlot = DevBoardSlot::where('dev_board_id', $issue->dev_board_id)
            ->where('agent_role', 'human')
            ->orderBy('order')
            ->first();
        $update = [
            'agent_summary' => 'RÜCKFRAGE: ' . $question,
            'agent_locked_at' => null,
            'agent_locked_by' => null,
        ];
        if ($humanSlot) {
            $update['dev_board_slot_id'] = $humanSlot->id;
        }
        $issue->update($update);

        $issue->logActivity("Agent hat eine Rückfrage im Kontext-Thread gestellt.\n\nFrage: {$question}", [
            'source' => 'agent',
            'status' => 'deferred',
        ]);

        Log::info('[Dev Agent] Rückfrage in Context-Thread', ['issue_id' => $issue->id, 'moved_to_slot' => $humanSlot?->id]);

        return response()->json([
            'message' => 'Question posted to context thread',
            'data' => ['id' => $issue->id, 'moved_to_slot' => $humanSlot?->id],
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

        // Kurze Erledigt-Meldung in den Context-Thread (Thread anlegen, falls keiner) —
        // erwähnt den Package-Verantwortlichen, auch ohne vorherige Rückfrage.
        $doneNote = trim((string) $summary);
        $this->postToIssueThread($issue, (int) $request->user()?->id, '✅ Erledigt'.($doneNote !== '' ? ': '.$doneNote : '.'));

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
     * Log elapsed time onto an issue (autonomous worker, at end of run).
     *
     * POST /api/dev/agent/issues/{id}/log-time  { minutes, note? }
     *
     * Writes an Organization time entry with context = this DevIssue, so the run's
     * wall-clock time rolls up on the issue (and its board/package/team hierarchy).
     * The worker (its own platform token) is the user; the team comes from the issue.
     */
    public function logTime(Request $request, int $id): JsonResponse
    {
        $issue = DevIssue::find($id);

        if (!$issue) {
            return response()->json(['message' => 'Issue not found'], 404);
        }

        $data = $request->validate([
            'minutes' => 'required|integer|min:1|max:100000',
            'note' => 'nullable|string|max:1000',
        ]);

        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        // Reuse the organization time-entry service (context_type = DevIssue).
        // Guarded so the dev module stays usable if organization is absent.
        $storeClass = \Platform\Organization\Services\StoreTimeEntry::class;
        if (!class_exists($storeClass)) {
            return response()->json(['message' => 'Time tracking unavailable (organization module missing)'], 501);
        }

        $entry = app($storeClass)->store([
            'team_id' => $issue->team_id,
            'user_id' => $user->id,
            'context_type' => DevIssue::class,
            'context_id' => $issue->id,
            'work_date' => now()->toDateString(),
            'minutes' => (int) $data['minutes'],
            'note' => $data['note'] ?? null,
            'metadata' => ['source' => 'agent'],
        ]);

        Log::info('[Dev Agent] Time logged', [
            'issue_id' => $issue->id,
            'minutes' => (int) $data['minutes'],
            'entry_id' => $entry->id,
        ]);

        return response()->json([
            'message' => 'Time logged',
            'data' => [
                'id' => $entry->id,
                'minutes' => $entry->minutes,
                'issue_id' => $issue->id,
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
     * Agent stellt eine Rückfrage und legt das Issue in den human-Slot zurück.
     * Der Mensch beantwortet und schiebt es zurück nach Ready — dann greift der Worker erneut.
     *
     * POST /api/dev/agent/issues/{id}/defer
     */
    public function defer(Request $request, int $id): JsonResponse
    {
        $issue = DevIssue::find($id);

        if (!$issue) {
            return response()->json(['message' => 'Issue not found'], 404);
        }

        $data = $request->validate([
            'question' => 'required|string|max:5000',
            'branch' => 'nullable|string|max:255',
        ]);
        $question = $data['question'];

        // In den "Rückfrage"-Slot (agent_role=human) desselben Boards verschieben.
        $humanSlot = DevBoardSlot::where('dev_board_id', $issue->dev_board_id)
            ->where('agent_role', 'human')
            ->orderBy('order')
            ->first();

        $update = [
            'agent_summary' => 'RÜCKFRAGE: ' . $question,
            'agent_locked_at' => null,
            'agent_locked_by' => null,
        ];
        if ($humanSlot) {
            $update['dev_board_slot_id'] = $humanSlot->id;
        }
        $issue->update($update);

        // Issue bleibt "open" — es ist eine offene Rückfrage, kein Abschluss.
        $issue->logActivity("Agent hat eine Rückfrage gestellt und das Issue zurückgestellt.\n\nFrage: {$question}", [
            'source' => 'agent',
            'status' => 'deferred',
        ]);

        Log::info('[Dev Agent] Issue deferred (Rückfrage)', [
            'issue_id' => $issue->id,
            'moved_to_slot' => $humanSlot?->id,
        ]);

        return response()->json([
            'message' => 'Issue deferred to human slot',
            'data' => ['id' => $issue->id, 'moved_to_slot' => $humanSlot?->id],
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
     * Pipeline-Aggregat über alle agent-freigegebenen Packages — die Leitwarte
     * für den Worker-Betrieb: offene Bugs/Features, Ready-Queue, Rückfragen, Alter.
     *
     * GET /api/dev/agent/pipeline
     */
    public function pipeline(Request $request): JsonResponse
    {
        $packages = DevPackage::agentEnabled()->get(['id', 'name', 'github_repo_full_name']);
        $ids = $packages->pluck('id')->all();

        $empty = ['bugs' => 0, 'features' => 0, 'ready' => 0, 'rueckfragen' => 0, 'oldest' => null];
        if (empty($ids)) {
            return response()->json(['data' => ['totals' => $empty, 'packages' => [], 'next_up' => []]]);
        }

        $base = fn () => DevIssue::query()
            ->join('dev_boards', 'dev_issues.dev_board_id', '=', 'dev_boards.id')
            ->join('dev_board_slots', 'dev_issues.dev_board_slot_id', '=', 'dev_board_slots.id')
            ->whereNull('dev_boards.deleted_at')
            ->whereNull('dev_board_slots.deleted_at')
            ->whereIn('dev_boards.dev_package_id', $ids)
            ->whereIn('dev_boards.type', ['bug', 'feature'])
            ->where('dev_issues.status', 'open')
            ->where('dev_issues.is_done', false);

        // Zählung pro Package x Typ x Rolle.
        $rows = $base()
            ->selectRaw('dev_boards.dev_package_id as pid, dev_boards.type as btype, dev_board_slots.agent_role as role, count(*) as c, min(dev_issues.created_at) as oldest')
            ->groupBy('dev_boards.dev_package_id', 'dev_boards.type', 'dev_board_slots.agent_role')
            ->get();

        $perPackage = [];
        foreach ($packages as $p) {
            $perPackage[$p->id] = ['name' => $p->name, 'github_repo' => $p->github_repo_full_name] + $empty;
        }
        foreach ($rows as $r) {
            $pk = $perPackage[$r->pid];
            if ($r->btype === 'bug') $pk['bugs'] += (int) $r->c;
            if ($r->btype === 'feature') $pk['features'] += (int) $r->c;
            if ($r->role === 'ready' || ($r->btype === 'bug' && $r->role === null)) $pk['ready'] += (int) $r->c;
            if ($r->role === 'human') $pk['rueckfragen'] += (int) $r->c;
            if ($r->oldest && ($pk['oldest'] === null || $r->oldest < $pk['oldest'])) $pk['oldest'] = $r->oldest;
            $perPackage[$r->pid] = $pk;
        }

        $totals = $empty;
        foreach ($perPackage as $pk) {
            foreach (['bugs', 'features', 'ready', 'rueckfragen'] as $k) {
                $totals[$k] += $pk[$k];
            }
            if ($pk['oldest'] && ($totals['oldest'] === null || $pk['oldest'] < $totals['oldest'])) {
                $totals['oldest'] = $pk['oldest'];
            }
        }

        // "Was kommt als Nächstes": claimbare Issues (Ready ODER Bug-Backlog), nicht gesperrt,
        // in Claim-Reihenfolge (Bugs vor Features, Ready vor Backlog, dann Alter).
        $nameById = $packages->keyBy('id');
        $nextUp = $base()
            ->where(function ($q) {
                $q->where('dev_board_slots.agent_role', 'ready')
                  ->orWhere(function ($q2) {
                      $q2->where('dev_boards.type', 'bug')->whereNull('dev_board_slots.agent_role');
                  });
            })
            ->where(function ($q) {
                $q->whereNull('dev_issues.agent_locked_at')
                  ->orWhere('dev_issues.agent_locked_at', '<', now()->subMinutes(30));
            })
            ->orderByRaw("CASE dev_boards.type WHEN 'bug' THEN 0 ELSE 1 END")
            ->orderByRaw("CASE WHEN dev_board_slots.agent_role = 'ready' THEN 0 ELSE 1 END")
            ->orderBy('dev_issues.created_at')
            ->limit(12)
            ->get(['dev_issues.id', 'dev_issues.title', 'dev_issues.created_at', 'dev_issues.story_points', 'dev_boards.type as board_type', 'dev_boards.dev_package_id as pid'])
            ->map(fn ($i) => [
                'id' => $i->id,
                'title' => $i->title,
                'type' => $i->board_type,
                'package' => $nameById[$i->pid]->name ?? null,
                'story_points' => $i->story_points?->value ?? $i->story_points,
                'created_at' => optional($i->created_at)->toIso8601String(),
            ]);

        return response()->json(['data' => [
            'totals' => $totals,
            'packages' => array_values($perPackage),
            'next_up' => $nextUp,
        ]]);
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
