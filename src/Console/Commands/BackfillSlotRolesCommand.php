<?php

namespace Platform\Dev\Console\Commands;

use Illuminate\Console\Command;
use Platform\Dev\Models\DevBoard;
use Platform\Dev\Services\DevPackageService;

class BackfillSlotRolesCommand extends Command
{
    protected $signature = 'dev:backfill-slot-roles
                            {--package= : Optional einzelne Package-ID}
                            {--board= : Optional einzelne Board-ID}
                            {--force : Auch bereits gesetzte Rollen ueberschreiben}';

    protected $description = 'Weist bestehenden Board-Slots die Worker-Rollen per Konvention zu (nach Slot-Reihenfolge).';

    public function handle(): int
    {
        // [null, 'ready', 'working', 'human', 'done'] — Standard-Reihenfolge
        $roles = array_column(DevPackageService::DEFAULT_SLOTS, 'role');

        $query = DevBoard::query()->with('slots');
        if ($packageId = $this->option('package')) {
            $query->where('dev_package_id', $packageId);
        }
        if ($boardId = $this->option('board')) {
            $query->where('id', $boardId);
        }

        $boards = $query->get();
        if ($boards->isEmpty()) {
            $this->info('Keine Boards gefunden.');

            return self::SUCCESS;
        }

        $force = (bool) $this->option('force');
        $touched = 0;

        foreach ($boards as $board) {
            $slots = $board->slots->sortBy('order')->values();
            foreach ($slots as $i => $slot) {
                if (! $force && $slot->agent_role !== null) {
                    continue;
                }
                // Slots jenseits des Standard-Satzes bekommen keine Rolle.
                $slot->update(['agent_role' => $roles[$i] ?? null]);
                $touched++;
            }
            $this->line("Board #{$board->id} ({$board->name}): {$slots->count()} Slots");
        }

        $this->info("Fertig — {$touched} Slot-Rolle(n) gesetzt.");

        return self::SUCCESS;
    }
}
