<?php

namespace App\Domain\Core\Models;

use App\Domain\Core\Enums\RegisterPlatform;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Register extends Model
{
    use HasUuids;

    protected $table = 'registers';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'store_id',
        'name',
        'code',
        'device_id',
        'app_version',
        'platform',
        'last_sync_at',
        'is_online',
        'is_active',
        // SoftPOS
        'softpos_enabled',
        'softpos_provider',
        'nearpay_tid',
        'nearpay_mid',
        'nearpay_auth_key',
        'edfapay_token',
        'edfapay_token_updated_at',
        // Acquirer
        'acquirer_source',
        'acquirer_name',
        'acquirer_reference',
        // Device hardware
        'device_model',
        'os_version',
        'nfc_capable',
        'serial_number',
        // Fee config
        'fee_profile',
        'fee_mada_percentage',
        'fee_visa_mc_percentage',
        'fee_flat_per_txn',
        'wameed_margin_percentage',
        // SoftPOS billing rates (bilateral: merchant-facing + gateway-facing)
        'softpos_mada_merchant_rate',
        'softpos_mada_gateway_rate',
        'softpos_card_merchant_rate',
        'softpos_card_gateway_rate',
        'softpos_card_merchant_fee',
        'softpos_card_gateway_fee',
        // Settlement
        'settlement_cycle',
        'settlement_bank_name',
        'settlement_iban',
        // Status
        'softpos_status',
        'softpos_activated_at',
        'last_transaction_at',
        'admin_notes',
    ];

    protected $casts = [
        'platform'               => RegisterPlatform::class,
        'is_online'              => 'boolean',
        'is_active'              => 'boolean',
        'last_sync_at'           => 'datetime',
        'softpos_enabled'        => 'boolean',
        'nfc_capable'            => 'boolean',
        'fee_mada_percentage'    => 'decimal:4',
        'fee_visa_mc_percentage' => 'decimal:4',
        'fee_flat_per_txn'       => 'decimal:2',
        'wameed_margin_percentage' => 'decimal:4',
        'softpos_mada_merchant_rate' => 'decimal:6',
        'softpos_mada_gateway_rate'  => 'decimal:6',
        'softpos_card_merchant_rate' => 'decimal:6',
        'softpos_card_gateway_rate'  => 'decimal:6',
        'softpos_card_merchant_fee'  => 'decimal:3',
        'softpos_card_gateway_fee'   => 'decimal:3',
        'softpos_activated_at'      => 'datetime',
        'last_transaction_at'       => 'datetime',
        'edfapay_token'             => 'encrypted',
        'edfapay_token_updated_at'  => 'datetime',
    ];

    protected $hidden = [
        'nearpay_auth_key',
        'edfapay_token',
    ];

    protected static function booted(): void
    {
        static::updating(function (Register $register) {
            // Auto-stamp token updated time whenever the EdfaPay token is changed
            if ($register->isDirty('edfapay_token') && ! $register->isDirty('edfapay_token_updated_at')) {
                $register->edfapay_token_updated_at = now();
            }

            // Auto-stamp activation time the first time softpos_status becomes 'active'
            if (
                $register->isDirty('softpos_status')
                && $register->softpos_status === 'active'
                && is_null($register->getOriginal('softpos_activated_at'))
                && ! $register->isDirty('softpos_activated_at')
            ) {
                $register->softpos_activated_at = now();
            }
        });
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
    public function posSessions(): HasMany
    {
        return $this->hasMany(PosSession::class);
    }
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }
    public function heldCarts(): HasMany
    {
        return $this->hasMany(HeldCart::class);
    }

    // ─── Computed Attributes ────────────────────────────────────

    /**
     * Whether the terminal is ready for SoftPOS operations.
     * Branches on softpos_provider: EdfaPay requires an encrypted token,
     * NearPay requires a TID.
     */
    public function getIsSoftposReadyAttribute(): bool
    {
        $hasCredentials = $this->softpos_provider === 'edfapay'
            ? (bool) $this->edfapay_token
            : (bool) $this->nearpay_tid;

        return $this->softpos_enabled
            && $hasCredentials
            && (bool) $this->acquirer_source
            && $this->softpos_status === 'active';
    }

    /**
     * Formatted merchant fee (e.g. "1.50% mada / 2.00% Visa-MC").
     */
    public function getFeeDescriptionAttribute(): string
    {
        $mada = number_format((float) $this->fee_mada_percentage * 100, 2);
        $visamc = number_format((float) $this->fee_visa_mc_percentage * 100, 2);
        $flat = (float) $this->fee_flat_per_txn;

        $desc = "{$mada}% mada / {$visamc}% Visa-MC";
        if ($flat > 0) {
            $desc .= " + {$flat} SAR/txn";
        }
        return $desc;
    }

    // ─── Scopes ─────────────────────────────────────────────────

    public function scopeSoftposEnabled($query)
    {
        return $query->where('softpos_enabled', true);
    }

    public function scopeByAcquirer($query, string $acquirerSource)
    {
        return $query->where('acquirer_source', $acquirerSource);
    }
}
