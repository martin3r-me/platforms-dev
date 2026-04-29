<?php

namespace Platform\Dev\Livewire\Package;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Platform\Dev\Models\DevPackage;
use Platform\Dev\Models\DevBoard;
use Platform\Dev\Models\DevBoardSlot;
use Platform\Dev\Models\DevIssue;
use Platform\Dev\Services\DevBoardService;
use Platform\Dev\Services\DevIssueService;

class Board extends Component
{
    public DevPackage $package;
    public DevBoard $board;
    public $groups;
    public bool $showDone = false;

    // Slot settings
    public ?int $editingSlotId = null;
    public string $editSlotName = '';
    public bool $showSlotSettings = false;

    // Board settings
    public bool $showBoardSettings = false;
    public string $editBoardName = '';
    public string $editBoardDescription = '';

    public function mount(DevPackage $package, DevBoard $board): void
    {
        $this->package = $package;
        $this->board = $board;
        $this->loadGroups();
    }

    public function rendered(): void
    {
        // Comms - Communication/Channel Integration (Package-level)
        $this->dispatch('comms', [
            'model' => get_class($this->package),
            'modelId' => $this->package->id,
            'subject' => $this->package->name . ' – ' . $this->board->name,
            'description' => $this->board->description ?? '',
            'url' => route('dev.packages.boards.show', [$this->package, $this->board]),
            'source' => 'dev.board.view',
            'recipients' => array_filter([$this->package->user_in_charge_id]),
            'capabilities' => [
                'manage_channels' => true,
                'threads' => false,
            ],
            'meta' => [
                'board' => $this->board->name,
                'board_type' => $this->board->type instanceof \BackedEnum ? $this->board->type->value : $this->board->type,
                'package' => $this->package->name,
            ],
        ]);

        // Terminal Activity + Files + Tags
        $this->dispatch('terminal:app:activity');
        $this->dispatch('terminal:app:files');
        $this->dispatch('terminal:app:tags');

        // Organization - Time Tracking + Entity Linking
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

    public function loadGroups(): void
    {
        $this->groups = collect();
        $eagerLoad = ['userInCharge', 'createdBy', 'board'];

        // Slots (Issues ohne Slot werden dem ersten Slot zugerechnet oder separat in Sidebar angezeigt)
        $slots = $this->board->slots()->orderBy('order')->get();

        // Backlog-Issues (ohne Slot) dem ersten Slot voranstellen
        $backlogIssues = $this->board->issues()
            ->with($eagerLoad)
            ->whereNull('dev_board_slot_id')
            ->where('is_done', false)
            ->orderByDesc('created_at')
            ->get();

        $slots->each(function ($slot, $index) use ($eagerLoad, $backlogIssues) {
            $slot->label = $slot->name;
            $slot->isBacklog = false;
            $slotIssues = $slot->issues()
                ->with($eagerLoad)
                ->where('is_done', false)
                ->orderBy('slot_order')
                ->get();

            // Backlog-Issues in die erste Spalte einfügen
            if ($index === 0 && $backlogIssues->isNotEmpty()) {
                $slot->tasks = $backlogIssues->merge($slotIssues);
            } else {
                $slot->tasks = $slotIssues;
            }

            $this->groups->push($slot);
        });

        // Erledigt
        $doneGroup = new DevBoardSlot();
        $doneGroup->id = 'done';
        $doneGroup->name = 'ERLEDIGT';
        $doneGroup->label = 'ERLEDIGT';
        $doneGroup->isDoneGroup = true;
        $doneGroup->tasks = $this->board->issues()
            ->with($eagerLoad)
            ->where('is_done', true)
            ->orderByDesc('done_at')
            ->get();
        $this->groups->push($doneGroup);
    }

    public function createIssue(?string $slotId = null): void
    {
        $user = Auth::user();
        $team = $user->currentTeam;
        if (!$team) {
            return;
        }

        $resolvedSlotId = ($slotId === 'backlog' || $slotId === null || $slotId === 'null')
            ? null
            : (int) $slotId;

        $service = new DevIssueService();
        $service->createIssue([
            'team_id' => $team->id,
            'created_by_user_id' => $user->id,
            'dev_board_id' => $this->board->id,
            'dev_board_slot_id' => $resolvedSlotId,
            'title' => 'Neues Issue',
        ]);

        $this->loadGroups();
    }

    public function createBoardSlot(): void
    {
        DevBoardSlot::create([
            'team_id' => $this->board->team_id,
            'created_by_user_id' => Auth::id(),
            'dev_board_id' => $this->board->id,
            'name' => 'Neue Spalte',
            'order' => $this->board->slots()->count(),
        ]);

        $this->loadGroups();
    }

    public function updateIssueOrder($groups): void
    {
        foreach ($groups as $group) {
            $slotId = ($group['value'] === 'null' || $group['value'] === 'backlog' || (int) $group['value'] === 0)
                ? null
                : (int) $group['value'];

            foreach ($group['items'] as $item) {
                $issue = DevIssue::find($item['value']);
                if (!$issue) {
                    continue;
                }

                $newSlotId = null;
                if ($slotId !== null && $slotId !== 'done') {
                    $slot = $this->board->slots()->find($slotId);
                    if ($slot) {
                        $newSlotId = $slot->id;
                    }
                }

                $issue->dev_board_slot_id = $newSlotId;
                $issue->slot_order = $item['order'];
                $issue->order = $item['order'];
                $issue->save();
            }
        }

        $this->loadGroups();
    }

    public function updateSlotOrder($groups): void
    {
        foreach ($groups as $slotGroup) {
            $slot = DevBoardSlot::find($slotGroup['value']);
            if ($slot) {
                $slot->order = $slotGroup['order'];
                $slot->save();
            }
        }

        $this->loadGroups();
    }

    public function deleteIssue(int $issueId): void
    {
        $issue = DevIssue::where('dev_board_id', $this->board->id)->findOrFail($issueId);
        $issue->delete();
        $this->loadGroups();
    }

    public function toggleShowDone(): void
    {
        $this->showDone = !$this->showDone;
    }

    public function openSlotSettings(int $slotId): void
    {
        $slot = DevBoardSlot::find($slotId);
        if (!$slot || $slot->dev_board_id !== $this->board->id) {
            return;
        }

        $this->editingSlotId = $slotId;
        $this->editSlotName = $slot->name;
        $this->showSlotSettings = true;
    }

    public function saveSlotSettings(): void
    {
        if (!$this->editingSlotId || trim($this->editSlotName) === '') {
            return;
        }

        $slot = DevBoardSlot::find($this->editingSlotId);
        if ($slot && $slot->dev_board_id === $this->board->id) {
            $slot->update(['name' => trim($this->editSlotName)]);
        }

        $this->showSlotSettings = false;
        $this->editingSlotId = null;
        $this->loadGroups();
    }

    public function deleteSlot(): void
    {
        if (!$this->editingSlotId) {
            return;
        }

        $slot = DevBoardSlot::find($this->editingSlotId);
        if ($slot && $slot->dev_board_id === $this->board->id) {
            // Move issues from this slot to no slot (backlog)
            DevIssue::where('dev_board_slot_id', $slot->id)
                ->update(['dev_board_slot_id' => null]);

            $slot->delete();
        }

        $this->showSlotSettings = false;
        $this->editingSlotId = null;
        $this->loadGroups();
    }

    public function openBoardSettings(): void
    {
        $this->editBoardName = $this->board->name;
        $this->editBoardDescription = $this->board->description ?? '';
        $this->showBoardSettings = true;
    }

    public function saveBoardSettings(): void
    {
        $name = trim($this->editBoardName);
        if ($name === '') {
            return;
        }

        $boardService = new DevBoardService();
        $boardService->updateBoard($this->board, [
            'name' => $name,
            'description' => trim($this->editBoardDescription) ?: null,
        ]);

        $this->board->refresh();
        $this->showBoardSettings = false;
        $this->dispatch('updateSidebar');
    }

    public function archiveBoard(): void
    {
        $boardService = new DevBoardService();
        $boardService->archiveBoard($this->board);

        $this->dispatch('updateSidebar');

        $this->redirect(route('dev.packages.show', $this->package), navigate: true);
    }

    public function render()
    {
        // Always reload groups to ensure dynamic `tasks` property is present
        // (Livewire hydration strips dynamic properties from Eloquent models)
        $this->loadGroups();

        $openCount = $this->groups
            ->filter(fn ($g) => !($g->isDoneGroup ?? false))
            ->sum(fn ($g) => ($g->tasks ?? collect())->count());

        $doneCount = $this->groups
            ->filter(fn ($g) => $g->isDoneGroup ?? false)
            ->sum(fn ($g) => ($g->tasks ?? collect())->count());

        $allBoards = $this->package->boards()
            ->active()
            ->withCount(['issues as open_issues_count' => fn ($q) => $q->where('status', 'open')])
            ->orderBy('order')
            ->get();

        return view('dev::livewire.package.board', [
            'openCount' => $openCount,
            'doneCount' => $doneCount,
            'allBoards' => $allBoards,
        ])->layout('platform::layouts.app');
    }
}
