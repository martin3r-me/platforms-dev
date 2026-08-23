<?php

namespace Platform\Dev\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Platform\ActivityLog\Traits\LogsActivity;
use Platform\Core\Traits\HasTags;
use Platform\Dev\Enums\IssuePriority;
use Platform\Dev\Enums\IssueStoryPoints;
use Platform\Organization\Traits\HasTimeEntries;
use Symfony\Component\Uid\UuidV7;

/**
 * @property string $status
 * @property bool $is_done
 */
class DevIssue extends Model
{
    use SoftDeletes, LogsActivity, HasTimeEntries, HasTags;

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
        'story_points',
        'status',
        'labels',
        'acceptance_criteria',
        'user_in_charge_id',
        'order',
        'slot_order',
        'is_done',
        'done_at',
        'due_date',
        'agent_locked_at',
        'agent_locked_by',
        'agent_branch',
        'agent_completed_at',
        'agent_summary',
        'agent_waiting_at',
        'agent_session_id',
        'agent_fail_count',
        'triage_done_at',
    ];

    protected $casts = [
        'priority' => IssuePriority::class,
        'story_points' => IssueStoryPoints::class,
        'labels' => 'array',
        'acceptance_criteria' => 'array',
        'is_done' => 'boolean',
        'done_at' => 'datetime',
        'due_date' => 'date',
        'agent_locked_at' => 'datetime',
        'agent_completed_at' => 'datetime',
        'agent_waiting_at' => 'datetime',
        'triage_done_at' => 'datetime',
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

    /**
     * @return BelongsTo<DevBoard, $this>
     */
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

    /**
     * Offen = weder geschlossen noch als erledigt markiert.
     * is_done kann auch ohne Status-Wechsel gesetzt werden (Board "ERLEDIGT"),
     * darum zaehlt beides.
     */
    public function scopeOpen($query)
    {
        return $query->where('status', 'open')
            ->where('is_done', false);
    }

    public function scopeClosed($query)
    {
        return $query->where(fn ($q) => $q->where('status', 'closed')->orWhere('is_done', true));
    }

    public function scopeDone($query)
    {
        return $query->where('is_done', true);
    }

    public function scopeInBacklog($query)
    {
        return $query->whereNull('dev_board_slot_id');
    }

    public function scopeAgentAvailable($query)
    {
        return $query->where('status', 'open')
            ->where('is_done', false)
            ->where(function ($q) {
                $q->whereNull('agent_locked_at')
                  ->orWhere('agent_locked_at', '<', now()->subMinutes(30));
            });
    }

    public function scopeAgentLocked($query)
    {
        return $query->whereNotNull('agent_locked_at')
            ->where('agent_locked_at', '>=', now()->subMinutes(30));
    }

    public function isAgentLocked(): bool
    {
        return $this->agent_locked_at !== null
            && $this->agent_locked_at->greaterThan(now()->subMinutes(30));
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
