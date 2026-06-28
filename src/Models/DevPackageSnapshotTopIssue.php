<?php

namespace Platform\Dev\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DevPackageSnapshotTopIssue extends Model
{
    protected $table = 'dev_package_snapshot_top_issues';

    protected $fillable = [
        'snapshot_id', 'issue_id', 'issue_uuid', 'issue_title',
        'board_type', 'board_name',
        'priority', 'story_points',
        'due_date', 'is_overdue', 'is_done',
        'user_in_charge_id', 'user_in_charge_name',
        'rank',
    ];

    protected $casts = [
        'due_date' => 'date',
        'is_overdue' => 'boolean',
        'is_done' => 'boolean',
    ];

    public function snapshot(): BelongsTo
    {
        return $this->belongsTo(DevPackageSnapshot::class, 'snapshot_id');
    }

    public function issue(): BelongsTo
    {
        return $this->belongsTo(DevIssue::class, 'issue_id');
    }

    public function userInCharge(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\User::class, 'user_in_charge_id');
    }
}
