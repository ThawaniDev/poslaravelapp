<?php

namespace App\Domain\Catalog\Models;

use App\Domain\Inventory\Models\GoodsReceipt;
use App\Domain\Inventory\Models\PurchaseOrder;
use App\Domain\Inventory\Models\SupplierReturn;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supplier extends Model
{
    use HasUuids;

    protected $table = 'suppliers';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'organization_id',
        'name',
        'phone',
        'email',
        'website',
        'address',
        'city',
        'country',
        'postal_code',
        'notes',
        'contact_person',
        'tax_number',
        'payment_terms',
        'bank_name',
        'bank_account',
        'iban',
        'credit_limit',
        'outstanding_balance',
        'rating',
        'category',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'credit_limit' => 'decimal:2',
        'outstanding_balance' => 'decimal:2',
        'rating' => 'integer',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
    public function productSuppliers(): HasMany
    {
        return $this->hasMany(ProductSupplier::class);
    }
    public function goodsReceipts(): HasMany
    {
        return $this->hasMany(GoodsReceipt::class);
    }
    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }
    public function supplierReturns(): HasMany
    {
        return $this->hasMany(SupplierReturn::class);
    }
}
