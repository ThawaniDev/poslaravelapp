<?php

namespace App\Domain\PredefinedCatalog\Services;

use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductImage;
use App\Domain\PredefinedCatalog\Models\PredefinedCategory;
use App\Domain\PredefinedCatalog\Models\PredefinedProduct;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class PredefinedCatalogService
{
    // ═══════════════════════════════════════════════════════════════
    // ─── Categories ──────────────────────────────────────────────
    // ═══════════════════════════════════════════════════════════════

    public function listCategories(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        $query = PredefinedCategory::with(['businessType', 'children'])
            ->withCount('products');

        if (isset($filters['business_type_id'])) {
            $query->where('business_type_id', $filters['business_type_id']);
        }

        if (isset($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('name_ar', 'like', "%{$search}%");
            });
        }

        if (isset($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }

        if (isset($filters['parent_only']) && $filters['parent_only']) {
            $query->whereNull('parent_id');
        }

        $query->orderBy('sort_order')->orderBy('name');

        return $query->paginate($perPage);
    }

    public function categoryTree(string $businessTypeId): Collection
    {
        return PredefinedCategory::where('business_type_id', $businessTypeId)
            ->whereNull('parent_id')
            ->where('is_active', true)
            ->with(['children' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order')])
            ->withCount('products')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    public function findCategory(string $id): PredefinedCategory
    {
        return PredefinedCategory::with(['businessType', 'children', 'products'])
            ->withCount('products')
            ->findOrFail($id);
    }

    public function createCategory(array $data): PredefinedCategory
    {
        return PredefinedCategory::create($data);
    }

    public function updateCategory(PredefinedCategory $category, array $data): PredefinedCategory
    {
        $category->update($data);
        return $category->fresh(['businessType', 'children']);
    }

    public function deleteCategory(PredefinedCategory $category): void
    {
        $productCount = $category->products()->count();
        if ($productCount > 0) {
            throw new \RuntimeException(
                "Cannot delete category '{$category->name}': {$productCount} predefined product(s) assigned. Move or delete them first."
            );
        }

        $childCount = $category->children()->count();
        if ($childCount > 0) {
            throw new \RuntimeException(
                "Cannot delete category '{$category->name}': {$childCount} sub-categories exist. Delete them first."
            );
        }

        $category->delete();
    }

    // ═══════════════════════════════════════════════════════════════
    // ─── Products ────────────────────────────────────────────────
    // ═══════════════════════════════════════════════════════════════

    public function listProducts(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        $query = PredefinedProduct::with(['businessType', 'predefinedCategory', 'images']);

        if (isset($filters['business_type_id'])) {
            $query->where('business_type_id', $filters['business_type_id']);
        }

        if (isset($filters['predefined_category_id'])) {
            $query->where('predefined_category_id', $filters['predefined_category_id']);
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

        $sortBy = $filters['sort_by'] ?? 'name';
        $sortDir = $filters['sort_dir'] ?? 'asc';
        $allowedSorts = ['name', 'sell_price', 'created_at', 'sku'];
        if (in_array($sortBy, $allowedSorts, true)) {
            $query->orderBy($sortBy, $sortDir);
        }

        return $query->paginate($perPage);
    }

    public function findProduct(string $id): PredefinedProduct
    {
        return PredefinedProduct::with(['businessType', 'predefinedCategory', 'images'])
            ->findOrFail($id);
    }

    public function createProduct(array $data): PredefinedProduct
    {
        return DB::transaction(function () use ($data) {
            $images = $data['images'] ?? [];
            unset($data['images']);

            $product = PredefinedProduct::create($data);

            foreach ($images as $index => $image) {
                $product->images()->create([
                    'image_url' => $image['image_url'],
                    'sort_order' => $image['sort_order'] ?? $index,
                ]);
            }

            return $product->load(['businessType', 'predefinedCategory', 'images']);
        });
    }

    public function updateProduct(PredefinedProduct $product, array $data): PredefinedProduct
    {
        return DB::transaction(function () use ($product, $data) {
            $images = $data['images'] ?? null;
            unset($data['images']);

            $product->update($data);

            if ($images !== null) {
                $product->images()->delete();
                foreach ($images as $index => $image) {
                    $product->images()->create([
                        'image_url' => $image['image_url'],
                        'sort_order' => $image['sort_order'] ?? $index,
                    ]);
                }
            }

            return $product->fresh(['businessType', 'predefinedCategory', 'images']);
        });
    }

    public function deleteProduct(PredefinedProduct $product): void
    {
        // FK cascade is bypassed in tests (session_replication_role=replica); delete children explicitly.
        $product->images()->delete();
        $product->delete();
    }

    // ═══════════════════════════════════════════════════════════════
    // ─── Clone to Store ──────────────────────────────────────────
    // ═══════════════════════════════════════════════════════════════

    /**
     * Clone a predefined category (and optionally its products) into a store.
     */
    public function cloneCategory(PredefinedCategory $source, string $organizationId, bool $includeProducts = true): Category
    {
        return DB::transaction(function () use ($source, $organizationId, $includeProducts) {
            $category = Category::create([
                'organization_id' => $organizationId,
                'name' => $source->name,
                'name_ar' => $source->name_ar,
                'description' => $source->description,
                'description_ar' => $source->description_ar,
                'image_url' => $source->image_url,
                'sort_order' => $source->sort_order,
                'is_active' => true,
                'sync_version' => 1,
            ]);

            if ($includeProducts) {
                foreach ($source->products()->with('images')->where('is_active', true)->get() as $predefinedProduct) {
                    $this->cloneProductToCategory($predefinedProduct, $organizationId, $category->id);
                }
            }

            // Clone children recursively
            foreach ($source->children()->where('is_active', true)->get() as $child) {
                $childCategory = Category::create([
                    'organization_id' => $organizationId,
                    'parent_id' => $category->id,
                    'name' => $child->name,
                    'name_ar' => $child->name_ar,
                    'description' => $child->description,
                    'description_ar' => $child->description_ar,
                    'image_url' => $child->image_url,
                    'sort_order' => $child->sort_order,
                    'is_active' => true,
                    'sync_version' => 1,
                ]);

                if ($includeProducts) {
                    foreach ($child->products()->with('images')->where('is_active', true)->get() as $predefinedProduct) {
                        $this->cloneProductToCategory($predefinedProduct, $organizationId, $childCategory->id);
                    }
                }
            }

            return $category->load('categories');
        });
    }

    /**
     * Clone a single predefined product into the store's catalog.
     */
    public function cloneProduct(PredefinedProduct $source, string $organizationId, ?string $categoryId = null): Product
    {
        return $this->cloneProductToCategory($source, $organizationId, $categoryId);
    }

    /**
     * Clone all predefined products/categories for a business type into a store.
     */
    public function cloneAllForBusinessType(string $businessTypeId, string $organizationId): array
    {
        return DB::transaction(function () use ($businessTypeId, $organizationId) {
            $categories = PredefinedCategory::where('business_type_id', $businessTypeId)
                ->whereNull('parent_id')
                ->where('is_active', true)
                ->with(['children', 'products'])
                ->get();

            $clonedCategories = 0;
            $clonedProducts = 0;

            foreach ($categories as $category) {
                $this->cloneCategory($category, $organizationId, true);
                $clonedCategories++;
                $clonedProducts += $category->products()->where('is_active', true)->count();

                foreach ($category->children()->where('is_active', true)->get() as $child) {
                    $clonedCategories++;
                    $clonedProducts += $child->products()->where('is_active', true)->count();
                }
            }

            // Clone uncategorized products
            $uncategorized = PredefinedProduct::where('business_type_id', $businessTypeId)
                ->whereNull('predefined_category_id')
                ->where('is_active', true)
                ->with('images')
                ->get();

            foreach ($uncategorized as $product) {
                $this->cloneProductToCategory($product, $organizationId, null);
                $clonedProducts++;
            }

            return [
                'categories_cloned' => $clonedCategories,
                'products_cloned' => $clonedProducts,
            ];
        });
    }

    private function cloneProductToCategory(PredefinedProduct $source, string $organizationId, ?string $categoryId): Product
    {
        $product = Product::create([
            'organization_id' => $organizationId,
            'category_id' => $categoryId,
            'name' => $source->name,
            'name_ar' => $source->name_ar,
            'description' => $source->description,
            'description_ar' => $source->description_ar,
            'sku' => $source->sku,
            'barcode' => $source->barcode,
            'sell_price' => $source->sell_price,
            'cost_price' => $source->cost_price,
            'unit' => $source->unit?->value,
            'tax_rate' => $source->tax_rate,
            'is_weighable' => $source->is_weighable,
            'tare_weight' => $source->tare_weight ?? 0,
            'is_active' => true,
            'is_combo' => false,
            'age_restricted' => $source->age_restricted,
            'image_url' => $source->image_url,
            'sync_version' => 1,
        ]);

        // Clone images
        foreach ($source->images as $image) {
            $product->productImages()->create([
                'image_url' => $image->image_url,
                'sort_order' => $image->sort_order,
            ]);
        }

        return $product;
    }

    // ═══════════════════════════════════════════════════════════════
    // ─── Bulk Operations ─────────────────────────────────────────
    // ═══════════════════════════════════════════════════════════════

    public function bulkDeleteProducts(array $productIds): int
    {
        return PredefinedProduct::whereIn('id', $productIds)->delete();
    }

    public function bulkToggleProducts(array $productIds, bool $isActive): int
    {
        return PredefinedProduct::whereIn('id', $productIds)->update(['is_active' => $isActive]);
    }
}
