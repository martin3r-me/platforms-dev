<?php

namespace Platform\Dev\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DevPackageSnapshotPerson extends Model
{
    protected $table = 'dev_package_snapshot_people';

    protected $fillable = [
        'snapshot_id', 'user_id', 'user_name',
        'open_issues', 'done_issues',
        'open_bugs', 'open_features',
        'overdue_issues',
        'sp_open', 'sp_done',
    ];

    public function snapshot(): BelongsTo
    {
        return $this->belongsTo(DevPackageSnapshot::class, 'snapshot_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\User::class, 'user_id');
    }
}
