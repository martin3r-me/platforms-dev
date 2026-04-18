<?php

namespace Platform\Dev\Contracts;

use Illuminate\Database\Eloquent\Collection;
use Platform\Dev\Models\DevErrorOccurrence;
use Throwable;

interface DevErrorTrackerContract
{
    public function capture(Throwable $e, array $context = []): ?DevErrorOccurrence;

    public function getOpenOccurrences(int $packageId): Collection;

    public function getOccurrences(int $packageId, ?string $status = null, int $limit = 50): Collection;
}
