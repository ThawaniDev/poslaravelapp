<?php

namespace App\Domain\StaffManagement\Models;

use App\Domain\Auth\Models\User;
use App\Domain\Core\Models\Store;
use App\Domain\StaffManagement\Enums\EmploymentType;
use App\Domain\StaffManagement\Enums\SalaryType;
use App\Domain\StaffManagement\Enums\StaffStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Permission\Traits\HasRoles;

class StaffUser extends Model
{
    use HasUuids, HasRoles;

    protected $table = 'staff_users';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $guard_name = 'staff';

    protected $fillable = [
        'store_id',
        'user_id',
        'first_name',
        'last_name',
        'email',
        'phone',
        'photo_url',
        'national_id',
        'pin_hash',
        'nfc_badge_uid',
        'biometric_enabled',
        'employment_type',
        'salary_type',
        'hourly_rate',
        'hire_date',
        'termination_date',
        'status',
        'language_preference',
    ];

    protected $casts = [
        'employment_type' => EmploymentType::class,
        'salary_type' => SalaryType::class,
        'status' => StaffStatus::class,
        'biometric_enabled' => 'boolean',
        'hourly_rate' => 'decimal:2',
        'hire_date' => 'date',
        'termination_date' => 'date',
    ];

    protected $hidden = [
        'pin_hash',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function staffBranchAssignments(): HasMany
    {
        return $this->hasMany(StaffBranchAssignment::class);
    }
    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class);
    }
    public function shiftSchedules(): HasMany
    {
        return $this->hasMany(ShiftSchedule::class);
    }
    public function shiftSchedulesViaSwappedWith(): HasMany
    {
        return $this->hasMany(ShiftSchedule::class, 'swapped_with_id');
    }
    public function commissionRules(): HasMany
    {
        return $this->hasMany(CommissionRule::class);
    }
    public function commissionEarnings(): HasMany
    {
        return $this->hasMany(CommissionEarning::class);
    }
    public function staffActivityLog(): HasMany
    {
        return $this->hasMany(StaffActivityLog::class);
    }
    public function trainingSessions(): HasMany
    {
        return $this->hasMany(TrainingSession::class);
    }
    public function staffDocuments(): HasMany
    {
        return $this->hasMany(StaffDocument::class);
    }
    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class, 'staff_id');
    }
    public function buybackTransactions(): HasMany
    {
        return $this->hasMany(BuybackTransaction::class);
    }
    public function repairJobs(): HasMany
    {
        return $this->hasMany(RepairJob::class);
    }
    public function tradeInRecords(): HasMany
    {
        return $this->hasMany(TradeInRecord::class);
    }
}
