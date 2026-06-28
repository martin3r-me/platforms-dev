<?php

namespace Platform\Dev\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DevPackageSnapshotBoard extends Model
{
    protected $table = 'dev_package_snapshot_boards';

    protected $fillable = [
        'snapshot_id', 'board_id',
        'board_name', 'board_type',
        'issues_open', 'issues_done', 'issues_total',
    ];

    public function snapshot(): BelongsTo
    {
        return $this->belongsTo(DevPackageSnapshot::class, 'snapshot_id');
    }

    public function board(): BelongsTo
    {
        return $this->belongsTo(DevBoard::class, 'board_id');
    }
}
