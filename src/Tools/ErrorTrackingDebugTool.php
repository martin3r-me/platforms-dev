<?php

namespace Platform\Dev\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Services\ErrorReporterRegistry;
use Platform\Dev\Models\DevErrorOccurrence;
use Platform\Dev\Models\DevPackage;
use Platform\Dev\Models\DevPackageErrorSettings;
use Platform\Dev\Tools\Concerns\ResolvesDevTeam;

class ErrorTrackingDebugTool implements ToolContract, ToolMetadataContract
{
    use ResolvesDevTeam;

    public function getName(): string
    {
        return 'dev.error_tracking.debug';
    }

    public function getDescription(): string
    {
        return 'Diagnostiziert das Error-Tracking-System: zeigt registrierte Packages, Endpoint-Konfiguration, Error-Settings pro Package, offene Occurrences und ENV-Status.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'team_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Team-ID. Default: aktuelles Team.',
                ],
                'package_id' => [
                    'type' => 'integer',
                    'nullable' => true,
                    'description' => 'Optional: Filtert auf ein bestimmtes Package.',
                ],
            ],
            'required' => [],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $resolved = $this->resolveTeam($arguments, $context);
            if ($resolved['error']) {
                return $resolved['error'];
            }
            $teamId = (int) $resolved['team_id'];

            $lines = [];
            $lines[] = '=== Error Tracking Debug ===';
            $lines[] = "Team: #{$teamId}";
            $lines[] = "Zeitpunkt: " . now()->toIso8601String();
            $lines[] = '';

            // --- Sending Side: ErrorReporterRegistry ---
            $lines[] = '--- SENDING SIDE (ErrorReporterRegistry) ---';
            try {
                $registry = resolve(ErrorReporterRegistry::class);
                $keys = $registry->registeredKeys();
                $endpoint = $registry->getEndpoint();

                $lines[] = 'Registrierte Packages: ' . (empty($keys) ? '(keine)' : implode(', ', $keys));
                $lines[] = 'DEV_ERROR_ENDPOINT: ' . ($endpoint ? 'gesetzt (' . substr($endpoint, 0, 50) . '...)' : 'NICHT gesetzt');

                if (empty($keys)) {
                    $lines[] = 'HINWEIS: Packages registrieren sich automatisch beim Boot.';
                    $lines[] = '         Wenn leer, fehlt DEV_ERROR_ENDPOINT in der .env (wird erst lazy geladen).';
                }
                if (!$endpoint && !empty($keys)) {
                    $lines[] = 'PROBLEM: Packages registriert aber kein Endpoint! Setze DEV_ERROR_ENDPOINT in .env.';
                }
            } catch (\Throwable $e) {
                $lines[] = 'Registry nicht verfuegbar: ' . $e->getMessage();
            }
            $lines[] = '';

            // --- Receiving Side: Ingest Token ---
            $lines[] = '--- RECEIVING SIDE (Ingest API) ---';

            // Find any active ingest token for this team
            $activeTokenSettings = DevPackageErrorSettings::whereHas('package', function ($q) use ($teamId) {
                $q->where('team_id', $teamId);
            })->whereNotNull('ingest_token')->where('enabled', true)->first();

            if ($activeTokenSettings) {
                $ingestUrl = $activeTokenSettings->getIngestUrl();
                $lines[] = 'Ingest-URL: ' . ($ingestUrl ?? '(nicht verfuegbar)');
                $lines[] = 'Token-Owner: ' . ($activeTokenSettings->package->name ?? '?');
                $lines[] = '';
                if ($ingestUrl) {
                    $lines[] = '.env Konfiguration fuer sendende Instanzen:';
                    $lines[] = '  DEV_ERROR_ENDPOINT=' . $ingestUrl;
                }
            } else {
                $lines[] = 'Kein aktiver Ingest-Token gefunden.';
                $lines[] = 'TIPP: Generiere einen Token in den Error-Settings eines Packages.';
            }
            $lines[] = '';

            // --- Packages ---
            $query = DevPackage::where('team_id', $teamId)->with('errorSettings');
            if (!empty($arguments['package_id'])) {
                $query->where('id', (int) $arguments['package_id']);
            }
            $packages = $query->orderBy('name')->get();

            $lines[] = '--- PACKAGES ---';
            foreach ($packages as $package) {
                $settings = $package->errorSettings;
                $openCount = DevErrorOccurrence::where('dev_package_id', $package->id)
                    ->where('status', DevErrorOccurrence::STATUS_OPEN)
                    ->count();

                $lastOccurrence = DevErrorOccurrence::where('dev_package_id', $package->id)
                    ->orderByDesc('last_seen_at')
                    ->first();

                $lines[] = '';
                $lines[] = '  ' . $package->name . ' (ID ' . $package->id . ')';

                if ($settings) {
                    $lines[] = '    enabled=' . ($settings->enabled ? 'ja' : 'nein')
                        . ' | auto_issue=' . ($settings->auto_create_issue ? 'ja' : 'nein')
                        . ' | dedupe=' . $settings->dedupe_window_hours . 'h'
                        . ' | console=' . ($settings->capture_console_errors ? 'ja' : 'nein');
                    $lines[] = '    capture_codes: ' . implode(', ', $settings->getCaptureCodes());
                } else {
                    $lines[] = '    Error Settings: nicht konfiguriert';
                }

                $lines[] = '    Offene Errors: ' . $openCount;

                if ($lastOccurrence) {
                    $lines[] = '    Letzter: ' . ($lastOccurrence->getShortExceptionClass() ?? '-')
                        . ' (' . ($lastOccurrence->last_seen_at?->diffForHumans() ?? '-') . ')'
                        . ' x' . $lastOccurrence->occurrence_count;
                }
            }

            if ($packages->isEmpty()) {
                $lines[] = '  (keine Packages gefunden)';
            }

            $lines[] = '';

            return ToolResult::success([
                'output' => implode("\n", $lines),
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler bei Error-Tracking-Diagnose: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'debug',
            'tags' => ['dev', 'error-tracking', 'debug', 'diagnostics'],
            'read_only' => true,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'safe',
            'idempotent' => true,
        ];
    }
}
