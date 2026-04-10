<?php

namespace App\Domain\Payment\Models;

use App\Domain\Core\Models\Store;
use App\Domain\Payment\Enums\InstallmentProvider;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoreInstallmentConfig extends Model
{
    use HasUuids;

    protected $table = 'store_installment_configs';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'store_id',
        'provider',
        'is_enabled',
        'environment',
        'credentials',
        'merchant_code',
        'webhook_url',
        'success_url',
        'failure_url',
        'cancel_url',
        'config',
    ];

    protected $casts = [
        'provider' => InstallmentProvider::class,
        'is_enabled' => 'boolean',
        'credentials' => 'encrypted:array',
        'config' => 'array',
    ];

    protected $hidden = [
        'credentials',
    ];

    // ─── Relationships ───────────────────────────────────────────

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function providerConfig(): BelongsTo
    {
        return $this->belongsTo(InstallmentProviderConfig::class, 'provider', 'provider');
    }

    // ─── Helpers ─────────────────────────────────────────────────

    public function getCredential(string $key, mixed $default = null): mixed
    {
        return data_get($this->credentials, $key, $default);
    }

    public function isFullyConfigured(): bool
    {
        $creds = $this->credentials ?? [];
        return match ($this->provider) {
            InstallmentProvider::Tabby => !empty($creds['public_key']) && !empty($creds['secret_key']) && !empty($creds['merchant_code']),
            InstallmentProvider::Tamara => !empty($creds['api_token']),
            InstallmentProvider::MisPay => !empty($creds['app_id']) && !empty($creds['app_secret']),
            InstallmentProvider::Madfu => !empty($creds['api_key']) && !empty($creds['app_code']) && !empty($creds['authorization']),
            default => false,
        };
    }

    public function isAvailable(): bool
    {
        return $this->is_enabled && $this->isFullyConfigured();
    }

    /**
     * Get credentials with sensitive values masked for display.
     */
    public function getMaskedCredentials(): array
    {
        $creds = $this->credentials ?? [];
        $masked = [];
        foreach ($creds as $key => $value) {
            if (is_string($value) && strlen($value) > 8) {
                $masked[$key] = substr($value, 0, 4) . str_repeat('•', min(strlen($value) - 8, 20)) . substr($value, -4);
            } else {
                $masked[$key] = $value;
            }
        }
        return $masked;
    }
}
