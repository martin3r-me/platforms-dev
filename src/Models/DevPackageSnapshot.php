<?php

namespace Platform\Dev\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Platform\Core\Health\Traits\HasHealthSnapshotData;
use Symfony\Component\Uid\UuidV7;

class DevPackageSnapshot extends Model
{
    use HasHealthSnapshotData;

    protected $table = 'dev_package_snapshots';

    protected $fillable = [
        'dev_package_id',
        // Issues
        'issues_total', 'issues_open', 'issues_done',
        'issues_overdue', 'issues_high_priority_open',
        // Bugs
        'bugs_total', 'bugs_open', 'bugs_done',
        // Features
        'features_total', 'features_open', 'features_done',
        // Story Points
        'story_points_total', 'story_points_open', 'story_points_done',
        // Errors
        'errors_open', 'errors_acknowledged', 'errors_total_hits',
        'errors_seen_today', 'latest_error_seen_at',
        // Boards
        'boards_count', 'has_bug_board', 'has_feature_board',
        // Docs
        'doc_pages_count', 'doc_pages_stale', 'doc_pages_published',
        // Workload
        'active_users_count', 'unassigned_open_issues',
    ];

    protected $casts = [
        'has_bug_board' => 'boolean',
        'has_feature_board' => 'boolean',
        'latest_error_seen_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if (empty($model->uuid)) {
                do {
                    $uuid = UuidV7::generate();
                } while (self::where('uuid', $uuid)->exists());
                $model->uuid = $uuid;
            }
        });
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(DevPackage::class, 'dev_package_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\Team::class, 'team_id');
    }

    public function previous(): BelongsTo
    {
        return $this->belongsTo(self::class, 'prev_snapshot_id');
    }

    public function topIssues(): HasMany
    {
        return $this->hasMany(DevPackageSnapshotTopIssue::class, 'snapshot_id')->orderBy('rank');
    }

    public function topErrors(): HasMany
    {
        return $this->hasMany(DevPackageSnapshotTopError::class, 'snapshot_id')->orderBy('rank');
    }

    public function people(): HasMany
    {
        return $this->hasMany(DevPackageSnapshotPerson::class, 'snapshot_id')->orderByDesc('open_issues');
    }

    public function boards(): HasMany
    {
        return $this->hasMany(DevPackageSnapshotBoard::class, 'snapshot_id');
    }
}
