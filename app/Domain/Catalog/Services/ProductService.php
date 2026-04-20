<?php

namespace App\Domain\Catalog\Services;

use App\Domain\Auth\Models\User;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductBarcode;
use App\Domain\Catalog\Models\InternalBarcodeSequence;
use App\Domain\Catalog\Models\ProductSupplier;
use App\Domain\Catalog\Models\StorePrice;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class ProductService
{
    // ─── Queries ────────────────────────────────────────────────

    public function list(
        string $organizationId,
        array $filters = [],
        int $perPage = 25,
    ): LengthAwarePaginator {
        $query = Product::where('organization_id', $organizationId)
            ->with(['category', 'productBarcodes', 'productImages']);

        if (isset($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        if (isset($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('name_ar', 'like', "%{$search}%")
                  ->orWhere('sku', 'like', "%{$search}%")
                  ->orWhere('barcode', 'like', "%{$search}%");
            });
        }

        if (isset($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }

        if (isset($filters['is_combo'])) {
            $query->where('is_combo', $filters['is_combo']);
        }

        if (isset($filters['unit'])) {
            $query->where('unit', $filters['unit']);
        }

        $sortBy = $filters['sort_by'] ?? 'name';
        $sortDir = $filters['sort_dir'] ?? 'asc';
        $query->orderBy($sortBy, $sortDir);

        return $query->paginate($perPage);
    }

    public function find(string $organizationId, string $productId): Product
    {
        return Product::with([
            'category',
            'productBarcodes',
            'productImages',
            'productVariants',
            'modifierGroups.modifierOptions',
            'productSuppliers.supplier',
            'storePrices',
        ])->where('organization_id', $organizationId)->findOrFail($productId);
    }

    public function catalog(string $organizationId): Collection
    {
        return Product::where('organization_id', $organizationId)
            ->where('is_active', true)
            ->with([
                'category',
                'productBarcodes',
                'productVariants',
                'modifierGroups.modifierOptions',
                'storePrices',
            ])
            ->orderBy('name')
            ->get();
    }

    public function changes(string $organizationId, int $sinceVersion): Collection
    {
        return Product::where('organization_id', $organizationId)
            ->where('sync_version', '>', $sinceVersion)
            ->withTrashed()
            ->with([
                'category',
                'productBarcodes',
                'productVariants',
                'modifierGroups.modifierOptions',
                'storePrices',
            ])
            ->get();
    }

    // ─── CRUD ───────────────────────────────────────────────────

    public function create(array $data, User $actor): Product
    {
        return DB::transaction(function () use ($data, $actor) {
            $barcodes = $data['barcodes'] ?? [];
            $images = $data['images'] ?? [];
            unset($data['barcodes'], $data['images']);

            $data['organization_id'] = $actor->organization_id;
            $data['sync_version'] = 1;

            $product = Product::create($data);

            foreach ($barcodes as $barcode) {
                $product->productBarcodes()->create([
                    'barcode' => $barcode['barcode'],
                    'is_primary' => $barcode['is_primary'] ?? false,
                ]);
            }

            foreach ($images as $index => $image) {
                $product->productImages()->create([
                    'image_url' => $image['image_url'],
                    'sort_order' => $image['sort_order'] ?? $index,
                ]);
            }

            return $product->load(['category', 'productBarcodes', 'productImages']);
        });
    }

    public function update(Product $product, array $data): Product
    {
        return DB::transaction(function () use ($product, $data) {
            $barcodes = $data['barcodes'] ?? null;
            $images = $data['images'] ?? null;
            unset($data['barcodes'], $data['images']);

            $data['sync_version'] = ($product->sync_version ?? 0) + 1;
            $product->update($data);

            if ($barcodes !== null) {
                $product->productBarcodes()->delete();
                foreach ($barcodes as $barcode) {
                    $product->productBarcodes()->create([
                        'barcode' => $barcode['barcode'],
                        'is_primary' => $barcode['is_primary'] ?? false,
                    ]);
                }
            }

            if ($images !== null) {
                $product->productImages()->delete();
                foreach ($images as $index => $image) {
                    $product->productImages()->create([
                        'image_url' => $image['image_url'],
                        'sort_order' => $image['sort_order'] ?? $index,
                    ]);
                }
            }

            return $product->fresh([
                'category', 'productBarcodes', 'productImages',
                'productVariants', 'modifierGroups.modifierOptions',
            ]);
        });
    }

    public function delete(Product $product): void
    {
        $product->update(['sync_version' => ($product->sync_version ?? 0) + 1]);
        $product->delete(); // soft delete
    }

    // ─── Barcode Generation ─────────────────────────────────────

    public function generateBarcode(string $storeId, Product $product): string
    {
        return DB::transaction(function () use ($storeId, $product) {
            $sequence = InternalBarcodeSequence::firstOrCreate(
                ['store_id' => $storeId],
                ['last_sequence' => 0],
            );

            $sequence->increment('last_sequence');
            $seq = str_pad($sequence->last_sequence, 5, '0', STR_PAD_LEFT);
            $prefix = '200';
            $barcode = $prefix . $seq;

            // Calculate EAN-13 check digit
            $barcode = str_pad($barcode, 12, '0', STR_PAD_RIGHT);
            $checkDigit = $this->calculateEan13CheckDigit($barcode);
            $barcode .= $checkDigit;

            $product->productBarcodes()->create([
                'barcode' => $barcode,
                'is_primary' => !$product->productBarcodes()->exists(),
            ]);

            $product->update([
                'barcode' => $product->barcode ?? $barcode,
                'sync_version' => ($product->sync_version ?? 0) + 1,
            ]);

            return $barcode;
        });
    }

    private function calculateEan13CheckDigit(string $digits): int
    {
        $sum = 0;
        for ($i = 0; $i < 12; $i++) {
            $sum += (int) $digits[$i] * ($i % 2 === 0 ? 1 : 3);
        }
        return (10 - ($sum % 10)) % 10;
    }

    // ─── Variants ───────────────────────────────────────────────

    public function syncVariants(Product $product, array $variants): Product
    {
        return DB::transaction(function () use ($product, $variants) {
            $product->productVariants()->delete();

            foreach ($variants as $variant) {
                $product->productVariants()->create([
                    'variant_group_id' => $variant['variant_group_id'],
                    'variant_value' => $variant['variant_value'],
                    'variant_value_ar' => $variant['variant_value_ar'] ?? null,
                    'sku' => $variant['sku'] ?? null,
                    'barcode' => $variant['barcode'] ?? null,
                    'price_adjustment' => $variant['price_adjustment'] ?? 0,
                    'is_active' => $variant['is_active'] ?? true,
                ]);
            }

            $product->update(['sync_version' => ($product->sync_version ?? 0) + 1]);

            return $product->fresh(['productVariants']);
        });
    }

    // ─── Modifiers ──────────────────────────────────────────────

    public function syncModifiers(Product $product, array $groups): Product
    {
        return DB::transaction(function () use ($product, $groups) {
            $product->modifierGroups()->each(function ($group) {
                $group->modifierOptions()->delete();
            });
            $product->modifierGroups()->delete();

            foreach ($groups as $groupData) {
                $options = $groupData['options'] ?? [];
                unset($groupData['options']);

                $group = $product->modifierGroups()->create($groupData);

                foreach ($options as $index => $option) {
                    $group->modifierOptions()->create(array_merge($option, [
                        'sort_order' => $option['sort_order'] ?? $index,
                    ]));
                }
            }

            $product->update(['sync_version' => ($product->sync_version ?? 0) + 1]);

            return $product->fresh(['modifierGroups.modifierOptions']);
        });
    }

    // ─── Bulk Actions ───────────────────────────────────────────

    public function bulkAction(string $organizationId, array $productIds, string $action, ?string $categoryId = null): int
    {
        $query = Product::where('organization_id', $organizationId)
            ->whereIn('id', $productIds);

        return match ($action) {
            'activate' => $query->update(['is_active' => true, 'sync_version' => DB::raw('COALESCE(sync_version, 0) + 1')]),
            'deactivate' => $query->update(['is_active' => false, 'sync_version' => DB::raw('COALESCE(sync_version, 0) + 1')]),
            'delete' => DB::transaction(function () use ($query) {
                $query->update(['sync_version' => DB::raw('COALESCE(sync_version, 0) + 1')]);
                return $query->delete(); // soft delete
            }),
            'change_category' => $query->update([
                'category_id' => $categoryId,
                'sync_version' => DB::raw('COALESCE(sync_version, 0) + 1'),
            ]),
            default => 0,
        };
    }

    // ─── Duplicate ──────────────────────────────────────────────

    public function duplicate(Product $product): Product
    {
        return DB::transaction(function () use ($product) {
            $newProduct = $product->replicate(['id', 'barcode', 'sku', 'sync_version', 'deleted_at']);
            $newProduct->name = $product->name . ' (Copy)';
            $newProduct->name_ar = $product->name_ar ? $product->name_ar . ' (نسخة)' : null;
            $newProduct->sku = null;
            $newProduct->barcode = null;
            $newProduct->sync_version = 1;
            $newProduct->save();

            // Copy barcodes (not included — they need to be unique)
            // Copy images
            foreach ($product->productImages as $image) {
                $newProduct->productImages()->create([
                    'image_url' => $image->image_url,
                    'sort_order' => $image->sort_order,
                ]);
            }

            // Copy store prices
            foreach ($product->storePrices as $price) {
                $newProduct->storePrices()->create([
                    'store_id' => $price->store_id,
                    'sell_price' => $price->sell_price,
                    'valid_from' => $price->valid_from,
                    'valid_to' => $price->valid_to,
                ]);
            }

            return $newProduct->load(['category', 'productBarcodes', 'productImages', 'storePrices']);
        });
    }

    // ─── Store Prices ───────────────────────────────────────────

    public function syncStorePrices(Product $product, array $prices): Product
    {
        return DB::transaction(function () use ($product, $prices) {
            $product->storePrices()->delete();

            foreach ($prices as $price) {
                StorePrice::create([
                    'product_id' => $product->id,
                    'store_id' => $price['store_id'],
                    'sell_price' => $price['sell_price'],
                    'valid_from' => $price['valid_from'] ?? null,
                    'valid_to' => $price['valid_to'] ?? null,
                ]);
            }

            $product->update(['sync_version' => ($product->sync_version ?? 0) + 1]);

            return $product->fresh(['storePrices']);
        });
    }

    // ─── Product Suppliers ──────────────────────────────────────

    public function syncSuppliers(Product $product, array $suppliers): Product
    {
        return DB::transaction(function () use ($product, $suppliers) {
            $product->productSuppliers()->delete();

            foreach ($suppliers as $supplier) {
                ProductSupplier::create([
                    'product_id' => $product->id,
                    'supplier_id' => $supplier['supplier_id'],
                    'cost_price' => $supplier['cost_price'] ?? null,
                    'lead_time_days' => $supplier['lead_time_days'] ?? null,
                    'supplier_sku' => $supplier['supplier_sku'] ?? null,
                ]);
            }

            return $product->fresh(['productSuppliers.supplier']);
        });
    }
}
