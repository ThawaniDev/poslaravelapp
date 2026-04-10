<?php

namespace App\Domain\Payment\Models;

use App\Domain\Payment\Enums\InstallmentProvider;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InstallmentProviderConfig extends Model
{
    use HasUuids;

    protected $table = 'installment_providers';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'provider',
        'name',
        'name_ar',
        'logo_url',
        'description',
        'description_ar',
        'supported_currencies',
        'min_amount',
        'max_amount',
        'supported_installment_counts',
        'environment',
        'is_enabled',
        'is_under_maintenance',
        'maintenance_message',
        'maintenance_message_ar',
        'platform_config',
        'sort_order',
    ];

    protected $casts = [
        'provider' => InstallmentProvider::class,
        'supported_currencies' => 'array',
        'min_amount' => 'decimal:2',
        'max_amount' => 'decimal:2',
        'supported_installment_counts' => 'array',
        'is_enabled' => 'boolean',
        'is_under_maintenance' => 'boolean',
        'platform_config' => 'array',
        'sort_order' => 'integer',
    ];

    // ─── Relationships ───────────────────────────────────────────

    public function storeConfigs(): HasMany
    {
        return $this->hasMany(StoreInstallmentConfig::class, 'provider', 'provider');
    }

    // ─── Scopes ──────────────────────────────────────────────────

    public function scopeEnabled($query)
    {
        return $query->where('is_enabled', true);
    }

    public function scopeAvailable($query)
    {
        return $query->where('is_enabled', true)->where('is_under_maintenance', false);
    }

    // ─── Helpers ─────────────────────────────────────────────────

    public function isAvailable(): bool
    {
        return $this->is_enabled && !$this->is_under_maintenance;
    }

    public function supportsAmount(float $amount): bool
    {
        return $amount >= $this->min_amount && $amount <= $this->max_amount;
    }

    public function supportsCurrency(string $currency): bool
    {
        return in_array(strtoupper($currency), $this->supported_currencies ?? ['SAR']);
    }
}
