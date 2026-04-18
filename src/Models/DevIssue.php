<?php

namespace Platform\Dev\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Platform\ActivityLog\Traits\LogsActivity;
use Platform\Dev\Enums\IssuePriority;
use Symfony\Component\Uid\UuidV7;

class DevIssue extends Model
{
    use SoftDeletes, LogsActivity;

    protected $table = 'dev_issues';

    protected $fillable = [
        'uuid',
        'team_id',
        'created_by_user_id',
        'dev_board_id',
        'dev_board_slot_id',
        'title',
        'description',
        'priority',
        'status',
        'labels',
        'user_in_charge_id',
        'order',
        'slot_order',
        'is_done',
        'done_at',
        'due_date',
    ];

    protected $casts = [
        'priority' => IssuePriority::class,
        'labels' => 'array',
        'is_done' => 'boolean',
        'done_at' => 'datetime',
        'due_date' => 'date',
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

    public function board(): BelongsTo
    {
        return $this->belongsTo(DevBoard::class, 'dev_board_id');
    }

    public function slot(): BelongsTo
    {
        return $this->belongsTo(DevBoardSlot::class, 'dev_board_slot_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\Team::class, 'team_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\User::class, 'created_by_user_id');
    }

    public function userInCharge(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\User::class, 'user_in_charge_id');
    }

    public function scopeForTeam($query, int $teamId)
    {
        return $query->where('team_id', $teamId);
    }

    public function scopeOpen($query)
    {
        return $query->where('status', 'open');
    }

    public function scopeClosed($query)
    {
        return $query->where('status', 'closed');
    }

    public function scopeInBacklog($query)
    {
        return $query->whereNull('dev_board_slot_id');
    }

    public function close(): void
    {
        $this->update([
            'status' => 'closed',
            'is_done' => true,
            'done_at' => now(),
        ]);
    }

    public function reopen(): void
    {
        $this->update([
            'status' => 'open',
            'is_done' => false,
            'done_at' => null,
        ]);
    }
}
