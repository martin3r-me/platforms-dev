<?php

namespace Platform\Dev\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Platform\Dev\Models\DevErrorOccurrence;
use Platform\Dev\Models\DevIssue;
use Platform\Dev\Models\DevPackageErrorSettings;
use Illuminate\Support\Facades\Log;

class ErrorIngestController extends Controller
{
    public function ingest(Request $request, string $token): JsonResponse
    {
        $settings = DevPackageErrorSettings::where('ingest_token', $token)
            ->where('enabled', true)
            ->first();

        if (!$settings) {
            return response()->json(['error' => 'Invalid or disabled token'], 403);
        }

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

        $httpCode = $data['http_code'] ?? null;

        // Check if this HTTP code should be captured
        if ($httpCode && !$settings->shouldCaptureCode($httpCode)) {
            return response()->json(['status' => 'skipped', 'reason' => 'http_code_not_tracked'], 200);
        }

        // Console error check
        if (($data['is_console'] ?? false) && !$settings->capture_console_errors) {
            return response()->json(['status' => 'skipped', 'reason' => 'console_errors_disabled'], 200);
        }

        // Deduplication hash
        $hash = DevErrorOccurrence::generateHashFromComponents(
            $data['exception_class'],
            $data['file'] ?? null,
            $data['line'] ?? null,
            $httpCode
        );

        $existing = DevErrorOccurrence::findExistingInDedupeWindow(
            $settings->dev_package_id,
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

        // Create new occurrence
        $occurrence = DevErrorOccurrence::create([
            'dev_package_id' => $settings->dev_package_id,
            'team_id' => $settings->team_id,
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

        // Auto-create issue
        if ($settings->auto_create_issue) {
            $issue = $this->createIssue($occurrence, $settings, $httpCode);
            if ($issue) {
                $occurrence->update(['dev_issue_id' => $issue->id]);
            }
        }

        Log::info('[Dev ErrorIngest] New occurrence', [
            'occurrence_id' => $occurrence->id,
            'package_id' => $settings->dev_package_id,
            'exception' => $data['exception_class'],
            'instance' => $data['instance'] ?? 'unknown',
        ]);

        return response()->json([
            'status' => 'created',
            'occurrence_id' => $occurrence->id,
        ], 201);
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
