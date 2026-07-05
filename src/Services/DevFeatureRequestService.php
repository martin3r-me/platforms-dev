<?php

namespace Platform\Dev\Services;

use Illuminate\Support\Collection;
use Platform\Dev\Models\DevIssue;
use Platform\Dev\Models\DevPackage;

/**
 * Turns an incoming feature request into a DevIssue on a package's inbox board.
 * Shared by the HTTP ingest endpoint (FeatureIngestController) and in-process
 * callers such as the core Terminal "Feature Request" tab.
 */
class DevFeatureRequestService
{
    public function __construct(private DevPackageService $packages)
    {
    }

    /**
     * All packages of a team, for manual target selection in UIs.
     *
     * @return Collection<int, DevPackage>
     */
    public function packagesForTeam(int $teamId): Collection
    {
        return DevPackage::where('team_id', $teamId)->orderBy('name')->get();
    }

    /**
     * Resolve a DevPackage by a loose key (module key or package name) within a
     * team. Tries exact name, prefix-stripped name, and kebab suffix.
     */
    public function resolvePackageByKey(int $teamId, ?string $key): ?DevPackage
    {
        if (!$key) {
            return null;
        }

        foreach (DevPackage::where('team_id', $teamId)->get() as $package) {
            if ($package->name === $key) {
                return $package;
            }

            $shortName = preg_replace('/^platforms?-/', '', $package->name);
            if ($shortName === $key) {
                return $package;
            }

            if (str_ends_with($package->name, '-' . $key)) {
                return $package;
            }
        }

        return null;
    }

    /**
     * Create a feature-request issue on the package's inbox board.
     *
     * $data keys: title (required), description, priority, story_points,
     *   labels[], instance, submitted_by, url, extra[], created_by_user_id.
     */
    public function create(DevPackage $package, array $data): DevIssue
    {
        $board = $this->packages->getOrCreateInboxBoard($package);

        $instance = $data['instance'] ?? null;

        $labels = array_values(array_unique(array_filter(array_merge(
            ['feature-request'],
            $data['labels'] ?? [],
            $instance ? [$instance] : []
        ))));

        return DevIssue::create([
            'dev_board_id' => $board->id,
            'team_id' => $package->team_id,
            'created_by_user_id' => $data['created_by_user_id']
                ?? $package->user_in_charge_id
                ?? $package->created_by_user_id,
            'title' => $data['title'],
            'description' => $this->buildDescription($data, $instance),
            'priority' => $data['priority'] ?? 'normal',
            'story_points' => $data['story_points'] ?? null,
            'labels' => $labels,
        ]);
    }

    /**
     * Compose a markdown description from the request body and its metadata.
     */
    public function buildDescription(array $data, ?string $instance): string
    {
        $lines = array_filter([
            $data['description'] ?? null,
            !empty($data['description']) ? '' : null,
            !empty($data['submitted_by']) ? "**Submitted by:** {$data['submitted_by']}" : null,
            $instance ? "**Instance:** {$instance}" : null,
            !empty($data['url']) ? "**URL:** {$data['url']}" : null,
        ], fn ($line) => $line !== null);

        if (!empty($data['extra'])) {
            $lines[] = '';
            $lines[] = '**Extra:**';
            foreach ($data['extra'] as $key => $value) {
                $rendered = is_scalar($value) ? $value : json_encode($value);
                $lines[] = "- {$key}: {$rendered}";
            }
        }

        return implode("\n", $lines);
    }
}
