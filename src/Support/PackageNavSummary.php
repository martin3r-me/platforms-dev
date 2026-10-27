<?php

namespace Platform\Dev\Support;

use Platform\Dev\Models\DevPackage;
use Platform\Dev\Models\DevPackageSnapshot;

/**
 * Kennzahlen für die Package-Reiter der Dev-Actionbar: aktive Boards mit
 * offenen Issues, Anzahl Doku-Seiten, letzter Health-Stand. Pro Request
 * gecacht, weil die Leiste auf jeder Package-Seite gerendert wird.
 */
class PackageNavSummary
{
    protected static array $cache = [];

    public static function for(DevPackage $package): array
    {
        return static::$cache[$package->id] ??= static::build($package);
    }

    protected static function build(DevPackage $package): array
    {
        $boards = $package->boards()
            ->active()
            ->withCount(['issues as open_issues_count' => fn ($q) => $q->open()])
            ->get(['id', 'name', 'type', 'dev_package_id', 'order']);

        $snapshot = DevPackageSnapshot::where('dev_package_id', $package->id)
            ->orderByDesc('taken_on')
            ->first(['id', 'health_score', 'health_color', 'taken_on']);

        return [
            'boards' => $boards,
            'docs' => $package->docPages()->count(),
            'health' => [
                'score' => $snapshot?->health_score,
                'color' => $snapshot?->health_color,
            ],
        ];
    }
}
