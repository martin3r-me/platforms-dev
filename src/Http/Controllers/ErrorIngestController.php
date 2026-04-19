<?php

namespace Platform\Dev\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Platform\Dev\Models\DevErrorOccurrence;
use Platform\Dev\Models\DevIssue;
use Platform\Dev\Models\DevPackage;
use Platform\Dev\Models\DevPackageErrorSettings;
use Illuminate\Support\Facades\Log;

class ErrorIngestController extends Controller
{
    public function ingest(Request $request, string $token): JsonResponse
    {
        // Token authenticates the team (any package's token in that team works)
        $tokenSettings = DevPackageErrorSettings::where('ingest_token', $token)
            ->where('enabled', true)
            ->first();

        if (!$tokenSettings) {
            return response()->json(['error' => 'Invalid or disabled token'], 403);
        }

        $teamId = $tokenSettings->team_id;

        $data = $request->validate([
            'package_key' => 'nullable|string|max:100',
            'exception_class' => 'required|string|max:500',
            'message' => 'nullable|string|max:5000',
            'file' => 'nullable|string|max:500',
            'line' => 'nullable|integer',
            'http_code' => 'nullable|integer',
            'is_console' => 'nullable|boolean',
            'url' => 'nullable|string|max:2000',
            'method' => 'nullable|string|max:10',
            'user_id' => 'nullable|integer',
            'instance' => 'nullable|string|max:255',
            'instance_name' => 'nullable|string|max:255',
            'timestamp' => 'nullable|string',
            'stack_trace' => 'nullable|array',
            'extra' => 'nullable|array',
        ]);

        // Resolve target package by package_key from payload
        $packageKey = $data['package_key'] ?? null;
        $targetPackage = null;
        $settings = null;

        if ($packageKey) {
            $targetPackage = $this->resolvePackage($teamId, $packageKey);
        }

        if ($targetPackage) {
            $settings = DevPackageErrorSettings::where('dev_package_id', $targetPackage->id)->first();
            // Auto-create settings if missing
            if (!$settings) {
                $settings = DevPackageErrorSettings::getOrCreateForPackage($targetPackage);
            }
        } else {
            // Fallback: use the package that owns the token
            $targetPackage = $tokenSettings->package;
            $settings = $tokenSettings;
        }

        if (!$settings->enabled) {
            return response()->json(['status' => 'skipped', 'reason' => 'package_disabled'], 200);
        }

        $httpCode = $data['http_code'] ?? null;

        if ($httpCode && !$settings->shouldCaptureCode($httpCode)) {
            return response()->json(['status' => 'skipped', 'reason' => 'http_code_not_tracked'], 200);
        }

        if (($data['is_console'] ?? false) && !$settings->capture_console_errors) {
            return response()->json(['status' => 'skipped', 'reason' => 'console_errors_disabled'], 200);
        }

        // Deduplication
        $hash = DevErrorOccurrence::generateHashFromComponents(
            $data['exception_class'],
            $data['file'] ?? null,
            $data['line'] ?? null,
            $httpCode
        );

        $existing = DevErrorOccurrence::findExistingInDedupeWindow(
            $targetPackage->id,
            $hash,
            $settings->dedupe_window_hours
        );

        $sampleData = [
            'url' => $data['url'] ?? null,
            'method' => $data['method'] ?? null,
            'user_id' => $data['user_id'] ?? null,
            'instance' => $data['instance'] ?? null,
            'instance_name' => $data['instance_name'] ?? null,
            'timestamp' => $data['timestamp'] ?? now()->toIso8601String(),
        ];

        if ($settings->include_stack_trace && !empty($data['stack_trace'])) {
            $sampleData['stack_trace'] = array_slice($data['stack_trace'], 0, $settings->stack_trace_limit);
        }

        if (!empty($data['extra'])) {
            $sampleData['extra'] = $data['extra'];
        }

        if ($existing) {
            $existing->recordOccurrence($sampleData);

            return response()->json([
                'status' => 'deduplicated',
                'occurrence_id' => $existing->id,
                'count' => $existing->occurrence_count,
            ], 200);
        }

        $occurrence = DevErrorOccurrence::create([
            'dev_package_id' => $targetPackage->id,
            'team_id' => $teamId,
            'error_hash' => $hash,
            'exception_class' => $data['exception_class'],
            'message' => mb_substr($data['message'] ?? '', 0, 2000),
            'file' => $data['file'] ?? null,
            'line' => $data['line'] ?? null,
            'http_code' => $httpCode,
            'occurrence_count' => 1,
            'first_seen_at' => now(),
            'last_seen_at' => now(),
            'sample_data' => $sampleData,
            'status' => DevErrorOccurrence::STATUS_OPEN,
        ]);

        if ($settings->auto_create_issue) {
            $issue = $this->createIssue($occurrence, $settings, $httpCode);
            if ($issue) {
                $occurrence->update(['dev_issue_id' => $issue->id]);
            }
        }

        Log::info('[Dev ErrorIngest] New occurrence', [
            'occurrence_id' => $occurrence->id,
            'package' => $targetPackage->name,
            'package_key' => $packageKey,
            'exception' => $data['exception_class'],
            'instance' => $data['instance'] ?? 'unknown',
        ]);

        return response()->json([
            'status' => 'created',
            'occurrence_id' => $occurrence->id,
        ], 201);
    }

