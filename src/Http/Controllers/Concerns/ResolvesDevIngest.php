<?php

namespace Platform\Dev\Http\Controllers\Concerns;

use Platform\Dev\Models\DevPackageErrorSettings;

/**
 * Shared token authentication for the dev ingest endpoints. The ingest token
 * lives on dev_package_error_settings but is really a team-level credential —
 * any enabled token in a team authenticates ingest for that team. Package
 * resolution lives in DevFeatureRequestService so it can be reused in-process.
 */
trait ResolvesDevIngest
{
    /**
     * Resolve the enabled settings row that owns the given ingest token.
     * Returns null when the token is unknown or disabled.
     */
    protected function authenticateToken(string $token): ?DevPackageErrorSettings
    {
        return DevPackageErrorSettings::where('ingest_token', $token)
            ->where('enabled', true)
            ->first();
    }
}
