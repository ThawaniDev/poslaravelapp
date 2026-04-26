<?php

namespace App\Domain\IndustryElectronics\Models;

use App\Domain\Core\Models\Store;
use App\Domain\Customer\Models\Customer;
use App\Domain\IndustryElectronics\Enums\RepairJobStatus;
use App\Domain\StaffManagement\Models\StaffUser;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RepairJob extends Model
{
    use HasUuids;

    protected $table = 'repair_jobs';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'store_id',
        'customer_id',
        'device_description',
        'imei',
        'issue_description',
        'status',
        'diagnosis_notes',
        'repair_notes',
        'estimated_cost',
        'final_cost',
        'parts_used',
        'staff_user_id',
        'received_at',
        'estimated_ready_at',
        'completed_at',
        'collected_at',
    ];

    protected $casts = [
        'status' => RepairJobStatus::class,
        'parts_used' => 'array',
        'estimated_cost' => 'decimal:2',
        'final_cost' => 'decimal:2',
        'received_at' => 'datetime',
        'estimated_ready_at' => 'datetime',
        'completed_at' => 'datetime',
        'collected_at' => 'datetime',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
    public function staffUser(): BelongsTo
    {
        return $this->belongsTo(StaffUser::class);
    }
}