    /**
     * Resolve DevPackage by package_key within a team.
     * Tries multiple matching strategies.
     */
    protected function resolvePackage(int $teamId, string $packageKey): ?DevPackage
    {
        $packages = DevPackage::where('team_id', $teamId)->get();

        foreach ($packages as $package) {
            // Exact match: package_key == package name
            if ($package->name === $packageKey) {
                return $package;
            }

            // Key is short form (e.g. "organization"), package name has prefix
            // "platforms-organization", "platform-organization"
            $shortName = preg_replace('/^platforms?-/', '', $package->name);
            if ($shortName === $packageKey) {
                return $package;
            }

            // Key is kebab, package name is kebab with prefix
            if (str_ends_with($package->name, '-' . $packageKey)) {
                return $package;
            }
        }

        return null;
    }

    protected function createIssue(
        DevErrorOccurrence $occurrence,
        DevPackageErrorSettings $settings,
        ?int $httpCode
    ): ?DevIssue {
        try {
            $package = $settings->package;
            if (!$package) {
                return null;
            }

            $bugBoard = $package->boards()->where('type', 'bug')->first()
                ?? $package->boards()->first();

            if (!$bugBoard) {
                return null;
            }

            $priority = $settings->getPriorityForCode($httpCode);
            $shortClass = $occurrence->getShortExceptionClass();
            $httpPrefix = $occurrence->http_code ? "[{$occurrence->http_code}] " : '';
            $message = mb_substr($occurrence->message ?? '', 0, 80);

            $instance = $occurrence->sample_data['instance_name']
                ?? $occurrence->sample_data['instance']
                ?? 'unknown';

            $description = implode("\n", array_filter([
                "**Exception:** {$occurrence->exception_class}",
                "**Message:** {$occurrence->message}",
                "**Location:** {$occurrence->getFormattedLocation()}",
                $occurrence->http_code ? "**HTTP Code:** {$occurrence->http_code}" : null,
                "**Instance:** {$instance}",
                !empty($occurrence->sample_data['url']) ? "**URL:** {$occurrence->sample_data['url']}" : null,
                '',
                "**First Seen:** {$occurrence->first_seen_at->format('Y-m-d H:i:s')}",
            ]));

            return DevIssue::create([
                'dev_board_id' => $bugBoard->id,
                'team_id' => $settings->team_id,
                'created_by_user_id' => $package->user_in_charge_id ?? $package->created_by_user_id,
                'title' => "{$httpPrefix}{$shortClass}: {$message}",
                'description' => $description,
                'priority' => $priority,
                'labels' => ['error-tracking', 'auto-created', $instance],
            ]);
        } catch (\Throwable $e) {
            Log::error('[Dev ErrorIngest] Failed to create issue', [
                'error' => $e->getMessage(),
                'occurrence_id' => $occurrence->id,
            ]);

            return null;
        }
    }
}
