<?php

namespace App\Domain\Website\Models;

use App\Domain\Website\Enums\HardwareQuoteStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class WebsiteHardwareQuote extends Model
{
    use HasUuids;

    protected $table = 'website_hardware_quotes';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'reference_number',
        'full_name',
        'business_name',
        'email',
        'phone',
        'hardware_bundle',
        'terminal_quantity',
        'needs_printer',
        'needs_scanner',
        'needs_cash_drawer',
        'needs_payment_terminal',
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
        'status' => HardwareQuoteStatus::class,
        'terminal_quantity' => 'integer',
        'needs_printer' => 'boolean',
        'needs_scanner' => 'boolean',
        'needs_cash_drawer' => 'boolean',
        'needs_payment_terminal' => 'boolean',
    ];

    // ─── Boot ────────────────────────────────────────────────

    protected static function booted(): void
    {
        static::creating(function (self $record) {
            if (empty($record->reference_number)) {
                $record->reference_number = 'HW-' . strtoupper(Str::random(8));
            }
        });
    }

    // ─── Scopes ──────────────────────────────────────────────

    public function scopeNew(Builder $query): Builder
    {
        return $query->where('status', HardwareQuoteStatus::New);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->whereNotIn('status', [
            HardwareQuoteStatus::Ordered,
            HardwareQuoteStatus::Closed,
        ]);
    }
}
