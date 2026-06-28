<?php

namespace Platform\Dev\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DevPackageSnapshotTopError extends Model
{
    protected $table = 'dev_package_snapshot_top_errors';

    protected $fillable = [
        'snapshot_id', 'error_occurrence_id',
        'exception_class', 'message_excerpt',
        'occurrence_count', 'status',
        'first_seen_at', 'last_seen_at',
        'rank',
    ];

    protected $casts = [
        'first_seen_at' => 'datetime',
        'last_seen_at' => 'datetime',
    ];

    public function snapshot(): BelongsTo
    {
        return $this->belongsTo(DevPackageSnapshot::class, 'snapshot_id');
    }

    public function error(): BelongsTo
    {
        return $this->belongsTo(DevErrorOccurrence::class, 'error_occurrence_id');
    }
}
