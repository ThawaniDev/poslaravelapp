<?php

namespace App\Domain\Core\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoreWorkingHour extends Model
{
    use HasUuids;

    protected $table = 'store_working_hours';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'store_id',
        'day_of_week',
        'is_open',
        'open_time',
        'close_time',
        'break_start',
        'break_end',
    ];

    protected $casts = [
        'day_of_week' => 'integer',
        'is_open' => 'boolean',
    ];

    // ─── Constants ───────────────────────────────────────────────

    public const DAYS = [
        0 => 'Sunday',
        1 => 'Monday',
        2 => 'Tuesday',
        3 => 'Wednesday',
        4 => 'Thursday',
        5 => 'Friday',
        6 => 'Saturday',
    ];

    // ─── Relationships ───────────────────────────────────────────

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    // ─── Helpers ─────────────────────────────────────────────────

    public function dayName(): string
    {
        return self::DAYS[$this->day_of_week] ?? 'Unknown';
    }

    /**
     * Is the store currently open based on these hours?
     */
    public function isCurrentlyOpen(): bool
    {
        if (!$this->is_open) return false;
        if (!$this->open_time || !$this->close_time) return false;

        $now = now()->format('H:i:s');

        // Handle break period
        if ($this->break_start && $this->break_end) {
            if ($now >= $this->break_start && $now < $this->break_end) {
                return false;
            }
        }

        return $now >= $this->open_time && $now < $this->close_time;
    }
}
