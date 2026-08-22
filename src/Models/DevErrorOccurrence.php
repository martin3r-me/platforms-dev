<?php

namespace Platform\Dev\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Throwable;

class DevErrorOccurrence extends Model
{
    public const STATUS_OPEN = 'open';
    public const STATUS_ACKNOWLEDGED = 'acknowledged';
    public const STATUS_RESOLVED = 'resolved';
    public const STATUS_IGNORED = 'ignored';

    /**
     * Aktualitäts-Fenster (Stunden): ein Fehler gilt nur als „aktiv/feuert noch", wenn er
     * INNERHALB dieses Fensters zuletzt auftrat. Ältere sind fired-and-fixed-Rauschen aus dem
     * Entwickeln — sie verschwinden aus der Default-Ansicht und tauchen bei Wiederkehr von
     * selbst wieder auf (der Ingest legt außerhalb des Dedup-Fensters eine neue open-Zeile an).
     */
    public const ACTIVE_WINDOW_HOURS = 48;

    protected $table = 'dev_error_occurrences';

    protected $fillable = [
        'dev_package_id',
        'dev_issue_id',
        'team_id',
        'error_hash',
        'exception_class',
        'message',
        'file',
        'line',
        'http_code',
        'occurrence_count',
        'first_seen_at',
        'last_seen_at',
        'sample_data',
        'status',
        'resolved_by_user_id',
        'resolved_at',
    ];

    protected $casts = [
        'http_code' => 'integer',
        'line' => 'integer',
        'occurrence_count' => 'integer',
        'first_seen_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'sample_data' => 'array',
        'resolved_at' => 'datetime',
    ];

    public function package(): BelongsTo
    {
        return $this->belongsTo(DevPackage::class, 'dev_package_id');
    }

    public function issue(): BelongsTo
    {
        return $this->belongsTo(DevIssue::class, 'dev_issue_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\Team::class);
    }

    public function resolvedByUser(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\User::class, 'resolved_by_user_id');
    }

    /**
     * „Aktiv" = feuert noch: offen/quittiert UND zuletzt innerhalb des Aktualitäts-Fensters
     * gesehen. Das ist die Default-Sicht — Behobenes/Altlast fällt raus, ohne Status-Mutation.
     */
    public function scopeActive($query, ?int $hours = null)
    {
        return $query
            ->whereIn('status', [self::STATUS_OPEN, self::STATUS_ACKNOWLEDGED])
            ->where('last_seen_at', '>=', now()->subHours($hours ?? self::ACTIVE_WINDOW_HOURS));
    }

    public static function generateHash(Throwable $e, ?int $httpCode = null): string
    {
        $components = [
            get_class($e),
            $e->getFile(),
            $e->getLine(),
            $httpCode ?? 0,
        ];

        return hash('sha256', implode('|', $components));
    }

    public static function generateHashFromComponents(
        string $exceptionClass,
        ?string $file,
        ?int $line,
        ?int $httpCode = null
    ): string {
        $components = [
            $exceptionClass,
            $file ?? '',
            $line ?? 0,
            $httpCode ?? 0,
        ];

        return hash('sha256', implode('|', $components));
    }

    public function recordOccurrence(array $sampleData = []): self
    {
        $this->occurrence_count++;
        $this->last_seen_at = now();

        if (!empty($sampleData)) {
            $this->sample_data = $sampleData;
        }

        $this->save();

        return $this;
    }

    public function resolve(?int $userId = null): self
    {
        $this->status = self::STATUS_RESOLVED;
        $this->resolved_by_user_id = $userId;
        $this->resolved_at = now();
        $this->save();

        return $this;
    }

    public function acknowledge(): self
    {
        $this->status = self::STATUS_ACKNOWLEDGED;
        $this->save();

        return $this;
    }

    public function ignore(): self
    {
        $this->status = self::STATUS_IGNORED;
        $this->save();

        return $this;
    }

    public static function findExistingInDedupeWindow(
        int $packageId,
        string $hash,
        int $dedupeWindowHours
    ): ?self {
        return static::where('dev_package_id', $packageId)
            ->where('error_hash', $hash)
            ->whereIn('status', [self::STATUS_OPEN, self::STATUS_ACKNOWLEDGED])
            ->where('last_seen_at', '>=', now()->subHours($dedupeWindowHours))
            ->first();
    }

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }

    public function isResolved(): bool
    {
        return $this->status === self::STATUS_RESOLVED;
    }

    public function getFormattedLocation(): string
    {
        if ($this->file && $this->line) {
            return "{$this->file}:{$this->line}";
        }

        return $this->file ?? 'Unknown location';
    }

    public function getShortExceptionClass(): string
    {
        if (!$this->exception_class) {
            return 'Unknown';
        }

        $parts = explode('\\', $this->exception_class);

        return end($parts);
    }
}
