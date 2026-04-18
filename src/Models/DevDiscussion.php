<?php

namespace Platform\Dev\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Symfony\Component\Uid\UuidV7;

class DevDiscussion extends Model
{
    use SoftDeletes;

    protected $table = 'dev_discussions';

    protected $fillable = [
        'uuid',
        'team_id',
        'created_by_user_id',
        'dev_package_id',
        'title',
        'body',
        'is_pinned',
        'is_locked',
    ];

    protected $casts = [
        'is_pinned' => 'boolean',
        'is_locked' => 'boolean',
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

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\User::class, 'created_by_user_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(DevDiscussionReply::class, 'dev_discussion_id')->orderBy('created_at');
    }

    public function rootReplies(): HasMany
    {
        return $this->hasMany(DevDiscussionReply::class, 'dev_discussion_id')
            ->whereNull('parent_id')
            ->orderBy('created_at');
    }
}
