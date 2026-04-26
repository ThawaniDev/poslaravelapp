<?php

namespace App\Domain\BackupSync\Models;

use App\Domain\Core\Models\Store;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoreBackupSettings extends Model
{
    use HasUuids;

    protected $table = 'store_backup_settings';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'store_id',
        'auto_backup_enabled',
        'frequency',
        'retention_days',
        'encrypt_backups',
        'local_backup_enabled',
        'cloud_backup_enabled',
        'backup_hour',
    ];

    protected $casts = [
        'auto_backup_enabled'  => 'boolean',
        'encrypt_backups'      => 'boolean',
        'local_backup_enabled' => 'boolean',
        'cloud_backup_enabled' => 'boolean',
        'retention_days'       => 'integer',
        'backup_hour'          => 'integer',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * Get or create settings for a store with sensible defaults.
     */
    public static function forStore(string $storeId): self
    {
        return self::firstOrCreate(
            ['store_id' => $storeId],
            [
                'auto_backup_enabled'  => true,
                'frequency'            => 'daily',
                'retention_days'       => 30,
                'encrypt_backups'      => false,
                'local_backup_enabled' => true,
                'cloud_backup_enabled' => true,
                'backup_hour'          => 2,
            ],
        );
    }
}
