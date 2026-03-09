<?php

namespace App\Domain\StaffManagement\Models;

use App\Domain\StaffManagement\Enums\ShiftScheduleStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShiftSchedule extends Model
{
    use HasUuids;

    protected $table = 'shift_schedules';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'store_id',
        'staff_user_id',
        'shift_template_id',
        'date',
        'actual_start',
        'actual_end',
        'status',
        'swapped_with_id',
    ];

    protected $casts = [
        'status' => ShiftScheduleStatus::class,
        'date' => 'date',
        'actual_start' => 'datetime',
        'actual_end' => 'datetime',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
    public function staffUser(): BelongsTo
    {
        return $this->belongsTo(StaffUser::class);
    }
    public function shiftTemplate(): BelongsTo
    {
        return $this->belongsTo(ShiftTemplate::class);
    }
    public function swappedWith(): BelongsTo
    {
        return $this->belongsTo(StaffUser::class, 'swapped_with_id');
    }
}
