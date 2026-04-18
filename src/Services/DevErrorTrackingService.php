<?php

namespace Platform\Dev\Services;

use Platform\Dev\Contracts\DevErrorTrackerContract;
use Platform\Dev\Models\DevBoard;
use Platform\Dev\Models\DevErrorOccurrence;
use Platform\Dev\Models\DevIssue;
use Platform\Dev\Models\DevPackage;
use Platform\Dev\Models\DevPackageErrorSettings;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Throwable;

class DevErrorTrackingService implements DevErrorTrackerContract
{
    public function capture(Throwable $e, array $context = []): ?DevErrorOccurrence
    {
        try {
            $httpCode = $context['http_code'] ?? null;
            $isConsole = $context['is_console'] ?? false;
            $occurrences = [];

            $enabledSettings = DevPackageErrorSettings::where('enabled', true)->get();

            foreach ($enabledSettings as $settings) {
                if ($isConsole && !$settings->capture_console_errors) {
                    continue;
                }

                if (!$settings->shouldCaptureCode($httpCode)) {
                    continue;
                }

                $occurrence = $this->processError($e, $settings, $context);
                if ($occurrence) {
                    $occurrences[] = $occurrence;
                }
            }

            return $occurrences[0] ?? null;
        } catch (Throwable $captureError) {
            Log::error('DevErrorTrackingService: Fehler beim Erfassen', [
                'error' => $captureError->getMessage(),
                'original_error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    protected function processError(
        Throwable $e,
        DevPackageErrorSettings $settings,
        array $context
    ): ?DevErrorOccurrence {
        $httpCode = $context['http_code'] ?? null;
        $hash = DevErrorOccurrence::generateHash($e, $httpCode);

        $existing = DevErrorOccurrence::findExistingInDedupeWindow(
            $settings->dev_package_id,
            $hash,
            $settings->dedupe_window_hours
        );

        if ($existing) {
            return $this->updateExistingOccurrence($existing, $e, $settings, $context);
        }

        return $this->createNewOccurrence($e, $settings, $context, $hash);
    }

    protected function updateExistingOccurrence(
        DevErrorOccurrence $occurrence,
        Throwable $e,
        DevPackageErrorSettings $settings,
        array $context
    ): DevErrorOccurrence {
        $sampleData = $this->buildSampleData($e, $settings, $context);

        return $occurrence->recordOccurrence($sampleData);
    }

    protected function createNewOccurrence(
        Throwable $e,
        DevPackageErrorSettings $settings,
        array $context,
        string $hash
    ): DevErrorOccurrence {
        $httpCode = $context['http_code'] ?? null;
        $sampleData = $this->buildSampleData($e, $settings, $context);

        $occurrence = DevErrorOccurrence::create([
            'dev_package_id' => $settings->dev_package_id,
            'team_id' => $settings->team_id,
            'error_hash' => $hash,
            'exception_class' => get_class($e),
            'message' => $this->truncateMessage($e->getMessage()),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
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

        Log::info('DevErrorTrackingService: Neue Error Occurrence erstellt', [
            'occurrence_id' => $occurrence->id,
            'package_id' => $settings->dev_package_id,
            'exception_class' => $occurrence->exception_class,
            'http_code' => $httpCode,
        ]);

        return $occurrence;
    }

    protected function createIssue(
        DevErrorOccurrence $occurrence,
        DevPackageErrorSettings $settings,
        ?int $httpCode
    ): ?DevIssue {
        try {
            $package = DevPackage::find($settings->dev_package_id);
            if (!$package) {
                return null;
            }

            // Find the Bug board for this package
            $bugBoard = $package->boards()->where('type', 'bug')->first();
            if (!$bugBoard) {
                // Fallback: use first board
                $bugBoard = $package->boards()->first();
            }
            if (!$bugBoard) {
                return null;
            }

            $priority = $settings->getPriorityForCode($httpCode);
            $title = $this->buildIssueTitle($occurrence);

            $issue = DevIssue::create([
                'dev_board_id' => $bugBoard->id,
                'team_id' => $settings->team_id,
                'created_by_user_id' => Auth::id() ?? $package->user_in_charge_id ?? $package->created_by_user_id,
                'title' => $title,
                'description' => $this->buildIssueDescription($occurrence),
                'priority' => $priority,
                'labels' => ['error-tracking', 'auto-created'],
            ]);

            Log::info('DevErrorTrackingService: Issue erstellt', [
                'issue_id' => $issue->id,
                'board_id' => $bugBoard->id,
                'occurrence_id' => $occurrence->id,
            ]);

            return $issue;
        } catch (Throwable $e) {
            Log::error('DevErrorTrackingService: Fehler beim Erstellen des Issues', [
                'error' => $e->getMessage(),
                'occurrence_id' => $occurrence->id,
            ]);

            return null;
        }
    }

    protected function buildSampleData(
        Throwable $e,
        DevPackageErrorSettings $settings,
        array $context
    ): array {
        $data = [
            'url' => $context['url'] ?? request()->fullUrl() ?? null,
            'method' => $context['method'] ?? request()->method() ?? null,
            'user_id' => $context['user_id'] ?? auth()->id() ?? null,
            'user_agent' => $context['user_agent'] ?? request()->userAgent() ?? null,
            'ip' => $context['ip'] ?? request()->ip() ?? null,
            'timestamp' => now()->toIso8601String(),
        ];

        if ($settings->include_stack_trace) {
            $data['stack_trace'] = $this->getStackTrace($e, $settings->stack_trace_limit);
        }

        if (isset($context['extra'])) {
            $data['extra'] = $context['extra'];
        }

        return $data;
    }

    protected function buildIssueTitle(DevErrorOccurrence $occurrence): string
    {
        $shortClass = $occurrence->getShortExceptionClass();
        $httpCode = $occurrence->http_code ? "[{$occurrence->http_code}] " : '';
        $message = $this->truncateMessage($occurrence->message, 80);

        return "{$httpCode}{$shortClass}: {$message}";
    }

    protected function buildIssueDescription(DevErrorOccurrence $occurrence): string
    {
        $lines = [
            "**Exception:** {$occurrence->exception_class}",
            "**Message:** {$occurrence->message}",
            "**Location:** {$occurrence->getFormattedLocation()}",
        ];

        if ($occurrence->http_code) {
            $lines[] = "**HTTP Code:** {$occurrence->http_code}";
        }

        $sampleData = $occurrence->sample_data ?? [];
        if (!empty($sampleData['url'])) {
            $lines[] = "**URL:** {$sampleData['url']}";
        }

        $lines[] = '';
        $lines[] = "**First Seen:** {$occurrence->first_seen_at->format('Y-m-d H:i:s')}";
        $lines[] = "**Error Occurrence ID:** {$occurrence->id}";

        return implode("\n", $lines);
    }

    protected function truncateMessage(?string $message, int $maxLength = 500): string
    {
        if (empty($message)) {
            return '';
        }

        if (strlen($message) <= $maxLength) {
            return $message;
        }

        return substr($message, 0, $maxLength - 3) . '...';
    }

    protected function getStackTrace(Throwable $e, int $limit): array
    {
        $trace = $e->getTrace();

        return array_slice(
            array_map(function ($frame) {
                return [
                    'file' => $frame['file'] ?? null,
                    'line' => $frame['line'] ?? null,
                    'function' => $frame['function'] ?? null,
                    'class' => $frame['class'] ?? null,
                ];
            }, $trace),
            0,
            $limit
        );
    }

    public function getOpenOccurrences(int $packageId): \Illuminate\Database\Eloquent\Collection
    {
        return DevErrorOccurrence::where('dev_package_id', $packageId)
            ->where('status', DevErrorOccurrence::STATUS_OPEN)
            ->orderBy('last_seen_at', 'desc')
            ->get();
    }

    public function getOccurrences(
        int $packageId,
        ?string $status = null,
        int $limit = 50
    ): \Illuminate\Database\Eloquent\Collection {
        $query = DevErrorOccurrence::where('dev_package_id', $packageId);

        if ($status !== null) {
            $query->where('status', $status);
        }

        return $query->orderBy('last_seen_at', 'desc')
            ->limit($limit)
            ->get();
    }
}
