<?php

namespace Platform\Dev\Livewire\Package;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Platform\Dev\Models\DevBoard;
use Platform\Dev\Models\DevPackage;
use Platform\Dev\Models\DevPackageErrorSettings;
use Platform\Dev\Models\DevErrorOccurrence;
use Platform\Dev\Models\DevIssue;
use Platform\Dev\Services\DevBoardService;
use Platform\Integrations\Models\IntegrationGithubCommit;
use Platform\Integrations\Models\IntegrationGithubPullRequest;
use Platform\Integrations\Models\IntegrationGithubRepo;

class Show extends Component
{
    public DevPackage $package;

    protected $rules = [
        'errorSettings.enabled' => 'boolean',
        'errorSettings.capture_console_errors' => 'boolean',
        'errorSettings.auto_create_issue' => 'boolean',
        'errorSettings.include_stack_trace' => 'boolean',
        'errorSettings.dedupe_window_hours' => 'integer|min:1|max:720',
        'errorSettings.stack_trace_limit' => 'integer|min:1|max:200',
    ];

    // Package editing
    public bool $editingPackage = false;
    public string $editPackageName = '';
    public string $editPackageDescription = '';
    public ?int $editPackageUserInChargeId = null;
    public string $editPackageIcon = '';

    public function mount(DevPackage $package): void
    {
        $this->package = $package;
    }

    public function startEditingPackage(): void
    {
        $this->editPackageName = $this->package->name;
        $this->editPackageDescription = $this->package->description ?? '';
        $this->editPackageUserInChargeId = $this->package->user_in_charge_id;
        $this->editPackageIcon = $this->package->icon ?? '';
        $this->editingPackage = true;
    }

    public function savePackage(): void
    {
        if (trim($this->editPackageName) === '') {
            return;
        }

        $this->package->update([
            'name' => trim($this->editPackageName),
            'description' => trim($this->editPackageDescription) ?: null,
            'user_in_charge_id' => $this->editPackageUserInChargeId ?: null,
            'icon' => trim($this->editPackageIcon) ?: null,
        ]);

        $this->package->refresh();
        $this->editingPackage = false;
    }

    public function cancelEditPackage(): void
    {
        $this->editingPackage = false;
    }

    // --- Board Management ---

    public bool $showCreateBoardModal = false;
    public string $newBoardName = '';
    public string $newBoardDescription = '';

    public function createBoard(): void
    {
        $name = trim($this->newBoardName);
        if ($name === '') {
            return;
        }

        $user = Auth::user();
        $team = $user->currentTeam;

        $boardService = new DevBoardService();
        $board = $boardService->createBoard([
            'name' => $name,
            'type' => 'feature',
            'description' => trim($this->newBoardDescription) ?: null,
            'dev_package_id' => $this->package->id,
            'team_id' => $team->id,
            'created_by_user_id' => $user->id,
            'order' => $this->package->boards()->count(),
        ]);

        $this->showCreateBoardModal = false;
        $this->newBoardName = '';
        $this->newBoardDescription = '';

        $this->dispatch('updateSidebar');

        $this->redirect(route('dev.packages.boards.show', [$this->package, $board]), navigate: true);
    }

    public function archiveBoard(int $boardId): void
    {
        $board = DevBoard::where('dev_package_id', $this->package->id)->find($boardId);
        if (!$board) {
            return;
        }

        $boardService = new DevBoardService();
        $boardService->archiveBoard($board);

        $this->dispatch('updateSidebar');
    }

    public function reactivateBoard(int $boardId): void
    {
        $board = DevBoard::where('dev_package_id', $this->package->id)->find($boardId);
        if (!$board) {
            return;
        }

        $boardService = new DevBoardService();
        $boardService->reactivateBoard($board);

        $this->dispatch('updateSidebar');
    }

    // --- Error Tracking Settings ---

    public bool $showErrorSettings = false;
    public ?DevPackageErrorSettings $errorSettings = null;
    public array $availableHttpCodes = [400, 401, 403, 404, 500, 502, 503, 504];

    public function openErrorSettings(): void
    {
        $this->errorSettings = DevPackageErrorSettings::getOrCreateForPackage($this->package);
        $this->showErrorSettings = true;
    }

    public function saveErrorSettings(): void
    {
        if (!$this->errorSettings) {
            return;
        }

        $this->errorSettings->save();
        $this->showErrorSettings = false;
    }

    public function toggleHttpCode(int $code): void
    {
        if (!$this->errorSettings) {
            return;
        }

        $codes = $this->errorSettings->capture_codes ?? DevPackageErrorSettings::DEFAULT_CAPTURE_CODES;

        if (in_array($code, $codes, true)) {
            $codes = array_values(array_diff($codes, [$code]));
        } else {
            $codes[] = $code;
        }

        $this->errorSettings->capture_codes = $codes;
    }

    public function isHttpCodeEnabled(int $code): bool
    {
        if (!$this->errorSettings) {
            return false;
        }

        $codes = $this->errorSettings->capture_codes ?? DevPackageErrorSettings::DEFAULT_CAPTURE_CODES;

        return in_array($code, $codes, true);
    }

    public function resolveOccurrence(int $occurrenceId): void
    {
        $occurrence = DevErrorOccurrence::where('dev_package_id', $this->package->id)->find($occurrenceId);
        if ($occurrence) {
            $occurrence->resolve(Auth::id());
        }
    }

    public function ignoreOccurrence(int $occurrenceId): void
    {
        $occurrence = DevErrorOccurrence::where('dev_package_id', $this->package->id)->find($occurrenceId);
        if ($occurrence) {
            $occurrence->ignore();
        }
    }

