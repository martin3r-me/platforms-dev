<?php

namespace Platform\Dev\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Services\ErrorReporterRegistry;
use Platform\Dev\Models\DevErrorOccurrence;
use Platform\Dev\Models\DevPackage;
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
        return 'Diagnostiziert das Error-Tracking-System: zeigt registrierte Reporter, Error-Settings pro Package, offene Occurrences, Ingest-URLs und ENV-Status.';
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

            $query = DevPackage::where('team_id', $teamId)->with('errorSettings');

            if (!empty($arguments['package_id'])) {
                $query->where('id', (int) $arguments['package_id']);
            }

            $packages = $query->orderBy('name')->get();

            if ($packages->isEmpty()) {
                return ToolResult::success([
                    'output' => "=== Error Tracking Debug ===\n\nKeine Packages gefunden" .
                        (!empty($arguments['package_id']) ? " (package_id={$arguments['package_id']})" : '') .
                        " fuer Team #{$teamId}.",
                ]);
            }

            $lines = [];
            $lines[] = '=== Error Tracking Debug ===';
            $lines[] = "Team: #{$teamId}";
            $lines[] = "Zeitpunkt: " . now()->toIso8601String();
            $lines[] = '';

            // --- ErrorReporterRegistry Status ---
            $lines[] = '--- ErrorReporterRegistry ---';
            try {
                $registry = resolve(ErrorReporterRegistry::class);
                $keys = $registry->registeredKeys();
                if (empty($keys)) {
                    $lines[] = 'Registrierte Keys: (keine)';
                } else {
                    $lines[] = 'Registrierte Keys: ' . implode(', ', $keys);
                }
            } catch (\Throwable $e) {
                $lines[] = 'Registry nicht verfuegbar: ' . $e->getMessage();
            }
            $lines[] = '';

            // --- Packages ---
            foreach ($packages as $package) {
                $lines[] = '--- Package: ' . $package->name . ' (ID ' . $package->id . ') ---';

                $settings = $package->errorSettings;

                if (!$settings) {
                    $lines[] = '  Error Settings: nicht konfiguriert';
                } else {
                    $maskedToken = $settings->ingest_token
                        ? substr($settings->ingest_token, 0, 8) . '...'
                        : '(nicht gesetzt)';

                    $lines[] = '  Error Settings:';
                    $lines[] = '    enabled:                ' . ($settings->enabled ? 'ja' : 'nein');
                    $lines[] = '    ingest_token:           ' . $maskedToken;
                    $lines[] = '    ingest_url:             ' . ($settings->getIngestUrl() ?? '(nicht verfuegbar)');
                    $lines[] = '    capture_console_errors: ' . ($settings->capture_console_errors ? 'ja' : 'nein');
                    $lines[] = '    auto_create_issue:      ' . ($settings->auto_create_issue ? 'ja' : 'nein');
                    $lines[] = '    dedupe_window_hours:    ' . $settings->dedupe_window_hours;
                    $lines[] = '    include_stack_trace:    ' . ($settings->include_stack_trace ? 'ja' : 'nein');
                    $lines[] = '    capture_codes:          ' . implode(', ', $settings->getCaptureCodes());
                }

                // Open occurrences
                $openCount = DevErrorOccurrence::where('dev_package_id', $package->id)
                    ->where('status', DevErrorOccurrence::STATUS_OPEN)
                    ->count();

                $lines[] = '  Offene Occurrences: ' . $openCount;

                // Last error occurrence
                $lastOccurrence = DevErrorOccurrence::where('dev_package_id', $package->id)
                    ->orderByDesc('last_seen_at')
                    ->first();

                if ($lastOccurrence) {
                    $lines[] = '  Letzter Fehler:';
                    $lines[] = '    exception_class:   ' . ($lastOccurrence->exception_class ?? '-');
                    $lines[] = '    message:           ' . mb_substr($lastOccurrence->message ?? '-', 0, 120);
                    $lines[] = '    last_seen_at:      ' . ($lastOccurrence->last_seen_at?->toIso8601String() ?? '-');
                    $lines[] = '    occurrence_count:  ' . $lastOccurrence->occurrence_count;
                    $lines[] = '    status:            ' . $lastOccurrence->status;
                } else {
                    $lines[] = '  Letzter Fehler: (keine Occurrences vorhanden)';
                }

                // ENV status
                $envKey = 'DEV_ERROR_ENDPOINT_' . strtoupper(str_replace('-', '_', $package->name));
                $envValue = getenv($envKey);
                $lines[] = '  ENV ' . $envKey . ': ' . ($envValue ? 'gesetzt' : 'NICHT gesetzt');

                $lines[] = '';
            }

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
