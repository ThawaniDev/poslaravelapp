<?php

namespace App\Domain\Website\Models;

use App\Domain\Website\Enums\ConsultationRequestStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class WebsiteConsultationRequest extends Model
{
    use HasUuids;

    protected $table = 'website_consultation_requests';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'reference_number',
        'full_name',
        'business_name',
        'email',
        'phone',
        'cr_number',
        'vat_number',
        'current_pos_system',
        'consultation_type',
        'branches',
        'message',
        'status',
        'admin_notes',
        'assigned_to',
        'scheduled_at',
        'ip_address',
    ];

    protected $attributes = [
        'status' => 'new',
    ];

    protected $casts = [
        'status' => ConsultationRequestStatus::class,
        'scheduled_at' => 'datetime',
    ];

    // ─── Boot ────────────────────────────────────────────────

    protected static function booted(): void
    {
        static::creating(function (self $record) {
            if (empty($record->reference_number)) {
                $record->reference_number = 'CON-' . strtoupper(Str::random(8));
            }
        });
    }

    // ─── Scopes ──────────────────────────────────────────────

    public function scopeNew(Builder $query): Builder
    {
        return $query->where('status', ConsultationRequestStatus::New);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->whereNotIn('status', [
            ConsultationRequestStatus::Completed,
            ConsultationRequestStatus::Closed,
        ]);
    }
}
