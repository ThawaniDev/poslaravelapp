<?php

namespace App\Domain\Website\Models;

use App\Domain\Website\Enums\PartnershipApplicationStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class WebsitePartnershipApplication extends Model
{
    use HasUuids;

    protected $table = 'website_partnership_applications';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'reference_number',
        'company_name',
        'contact_name',
        'email',
        'phone',
        'partnership_type',
        'website',
        'message',
        'status',
        'admin_notes',
        'assigned_to',
        'ip_address',
    ];

    protected $attributes = [
        'status' => 'new',
    ];

    protected $casts = [
        'status' => PartnershipApplicationStatus::class,
    ];

    // ─── Boot ────────────────────────────────────────────────

    protected static function booted(): void
    {
        static::creating(function (self $record) {
            if (empty($record->reference_number)) {
                $record->reference_number = 'PTR-' . strtoupper(Str::random(8));
            }
        });
    }

    // ─── Scopes ──────────────────────────────────────────────

    public function scopeNew(Builder $query): Builder
    {
        return $query->where('status', PartnershipApplicationStatus::New);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->whereNotIn('status', [
            PartnershipApplicationStatus::Approved,
            PartnershipApplicationStatus::Rejected,
            PartnershipApplicationStatus::Closed,
        ]);
    }
}
