<?php

namespace Platform\Dev\Livewire;

use Livewire\Component;
use Livewire\Attributes\On;
use Platform\Dev\Models\DevPackage;

class Sidebar extends Component
{
    #[On('updateSidebar')]
    public function updateSidebar()
    {
    }

    public function render()
    {
        $user = auth()->user();

        if (!$user) {
            return view('dev::livewire.sidebar', [
                'activePackages' => collect(),
                'archivedPackages' => collect(),
            ]);
        }

        $teamId = $user->currentTeam->id ?? null;

        $activePackages = $teamId
            ? DevPackage::where('team_id', $teamId)
                ->where('status', 'active')
                ->orderBy('order')
                ->get()
            : collect();

        $archivedPackages = $teamId
            ? DevPackage::where('team_id', $teamId)->where('status', 'archived')->orderBy('name')->get()
            : collect();

        return view('dev::livewire.sidebar', [
            'activePackages' => $activePackages,
            'archivedPackages' => $archivedPackages,
        ]);
    }
}
