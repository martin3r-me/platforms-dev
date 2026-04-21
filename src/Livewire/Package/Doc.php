<?php

namespace Platform\Dev\Livewire\Package;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Platform\Dev\Models\DevPackage;
use Platform\Dev\Models\DevDocPage;
use Platform\Dev\Models\DevDocRevision;

class Doc extends Component
{
    public DevPackage $package;
    public DevDocPage $docPage;

    public string $title = '';
    public string $content = '';
    public string $status = 'draft';

    public function mount(DevPackage $package, DevDocPage $docPage): void
    {
        $this->package = $package;
        $this->docPage = $docPage;

        $this->title = $docPage->title;
        $this->content = $docPage->content ?? '';
        $this->status = $docPage->status ?? 'draft';
    }

    public function updateTitle(): void
    {
        $title = trim($this->title);
        if ($title === '' || $title === $this->docPage->title) {
            return;
        }

        $this->docPage->update([
            'title' => $title,
            'last_edited_by_user_id' => Auth::id(),
        ]);

        $this->docPage->refresh();
    }

    public function updateContent(): void
    {
        if ($this->content === ($this->docPage->content ?? '')) {
            return;
        }

        $oldContent = $this->docPage->content;
        $oldTitle = $this->docPage->title;

        // Create revision before updating
        $lastVersion = $this->docPage->revisions()->max('version') ?? 0;

        DevDocRevision::create([
            'dev_doc_page_id' => $this->docPage->id,
            'version' => $lastVersion + 1,
            'title' => $oldTitle,
            'content' => $oldContent,
            'change_summary' => null,
            'created_by_user_id' => Auth::id(),
        ]);

        $this->docPage->update([
            'content' => $this->content,
            'last_edited_by_user_id' => Auth::id(),
        ]);

        $this->docPage->refresh();
    }

    public function toggleStatus(): void
    {
        $newStatus = $this->docPage->status === 'published' ? 'draft' : 'published';

        $this->docPage->update([
            'status' => $newStatus,
            'last_edited_by_user_id' => Auth::id(),
        ]);

        $this->status = $newStatus;
        $this->docPage->refresh();
    }

    public function render()
    {
        $docPages = $this->package->docPages()
            ->orderBy('position')
            ->orderBy('title')
            ->get();

        $revisions = $this->docPage->revisions()
            ->with('createdBy:id,name')
            ->orderByDesc('version')
            ->limit(20)
            ->get();

        return view('dev::livewire.package.doc', [
            'docPages' => $docPages,
            'revisions' => $revisions,
        ])->layout('platform::layouts.app');
    }
}
