<?php

namespace App\Domain\Catalog\Models;

use App\Domain\Catalog\Enums\ProductUnit;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'products';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'organization_id',
        'category_id',
        'name',
        'name_ar',
        'description',
        'description_ar',
        'sku',
        'barcode',
        'sell_price',
        'cost_price',
        'offer_price',
        'offer_start',
        'offer_end',
        'unit',
        'tax_rate',
        'is_weighable',
        'tare_weight',
        'is_active',
        'is_combo',
        'age_restricted',
        'image_url',
        'min_order_qty',
        'max_order_qty',
        'sync_version',
    ];

    protected $casts = [
        'unit' => ProductUnit::class,
        'is_weighable' => 'boolean',
        'is_active' => 'boolean',
        'is_combo' => 'boolean',
        'age_restricted' => 'boolean',
        'sell_price' => 'decimal:2',
        'cost_price' => 'decimal:2',
        'offer_price' => 'decimal:2',
        'offer_start' => 'date',
        'offer_end' => 'date',
        'tax_rate' => 'decimal:2',
        'tare_weight' => 'decimal:2',
        'min_order_qty' => 'decimal:3',
        'max_order_qty' => 'decimal:3',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
    public function productBarcodes(): HasMany
    {
        return $this->hasMany(ProductBarcode::class);
    }
    public function storePrices(): HasMany
    {
        return $this->hasMany(StorePrice::class);
    }
    public function productVariants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }
    public function productImages(): HasMany
    {
        return $this->hasMany(ProductImage::class);
    }
    public function comboProducts(): HasMany
    {
        return $this->hasMany(ComboProduct::class);
    }
    public function comboProductItems(): HasMany
    {
        return $this->hasMany(ComboProductItem::class);
    }
    public function modifierGroups(): HasMany
    {
        return $this->hasMany(ModifierGroup::class);
    }
    public function productSuppliers(): HasMany
    {
        return $this->hasMany(ProductSupplier::class);
    }
    public function stockLevels(): HasMany
    {
        return $this->hasMany(StockLevel::class);
    }
    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }
    public function goodsReceiptItems(): HasMany
    {
        return $this->hasMany(GoodsReceiptItem::class);
    }
    public function stockAdjustmentItems(): HasMany
    {
        return $this->hasMany(StockAdjustmentItem::class);
    }
    public function stockTransferItems(): HasMany
    {
        return $this->hasMany(StockTransferItem::class);
    }
    public function purchaseOrderItems(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }
    public function stockBatches(): HasMany
    {
        return $this->hasMany(StockBatch::class);
    }
    public function recipes(): HasMany
    {
        return $this->hasMany(Recipe::class);
    }
    public function recipeIngredients(): HasMany
    {
        return $this->hasMany(RecipeIngredient::class, 'ingredient_product_id');
    }
    public function promotionProducts(): HasMany
    {
        return $this->hasMany(PromotionProduct::class);
    }
    public function bundleProducts(): HasMany
    {
        return $this->hasMany(BundleProduct::class);
    }
    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class, 'service_product_id');
    }
    public function giftRegistryItems(): HasMany
    {
        return $this->hasMany(GiftRegistryItem::class);
    }
    public function wishlists(): HasMany
    {
        return $this->hasMany(Wishlist::class);
    }
    public function transactionItems(): HasMany
    {
        return $this->hasMany(TransactionItem::class);
    }
    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
    public function returnItems(): HasMany
    {
        return $this->hasMany(ReturnItem::class);
    }
    public function thawaniProductMappings(): HasMany
    {
        return $this->hasMany(ThawaniProductMapping::class);
    }
    public function productSalesSummary(): HasMany
    {
        return $this->hasMany(ProductSalesSummary::class);
    }
    public function drugSchedule(): HasOne
    {
        return $this->hasOne(DrugSchedule::class);
    }
    public function jewelryProductDetail(): HasOne
    {
        return $this->hasOne(JewelryProductDetail::class);
    }
    public function deviceImeiRecords(): HasMany
    {
        return $this->hasMany(DeviceImeiRecord::class);
    }
    public function flowerFreshnessLog(): HasMany
    {
        return $this->hasMany(FlowerFreshnessLog::class);
    }
    public function bakeryRecipes(): HasMany
    {
        return $this->hasMany(BakeryRecipe::class);
    }
}
