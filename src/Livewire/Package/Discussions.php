<?php

namespace Platform\Dev\Livewire\Package;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Platform\Dev\Models\DevPackage;
use Platform\Dev\Models\DevDiscussion;
use Platform\Dev\Models\DevDiscussionReply;

class Discussions extends Component
{
    public DevPackage $package;

    public ?int $activeDiscussionId = null;
    public string $newTitle = '';
    public string $newBody = '';
    public string $replyBody = '';
    public bool $showCreateForm = false;

    public function mount(DevPackage $package): void
    {
        $this->package = $package;
    }

    public function rendered(): void
    {
        // Comms - Communication/Channel Integration (Package-level)
        $this->dispatch('comms', [
            'model' => get_class($this->package),
            'modelId' => $this->package->id,
            'subject' => $this->package->name . ' – Diskussionen',
            'description' => $this->package->description ?? '',
            'url' => route('dev.packages.discussions', $this->package),
            'source' => 'dev.discussions.view',
            'recipients' => array_filter([$this->package->user_in_charge_id]),
            'capabilities' => [
                'manage_channels' => true,
                'threads' => false,
            ],
            'meta' => [
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

    public function selectDiscussion(int $id): void
    {
        $this->activeDiscussionId = $id;
        $this->replyBody = '';
    }

    public function createDiscussion(): void
    {
        $user = Auth::user();
        $team = $user->currentTeam;

        if (!$team || trim($this->newTitle) === '') {
            return;
        }

        $discussion = DevDiscussion::create([
            'team_id' => $team->id,
            'created_by_user_id' => $user->id,
            'dev_package_id' => $this->package->id,
            'title' => trim($this->newTitle),
            'body' => trim($this->newBody) ?: null,
        ]);

        $this->newTitle = '';
        $this->newBody = '';
        $this->showCreateForm = false;
        $this->activeDiscussionId = $discussion->id;
    }

    public function reply(): void
    {
        $user = Auth::user();
        $team = $user->currentTeam;

        if (!$team || !$this->activeDiscussionId || trim($this->replyBody) === '') {
            return;
        }

        $discussion = DevDiscussion::find($this->activeDiscussionId);
        if (!$discussion || $discussion->is_locked) {
            return;
        }

        DevDiscussionReply::create([
            'team_id' => $team->id,
            'created_by_user_id' => $user->id,
            'dev_discussion_id' => $discussion->id,
            'body' => trim($this->replyBody),
        ]);

        $this->replyBody = '';
    }

    public function render()
    {
        $discussions = $this->package->discussions()
            ->withCount('replies')
            ->with('createdBy')
            ->get();

        $activeDiscussion = null;
        $replies = collect();

        if ($this->activeDiscussionId) {
            $activeDiscussion = DevDiscussion::with(['createdBy', 'rootReplies.createdBy', 'rootReplies.children.createdBy'])
                ->find($this->activeDiscussionId);
            if ($activeDiscussion) {
                $replies = $activeDiscussion->rootReplies;
            }
        }

        return view('dev::livewire.package.discussions', [
            'discussions' => $discussions,
            'activeDiscussion' => $activeDiscussion,
            'replies' => $replies,
        ])->layout('platform::layouts.app');
    }
}
