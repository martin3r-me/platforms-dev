<?php

namespace Platform\Dev\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Platform\Dev\Models\DevPackage;
use Platform\Dev\Services\DevPackageSnapshotService;

class BuildPackageSnapshotsCommand extends Command
{
    protected $signature = 'dev:build-package-snapshots
                            {--package= : Optional einzelne Package-ID}
                            {--team= : Optional auf ein Team beschraenken}
                            {--trigger=cron : Snapshot-Trigger-Label (cron|manual|backfill)}';

    protected $description = 'Erstellt fuer alle Dev-Packages einen Tages-Snapshot (max 1/Tag/Package).';

    public function handle(DevPackageSnapshotService $service): int
    {
        $query = DevPackage::query();

        if ($packageId = $this->option('package')) {
            $query->where('id', $packageId);
        }
        if ($teamId = $this->option('team')) {
            $query->where('team_id', $teamId);
        }

        $trigger = (string) ($this->option('trigger') ?? 'cron');

        $packages = $query->get();
        $total = $packages->count();

        if ($total === 0) {
            $this->info('Keine Dev-Packages gefunden.');
            return self::SUCCESS;
        }

        $this->info("Snapshotte {$total} Package(s) — Trigger: {$trigger}");

        $ok = 0;
        $failed = 0;

        foreach ($packages as $package) {
            try {
                $snapshot = $service->snapshot($package, $trigger);
                $ok++;
                $this->line(sprintf(
                    '  ✓ #%d %s — health=%s (%s), confidence=%d',
                    $package->id,
                    mb_substr((string) ($package->name ?? '—'), 0, 60),
                    $snapshot->health_score ?? '–',
                    $snapshot->health_color ?? 'gray',
                    $snapshot->confidence_score,
                ));
            } catch (\Throwable $e) {
                $failed++;
                $this->error(sprintf(
                    '  ✗ #%d %s — %s',
                    $package->id,
                    mb_substr((string) ($package->name ?? '—'), 0, 60),
                    $e->getMessage(),
                ));
                Log::error('[dev:build-package-snapshots] Snapshot fehlgeschlagen', [
                    'package_id' => $package->id,
                    'exception' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }

        $this->info("Fertig: {$ok} OK, {$failed} Fehler.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
