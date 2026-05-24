<?php

namespace Platform\Dev\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Platform\ActivityLog\Traits\LogsActivity;
use Platform\Core\Models\Concerns\HasEntityLinks;
use Platform\Core\Traits\HasTags;
use Platform\Organization\Contracts\HasChildContextRelations;
use Platform\Organization\Traits\HasTimeEntries;
use Platform\Organization\Traits\HasOrganizationContexts;
use Symfony\Component\Uid\UuidV7;

class DevPackage extends Model implements HasChildContextRelations
{
    use SoftDeletes, LogsActivity, HasEntityLinks, HasTags, HasTimeEntries, HasOrganizationContexts;

    protected $table = 'dev_packages';

    protected $fillable = [
        'uuid',
        'team_id',
        'created_by_user_id',
        'user_in_charge_id',
        'locked_by_user_id',
        'locked_at',
        'lock_reason',
        'name',
        'description',
        'github_repo_full_name',
        'github_repo_id',
        'status',
        'icon',
        'order',
    ];

    protected $casts = [
        'locked_at' => 'datetime',
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

    public function lockedByUser(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\User::class, 'locked_by_user_id');
    }

    public function isLocked(): bool
    {
        return $this->locked_by_user_id !== null;
    }

    public function githubRepo(): BelongsTo
    {
        return $this->belongsTo(\Platform\Integrations\Models\IntegrationsGithubRepository::class, 'github_repo_id');
    }

    public function boards(): HasMany
    {
        return $this->hasMany(DevBoard::class, 'dev_package_id')->orderBy('order');
    }

    public function discussions(): HasMany
    {
        return $this->hasMany(DevDiscussion::class, 'dev_package_id')->orderByDesc('is_pinned')->orderByDesc('updated_at');
    }

    public function docPages(): HasMany
    {
        return $this->hasMany(DevDocPage::class, 'dev_package_id')->orderBy('position');
    }

    public function errorSettings(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(DevPackageErrorSettings::class, 'dev_package_id');
    }

    public function errorOccurrences(): HasMany
    {
        return $this->hasMany(DevErrorOccurrence::class, 'dev_package_id');
    }

    public function scopeForTeam($query, int $teamId)
    {
        return $query->where('team_id', $teamId);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    // ── HasChildContextRelations ─────────────────────────────

    public static function childContextRelations(): array
    {
        return ['boards.issues'];
    }
}
