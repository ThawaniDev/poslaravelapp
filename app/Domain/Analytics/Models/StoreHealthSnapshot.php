<?php

namespace App\Domain\Analytics\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use App\Domain\Core\Models\Store;

class StoreHealthSnapshot extends Model
{
    use HasUuids;

    protected $table = 'store_health_snapshots';
    public $timestamps = false;
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'store_id',
        'date',
        'sync_status',
        'zatca_compliance',
        'error_count',
        'last_activity_at',
    ];

    protected $casts = [
        'date' => 'date',
        'zatca_compliance' => 'boolean',
    ];

    public function setDateAttribute($value): void
    {
        $this->attributes['date'] = $value instanceof \DateTimeInterface
            ? $value->format('Y-m-d')
            : $value;
    }

    public function store()
    {
        return $this->belongsTo(Store::class);
    }
}
