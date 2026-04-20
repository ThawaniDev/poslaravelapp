<?php

namespace App\Domain\StaffManagement\Models;

use App\Domain\Shared\Enums\AuthMethod;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AttendanceRecord extends Model
{
    use HasUuids;

    protected $table = 'attendance_records';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'staff_user_id',
        'store_id',
        'clock_in_at',
        'clock_out_at',
        'break_minutes',
        'scheduled_shift_id',
        'overtime_minutes',
        'notes',
        'auth_method',
        'status',
    ];

    protected $casts = [
        'auth_method' => AuthMethod::class,
        'clock_in_at' => 'datetime',
        'clock_out_at' => 'datetime',
    ];

    public function staffUser(): BelongsTo
    {
        return $this->belongsTo(StaffUser::class);
    }
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
    public function breakRecords(): HasMany
    {
        return $this->hasMany(BreakRecord::class);
    }
}