    public function rendered(): void
    {
        // Comms - Communication/Channel Integration
        $this->dispatch('comms', [
            'model' => get_class($this->package),
            'modelId' => $this->package->id,
            'subject' => $this->package->name,
            'description' => $this->package->description ?? '',
            'url' => route('dev.packages.show', $this->package),
            'source' => 'dev.package.view',
            'recipients' => array_filter([$this->package->user_in_charge_id]),
            'capabilities' => [
                'manage_channels' => true,
                'threads' => false,
            ],
            'meta' => [
                'status' => $this->package->status,
                'github_repo' => $this->package->github_repo_full_name,
                'created_at' => $this->package->created_at,
            ],
        ]);

        // Terminal Activity + Files + Tags
        $this->dispatch('terminal:app:activity');
        $this->dispatch('terminal:app:files');
        $this->dispatch('terminal:app:tags');

        // Organization - Time Tracking + Entity Linking + Dimensions
        $this->dispatch('organization', [
            'context_type' => get_class($this->package),
            'context_id' => $this->package->id,
            'allow_time_entry' => true,
            'allow_entities' => true,
            'allow_dimensions' => true,
            'include_children_relations' => ['boards.issues'],
        ]);

        // ExtraFields
        $this->dispatch('extrafields', [
            'context_type' => get_class($this->package),
            'context_id' => $this->package->id,
        ]);
    }

    public string $lockReason = '';

    public function lockPackage(): void
    {
        $this->package->update([
            'locked_by_user_id' => Auth::id(),
            'locked_at' => now(),
            'lock_reason' => trim($this->lockReason) ?: null,
        ]);
        $this->lockReason = '';
        $this->package->refresh();
    }

    public function unlockPackage(): void
    {
        $this->package->update([
            'locked_by_user_id' => null,
            'locked_at' => null,
            'lock_reason' => null,
        ]);
        $this->package->refresh();
    }

    public function render()
    {
        $this->package->load(['userInCharge', 'lockedByUser']);
        $boards = $this->package->boards()
            ->active()
            ->withCount(['issues as open_issues_count' => fn ($q) => $q->where('status', 'open')])
            ->orderBy('order')
            ->get();

        $archivedBoards = $this->package->boards()
            ->archived()
            ->withCount(['issues as open_issues_count' => fn ($q) => $q->where('status', 'open')])
            ->orderBy('order')
            ->get();

        $packageIssues = DevIssue::whereHas('board', fn ($q) => $q->where('dev_package_id', $this->package->id));

        $totalOpen = (clone $packageIssues)->where('status', 'open')->count();
        $totalDone = (clone $packageIssues)->where('is_done', true)->count();
        $totalOverdue = (clone $packageIssues)
            ->where('status', 'open')
            ->whereNotNull('due_date')
            ->where('due_date', '<', now())
            ->count();
        $totalHighPriority = (clone $packageIssues)->where('status', 'open')->where('priority', 'high')->count();

        // Recent open issues for this package
        $recentIssues = DevIssue::whereHas('board', fn ($q) => $q->where('dev_package_id', $this->package->id))
            ->where('status', 'open')
            ->with(['board', 'userInCharge'])
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        // Recently completed
        $recentlyDone = DevIssue::whereHas('board', fn ($q) => $q->where('dev_package_id', $this->package->id))
            ->where('is_done', true)
            ->with(['board'])
            ->orderByDesc('done_at')
            ->limit(5)
            ->get();

        // GitHub data for this package
        $repoIds = collect();
        if ($this->package->github_repo_full_name) {
            $repoIds = IntegrationGithubRepo::where('full_name', $this->package->github_repo_full_name)
                ->where('is_active', true)
                ->pluck('id');
        }

        $recentCommits = $repoIds->isNotEmpty()
            ? IntegrationGithubCommit::whereIn('repo_id', $repoIds)
                ->orderByDesc('committed_at')
                ->limit(15)
                ->get()
            : collect();

        $openPullRequests = $repoIds->isNotEmpty()
            ? IntegrationGithubPullRequest::whereIn('repo_id', $repoIds)
                ->where('state', 'open')
                ->orderByDesc('github_created_at')
                ->limit(10)
                ->get()
            : collect();

        // Error occurrences
        $errorOccurrences = DevErrorOccurrence::where('dev_package_id', $this->package->id)
            ->whereIn('status', [DevErrorOccurrence::STATUS_OPEN, DevErrorOccurrence::STATUS_ACKNOWLEDGED])
            ->orderByDesc('last_seen_at')
            ->limit(20)
            ->get();

        $errorSettings = $this->package->errorSettings;

        // Documentation page count (for tab badge)
        $docPageCount = $this->package->docPages()->count();

        // Team members for user assignment
        $teamUsers = Auth::user()
            ->currentTeam
            ->users()
            ->orderBy('name')
            ->get()
            ->map(fn ($user) => ['id' => $user->id, 'name' => $user->fullname ?? $user->name]);

        return view('dev::livewire.package.show', [
            'boards' => $boards,
            'archivedBoards' => $archivedBoards,
            'totalOpen' => $totalOpen,
            'totalDone' => $totalDone,
            'totalOverdue' => $totalOverdue,
            'totalHighPriority' => $totalHighPriority,
            'recentIssues' => $recentIssues,
            'recentlyDone' => $recentlyDone,
            'recentCommits' => $recentCommits,
            'openPullRequests' => $openPullRequests,
            'teamUsers' => $teamUsers,
            'errorOccurrences' => $errorOccurrences,
            'errorSettingsEnabled' => $errorSettings?->enabled ?? false,
            'docPageCount' => $docPageCount,
        ])->layout('platform::layouts.app');
    }
}
