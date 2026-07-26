<?php

namespace Platform\Dev\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Platform\Dev\Enums\BoardType;
use Platform\Organization\Traits\HasTimeEntries;
use Symfony\Component\Uid\UuidV7;

class DevBoard extends Model
{
    use SoftDeletes, HasTimeEntries;

    protected $table = 'dev_boards';

    protected $fillable = [
        'uuid',
        'team_id',
        'created_by_user_id',
        'dev_package_id',
        'name',
        'type',
        'description',
        'order',
        'status',
        'agent_enabled',
    ];

    protected $casts = [
        'type' => BoardType::class,
        'agent_enabled' => 'boolean',
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

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeArchived($query)
    {
        return $query->where('status', 'archived');
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(DevPackage::class, 'dev_package_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\Team::class, 'team_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\User::class, 'created_by_user_id');
    }

    public function slots(): HasMany
    {
        return $this->hasMany(DevBoardSlot::class, 'dev_board_id')->orderBy('order');
    }

    public function issues(): HasMany
    {
        return $this->hasMany(DevIssue::class, 'dev_board_id');
    }
}
