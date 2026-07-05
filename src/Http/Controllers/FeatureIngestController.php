<?php

namespace Platform\Dev\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Platform\Dev\Http\Controllers\Concerns\ResolvesDevIngest;
use Platform\Dev\Services\DevFeatureRequestService;

/**
 * Receives external feature requests and drops them onto the target package's
 * inbox board as a new DevIssue (triage stage). Unlike error ingest there is
 * no deduplication — every request becomes its own issue.
 */
class FeatureIngestController extends Controller
{
    use ResolvesDevIngest;

    public function __construct(private DevFeatureRequestService $features)
    {
    }

    public function ingest(Request $request, string $token): JsonResponse
    {
        $tokenSettings = $this->authenticateToken($token);

        if (!$tokenSettings) {
            return response()->json(['error' => 'Invalid or disabled token'], 403);
        }

        $teamId = $tokenSettings->team_id;

        $data = $request->validate([
            'type' => 'nullable|string|in:feature',
            'package_key' => 'nullable|string|max:100',
            'title' => 'required|string|max:300',
            'description' => 'nullable|string|max:10000',
            'priority' => 'nullable|string|in:low,normal,high',
            'labels' => 'nullable|array',
            'labels.*' => 'string|max:100',
            'story_points' => 'nullable|string|in:xs,s,m,l,xl,xxl',
            'url' => 'nullable|string|max:2000',
            'user_id' => 'nullable|integer',
            'instance' => 'nullable|string|max:255',
            'instance_name' => 'nullable|string|max:255',
            'submitted_by' => 'nullable|string|max:255',
            'extra' => 'nullable|array',
        ]);

        // Resolve target package by package_key, fall back to the token owner.
        $packageKey = $data['package_key'] ?? null;
        $targetPackage = $this->features->resolvePackageByKey($teamId, $packageKey);

        if (!$targetPackage) {
            $targetPackage = $tokenSettings->package;

            if ($packageKey) {
                Log::warning('[Dev FeatureIngest] Package not resolved, falling back to token owner', [
                    'package_key' => $packageKey,
                    'fallback_package' => $targetPackage->name ?? 'unknown',
                    'team_id' => $teamId,
                ]);
            }
        }

        if (!$targetPackage) {
            return response()->json(['error' => 'Could not resolve target package'], 422);
        }

        $issue = $this->features->create($targetPackage, [
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'priority' => $data['priority'] ?? 'normal',
            'story_points' => $data['story_points'] ?? null,
            'labels' => $data['labels'] ?? [],
            'instance' => $data['instance_name'] ?? $data['instance'] ?? null,
            'submitted_by' => $data['submitted_by'] ?? null,
            'url' => $data['url'] ?? null,
            'extra' => $data['extra'] ?? [],
        ]);

        Log::info('[Dev FeatureIngest] New feature request', [
            'issue_id' => $issue->id,
            'package' => $targetPackage->name,
            'package_key' => $packageKey,
            'board_id' => $issue->dev_board_id,
        ]);

        return response()->json([
            'status' => 'created',
            'issue_id' => $issue->id,
            'board_id' => $issue->dev_board_id,
        ], 201);
    }
}
