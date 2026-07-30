<?php

namespace Platform\Dev\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Symfony\Component\Uid\UuidV7;

class DevBoardSlot extends Model
{
    use SoftDeletes;

    protected $table = 'dev_board_slots';

    protected $fillable = [
        'uuid',
        'team_id',
        'created_by_user_id',
        'dev_board_id',
        'name',
        'description',
        'order',
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

    public function issues(): HasMany
    {
        return $this->hasMany(DevIssue::class, 'dev_board_slot_id')->orderBy('slot_order');
    }
}
