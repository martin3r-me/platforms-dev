<?php

namespace Platform\Dev\Livewire\Package;

use Illuminate\Database\Eloquent\Model;
use Livewire\Component;
use Platform\Core\Livewire\Concerns\HasPageContext;
use Illuminate\Support\Facades\Auth;
use Platform\Dev\Models\DevPackage;
use Platform\Dev\Models\DevDiscussion;
use Platform\Dev\Models\DevDiscussionReply;

class Discussions extends Component
{
    use HasPageContext;

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

    protected function pageContext(): ?Model
    {
        return $this->package;
    }

    protected function pageContextOptions(): array
    {
        return [
            'subject' => $this->package->name,
            'url' => route('dev.packages.show', $this->package),
            'source' => 'dev.discussions.view',
            'recipients' => array_filter([$this->package->user_in_charge_id]),
            'capabilities' => ['manage_channels' => true, 'threads' => false],
            'meta' => ['package' => $this->package->name],
        ];
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
