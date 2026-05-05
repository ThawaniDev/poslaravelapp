<?php

namespace App\Domain\ProviderRegistration\Models;

use App\Domain\ProviderRegistration\Enums\ProviderRegistrationStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProviderRegistration extends Model
{
    use HasUuids;

    protected $table = 'provider_registrations';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'organization_name',
        'organization_name_ar',
        'owner_name',
        'owner_email',
        'owner_phone',
        'cr_number',
        'vat_number',
        'business_type_id',
        'status',
        'reviewed_by',
        'reviewed_at',
        'rejection_reason',
        'internal_notes',
        'source',
        'plan_id',
    ];

    protected $casts = [
        'status' => ProviderRegistrationStatus::class,
        'reviewed_at' => 'datetime',
    ];

    public function businessType(): BelongsTo
    {
        return $this->belongsTo(BusinessType::class);
    }
    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'reviewed_by');
    }
}
