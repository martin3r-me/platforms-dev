<?php

namespace Platform\Dev\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DevPackageErrorSettings extends Model
{
    public const DEFAULT_CAPTURE_CODES = [400, 401, 403, 404, 500, 502, 503, 504];

    public const DEFAULT_PRIORITY_MAPPING = [
        '400' => 'low',
        '401' => 'normal',
        '403' => 'normal',
        '404' => 'low',
        '500' => 'high',
        '502' => 'high',
        '503' => 'high',
        '504' => 'normal',
    ];

    protected $table = 'dev_package_error_settings';

    protected $fillable = [
        'dev_package_id',
        'team_id',
        'enabled',
        'capture_console_errors',
        'capture_codes',
        'priority_mapping',
        'dedupe_window_hours',
        'auto_create_issue',
        'include_stack_trace',
        'stack_trace_limit',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'capture_console_errors' => 'boolean',
        'capture_codes' => 'array',
        'priority_mapping' => 'array',
        'dedupe_window_hours' => 'integer',
        'auto_create_issue' => 'boolean',
        'include_stack_trace' => 'boolean',
        'stack_trace_limit' => 'integer',
    ];

    public function package(): BelongsTo
    {
        return $this->belongsTo(DevPackage::class, 'dev_package_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\Team::class);
    }

    public function errorOccurrences(): HasMany
    {
        return $this->hasMany(DevErrorOccurrence::class, 'dev_package_id', 'dev_package_id');
    }

    public static function getOrCreateForPackage(DevPackage $package): self
    {
        return static::firstOrCreate(
            ['dev_package_id' => $package->id],
            [
                'team_id' => $package->team_id,
                'enabled' => false,
                'capture_console_errors' => false,
                'capture_codes' => self::DEFAULT_CAPTURE_CODES,
                'priority_mapping' => self::DEFAULT_PRIORITY_MAPPING,
                'dedupe_window_hours' => 24,
                'auto_create_issue' => true,
                'include_stack_trace' => true,
                'stack_trace_limit' => 50,
            ]
        );
    }

    public function shouldCaptureCode(?int $code): bool
    {
        if ($code === null) {
            return true;
        }

        $codes = $this->capture_codes ?? self::DEFAULT_CAPTURE_CODES;

        return in_array($code, $codes, true);
    }

    public function getPriorityForCode(?int $code): string
    {
        if ($code === null) {
            return 'high';
        }

        $mapping = $this->priority_mapping ?? self::DEFAULT_PRIORITY_MAPPING;

        return $mapping[(string) $code] ?? 'normal';
    }

    public function getCaptureCodes(): array
    {
        return $this->capture_codes ?? self::DEFAULT_CAPTURE_CODES;
    }

    public function getPriorityMapping(): array
    {
        return $this->priority_mapping ?? self::DEFAULT_PRIORITY_MAPPING;
    }
}
