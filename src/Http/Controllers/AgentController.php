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

        // Nächstes Issue bestimmen — wie im Planner: relevant ist der VERANTWORTLICHE,
        // KEINE Slot-Rollen mehr. Der Worker holt ihm zugewiesene Issues/Features
        // (user_in_charge_id) und, bei gesetztem Setting, zusätzlich unzugewiesene (Pool).
        //  - nur agent-freigegebene bug/feature-Boards des Packages
        //  - offen, nicht erledigt, nicht (frisch) gesperrt
        $workerId = (int) $request->user()?->id;
        $allowUnassigned = $request->boolean('allow_unassigned');

        $query = DevIssue::query()
            ->join('dev_boards', 'dev_issues.dev_board_id', '=', 'dev_boards.id')
            ->leftJoin('dev_board_slots', 'dev_issues.dev_board_slot_id', '=', 'dev_board_slots.id')
            ->whereNull('dev_boards.deleted_at')
            ->where('dev_boards.dev_package_id', $package->id)
            ->whereIn('dev_boards.type', ['bug', 'feature'])
            ->where('dev_issues.status', 'open')
            ->where('dev_issues.is_done', false)
            ->where(function ($q) {
                $q->whereNull('dev_issues.agent_locked_at')
                  ->orWhere('dev_issues.agent_locked_at', '<', now()->subMinutes(30));
            })
            ->where(function ($q) use ($workerId, $allowUnassigned) {
                $q->where('dev_issues.user_in_charge_id', $workerId);
                if ($allowUnassigned) {
                    $q->orWhereNull('dev_issues.user_in_charge_id');
                }
            });
        // Wie im Planner: KEIN RÜCKFRAGE-Skip. Eine Rückfrage weist das Issue dem
        // Verantwortlichen zu → es fällt automatisch aus assignedTo(worker). Kommt es
        // (beantwortet) zurück an den Worker, ist es wieder claimbar; der Marker
        // verschwindet beim complete (überschreibt agent_summary).

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

        // Reihenfolge = Board-Layout (der Mensch priorisiert per Anordnung):
        // Board-Order → Slot-Order → Position im Slot. Backlog (ohne Slot) zuletzt.
        $issue = $query
            ->orderBy('dev_boards.order')
            ->orderByRaw('dev_board_slots.order IS NULL')
            ->orderBy('dev_board_slots.order')
            ->orderBy('dev_issues.slot_order')
            ->orderBy('dev_issues.created_at')
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
    /** Empfänger einer Dev-Rückfrage: Verantwortlicher des Packages (Fallback Package-/Issue-Ersteller). */
    protected function packageResponsibleId(DevIssue $issue): ?int
    {
        $package = DevPackage::find((int) $issue->board?->dev_package_id);
        $id = (int) ($package?->user_in_charge_id ?: $package?->created_by_user_id ?: $issue->created_by_user_id);

        return $id > 0 ? $id : null;
    }

    /**
     * Erfolgsmeldung zustellen: existiert schon ein Kontext-Thread (es lief eine
     * Rückfrage) → dort rein (Kreis schließen). Sonst DM an den Package-
     * Verantwortlichen — für ein einmaliges „fertig" lohnt kein neuer Thread.
     */
    protected function announceCompletion(DevIssue $issue, int $senderId, string $body): void
    {
        $hasThread = \Platform\Core\Models\TerminalChannel::forTeam((int) $issue->team_id)
            ->forContext(DevIssue::class, $issue->id)
            ->exists();

        if ($hasThread) {
            $this->postToIssueThread($issue, $senderId, $body);

            return;
        }

        if ($recipient = $this->packageResponsibleId($issue)) {
            app(\Platform\Core\Services\PostDirectMessage::class)
                ->post((int) $issue->team_id, $senderId, $recipient, $body);
        }
    }

    protected function postToIssueThread(DevIssue $issue, int $senderId, string $body): void
    {
        $recipients = array_values(array_filter([$this->packageResponsibleId($issue)]));

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
     * dem Verantwortlichen zuweisen (Park). Damit fällt es aus assignedTo(worker).
     * Reclaim: der Mensch weist den Verantwortlichen zurück auf den Worker.
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

        // Park wie im Planner: dem Package-Verantwortlichen zuweisen → raus aus der
        // Worker-Queue (nicht mehr ihm zugewiesen, nicht unzugewiesen). Reclaim macht
        // der Mensch, indem er den Verantwortlichen wieder auf den Worker setzt.
        $issue->update([
            'user_in_charge_id' => $this->packageResponsibleId($issue) ?? $issue->user_in_charge_id,
            'agent_summary' => 'RÜCKFRAGE: ' . $question,
            'agent_locked_at' => null,
            'agent_locked_by' => null,
        ]);

        $issue->logActivity("Agent hat eine Rückfrage im Kontext-Thread gestellt.\n\nFrage: {$question}", [
            'source' => 'agent',
            'status' => 'deferred',
        ]);

        Log::info('[Dev Agent] Rückfrage in Context-Thread', ['issue_id' => $issue->id]);

        return response()->json([
            'message' => 'Question posted to context thread',
            'data' => ['id' => $issue->id],
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

        // Erledigt-Meldung: existiert schon ein Rückfrage-Thread → dort (Kreis schließen),
        // sonst DM an den Package-Verantwortlichen. Für „fertig" lohnt kein neuer Thread.
        $doneNote = trim((string) $summary);
        $body = '✅ Erledigt: '.($issue->title ?: 'Issue').($doneNote !== '' ? "\n\n".$doneNote : '');
        $this->announceCompletion($issue, (int) $request->user()?->id, $body);

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
     * Agent stellt eine Rückfrage und weist das Issue dem Verantwortlichen zu (Park).
     * Der Mensch beantwortet und weist es zurück an den Worker — dann greift der Worker erneut.
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

        // Park wie ask/Planner: dem Package-Verantwortlichen zuweisen (raus aus der
        // Worker-Queue), Lock lösen. Keine Slot-Rollen mehr. (Legacy — der Worker
        // nutzt jetzt ask; hier kein Thread-Post.)
        $issue->update([
            'user_in_charge_id' => $this->packageResponsibleId($issue) ?? $issue->user_in_charge_id,
            'agent_summary' => 'RÜCKFRAGE: ' . $question,
            'agent_locked_at' => null,
            'agent_locked_by' => null,
        ]);

        // Issue bleibt "open" — es ist eine offene Rückfrage, kein Abschluss.
        $issue->logActivity("Agent hat eine Rückfrage gestellt und das Issue zurückgestellt.\n\nFrage: {$question}", [
            'source' => 'agent',
            'status' => 'deferred',
        ]);

        Log::info('[Dev Agent] Issue deferred (Rückfrage)', ['issue_id' => $issue->id]);

        return response()->json([
            'message' => 'Issue deferred',
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
     * Pipeline-Aggregat über alle agent-freigegebenen Packages — die Leitwarte
     * für den Worker-Betrieb: offene Bugs/Features, Ready-Queue, Rückfragen, Alter.
     *
     * GET /api/dev/agent/pipeline
     */
    public function pipeline(Request $request): JsonResponse
    {
        $packages = DevPackage::agentEnabled()->get(['id', 'name', 'github_repo_full_name']);
        // Package-Scope deckungsgleich mit dem Claim: schränkt der Worker per
        // allowedPackages/Pin ein, sendet er die Namen mit → Vorschau zeigt nur diese.
        // Ohne Scope (leer) = alle agent-freigegebenen (wie targetPackages ohne Pin).
        $scope = array_values(array_filter((array) $request->input('packages', [])));
        if ($scope) {
            $packages = $packages->whereIn('name', $scope)->values();
        }
        $ids = $packages->pluck('id')->all();

        $empty = ['bugs' => 0, 'features' => 0, 'ready' => 0, 'rueckfragen' => 0, 'oldest' => null];
        if (empty($ids)) {
            return response()->json(['data' => ['totals' => $empty, 'packages' => [], 'next_up' => []]]);
        }

        $workerId = (int) $request->user()?->id;
        $stale = now()->subMinutes(30);

        // Deckungsgleich mit dem echten Claim (nextIssue): nur der Verantwortliche,
        // und NUR bei gesetztem Worker-Setting zusätzlich der unzugewiesene Pool. Ohne
        // das würde die Vorschau Tickets zeigen, die der Worker nie zieht.
        $allowUnassigned = $request->boolean('allow_unassigned');
        $claimable = function ($q) use ($workerId, $allowUnassigned) {
            $q->where('dev_issues.user_in_charge_id', $workerId);
            if ($allowUnassigned) {
                $q->orWhereNull('dev_issues.user_in_charge_id');
            }
        };

        // Wie im Claim: keine Slot-Rollen mehr, es zählt der Verantwortliche.
        $base = fn () => DevIssue::query()
            ->join('dev_boards', 'dev_issues.dev_board_id', '=', 'dev_boards.id')
            ->whereNull('dev_boards.deleted_at')
            ->whereIn('dev_boards.dev_package_id', $ids)
            ->whereIn('dev_boards.type', ['bug', 'feature'])
            ->where('dev_issues.status', 'open')
            ->where('dev_issues.is_done', false);

        $perPackage = [];
        foreach ($packages as $p) {
            $perPackage[$p->id] = ['name' => $p->name, 'github_repo' => $p->github_repo_full_name] + $empty;
        }

        // Bugs/Features + ältestes offenes Issue je Package.
        foreach ($base()
            ->selectRaw('dev_boards.dev_package_id as pid, dev_boards.type as btype, count(*) as c, min(dev_issues.created_at) as oldest')
            ->groupBy('dev_boards.dev_package_id', 'dev_boards.type')->get() as $r) {
            $pk = $perPackage[$r->pid];
            if ($r->btype === 'bug') $pk['bugs'] += (int) $r->c;
            if ($r->btype === 'feature') $pk['features'] += (int) $r->c;
            if ($r->oldest && ($pk['oldest'] === null || $r->oldest < $pk['oldest'])) $pk['oldest'] = $r->oldest;
            $perPackage[$r->pid] = $pk;
        }

        // Rückfragen wie im Planner: NUR dem Worker zugewiesene Issues mit RÜCKFRAGE-Marker
        // (nicht package-weit — sonst zählt es fremde/alte Rückfragen anderer mit).
        foreach ($base()->where('dev_issues.user_in_charge_id', $workerId)
            ->where('dev_issues.agent_summary', 'like', 'RÜCKFRAGE:%')
            ->selectRaw('dev_boards.dev_package_id as pid, count(*) as c')
            ->groupBy('dev_boards.dev_package_id')->get() as $r) {
            $perPackage[$r->pid]['rueckfragen'] += (int) $r->c;
        }

        // Ready = für DIESEN Worker claimbar (nach seinem claim_unassigned-Setting), nicht gesperrt.
        foreach ($base()
            ->where($claimable)
            ->where(fn ($q) => $q->whereNull('dev_issues.agent_locked_at')->orWhere('dev_issues.agent_locked_at', '<', $stale))
            ->selectRaw('dev_boards.dev_package_id as pid, count(*) as c')
            ->groupBy('dev_boards.dev_package_id')->get() as $r) {
            $perPackage[$r->pid]['ready'] += (int) $r->c;
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

        // "Was kommt als Nächstes": für diesen Worker claimbare Issues (zugewiesen ODER
        // unzugewiesen), nicht gesperrt — in Board-Reihenfolge (wie der echte Claim).
        $nameById = $packages->keyBy('id');
        $nextUp = $base()
            ->leftJoin('dev_board_slots', 'dev_issues.dev_board_slot_id', '=', 'dev_board_slots.id')
            ->where($claimable)
            ->where(fn ($q) => $q->whereNull('dev_issues.agent_locked_at')->orWhere('dev_issues.agent_locked_at', '<', $stale))
            ->orderBy('dev_boards.order')
            ->orderByRaw('dev_board_slots.order IS NULL')
            ->orderBy('dev_board_slots.order')
            ->orderBy('dev_issues.slot_order')
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
