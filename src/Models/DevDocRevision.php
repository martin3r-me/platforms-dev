<?php

namespace Platform\Dev\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Symfony\Component\Uid\UuidV7;

class DevDocRevision extends Model
{
    public $timestamps = false;

    protected $table = 'dev_doc_revisions';

    protected $fillable = [
        'uuid',
        'dev_doc_page_id',
        'version',
        'title',
        'content',
        'change_summary',
        'created_by_user_id',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
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

            if (empty($model->created_at)) {
                $model->created_at = now();
            }
        });
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(DevDocPage::class, 'dev_doc_page_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\User::class, 'created_by_user_id');
    }
}
