<?php

namespace Platform\Dev\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Platform\Dev\Enums\DocPageType;
use Symfony\Component\Uid\UuidV7;

class DevDocPage extends Model
{
    use SoftDeletes;

    protected $table = 'dev_doc_pages';

    protected $fillable = [
        'uuid',
        'team_id',
        'created_by_user_id',
        'last_edited_by_user_id',
        'dev_package_id',
        'type',
        'title',
        'slug',
        'content',
        'position',
        'status',
    ];

    protected $casts = [
        'type' => DocPageType::class,
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

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\User::class, 'created_by_user_id');
    }

    public function lastEditedBy(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\User::class, 'last_edited_by_user_id');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(DevDocRevision::class, 'dev_doc_page_id')->orderByDesc('version');
    }

    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }

    public function getExcerptAttribute(): string
    {
        return Str::limit(strip_tags($this->content ?? ''), 200);
    }
}
