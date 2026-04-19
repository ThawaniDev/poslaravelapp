<?php

namespace App\Domain\Catalog\Controllers\Api;

use App\Domain\Catalog\Requests\CreateProductRequest;
use App\Domain\Catalog\Requests\UpdateProductRequest;
use App\Domain\Catalog\Requests\BulkProductActionRequest;
use App\Domain\Catalog\Requests\StorePriceRequest;
use App\Domain\Catalog\Requests\LinkProductSupplierRequest;
use App\Domain\Catalog\Resources\ProductResource;
use App\Domain\Catalog\Resources\StorePriceResource;
use App\Domain\Catalog\Resources\ProductSupplierResource;
use App\Domain\Catalog\Services\ProductService;
use App\Domain\Subscription\Traits\TracksSubscriptionUsage;
use App\Http\Controllers\Api\BaseApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends BaseApiController
{
    use TracksSubscriptionUsage;
    public function __construct(
        private readonly ProductService $productService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'category_id' => 'nullable|uuid',
            'search' => 'nullable|string|max:100',
            'is_active' => 'nullable|boolean',
            'is_combo' => 'nullable|boolean',
            'unit' => 'nullable|string',
            'sort_by' => 'nullable|string|in:name,sell_price,created_at,sku',
            'sort_dir' => 'nullable|string|in:asc,desc',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $paginator = $this->productService->list(
            organizationId: $request->user()->organization_id,
            filters: $request->only(['category_id', 'search', 'is_active', 'is_combo', 'unit', 'sort_by', 'sort_dir']),
            perPage: $request->integer('per_page', 25),
        );

        $data = $paginator->toArray();
        $data['data'] = ProductResource::collection($paginator->items())->resolve();

        return $this->success($data);
    }

    public function store(CreateProductRequest $request): JsonResponse
    {
        $product = $this->productService->create(
            $request->validated(),
            $request->user(),
        );

        // Refresh product usage snapshot after creation
        $orgId = $this->resolveOrganizationId($request);
        if ($orgId) {
            $this->refreshUsageFor($orgId, 'products');
        }

        return $this->created(new ProductResource($product));
    }

    public function show(Request $request, string $product): JsonResponse
    {
        $found = $this->productService->find($product);

        if ($found->organization_id !== $request->user()->organization_id) {
            return $this->notFound('Product not found.');
        }

        return $this->success(new ProductResource($found));
    }

    public function update(UpdateProductRequest $request, string $product): JsonResponse
    {
        $found = $this->productService->find($product);

        // Ensure product belongs to user's organization
        if ($found->organization_id !== $request->user()->organization_id) {
            return $this->notFound('Product not found.');
        }

        $updated = $this->productService->update($found, $request->validated());

        return $this->success(new ProductResource($updated));
    }

    public function destroy(Request $request, string $product): JsonResponse
    {
        $found = $this->productService->find($product);

        if ($found->organization_id !== $request->user()->organization_id) {
            return $this->notFound('Product not found.');
        }

        $this->productService->delete($found);

        // Refresh product usage snapshot after deletion
        $orgId = $this->resolveOrganizationId($request);
        if ($orgId) {
            $this->refreshUsageFor($orgId, 'products');
        }

        return $this->success(null, 'Product deleted successfully.');
    }

    public function catalog(Request $request): JsonResponse
    {
        $products = $this->productService->catalog($request->user()->organization_id);

        return $this->success(ProductResource::collection($products));
    }

    public function changes(Request $request): JsonResponse
    {
        $request->validate(['since' => 'required|integer|min:0']);

        $products = $this->productService->changes(
            $request->user()->organization_id,
            $request->integer('since'),
        );

        return $this->success(ProductResource::collection($products));
    }

    public function generateBarcode(Request $request, string $product): JsonResponse
    {
        $found = $this->productService->find($product);

        if ($found->organization_id !== $request->user()->organization_id) {
            return $this->notFound('Product not found.');
        }

        $barcode = $this->productService->generateBarcode(
            $request->user()->store_id,
            $found,
        );

        return $this->success(['barcode' => $barcode], 'Barcode generated successfully.');
    }

    public function barcodes(Request $request, string $product): JsonResponse
    {
        $found = $this->productService->find($product);

        if ($found->organization_id !== $request->user()->organization_id) {
            return $this->notFound('Product not found.');
        }

        return $this->success(
            $found->productBarcodes->map(fn ($b) => [
                'id' => $b->id,
                'barcode' => $b->barcode,
                'is_primary' => (bool) $b->is_primary,
            ])
        );
    }

    public function variants(Request $request, string $product): JsonResponse
    {
        $found = $this->productService->find($product);

        if ($found->organization_id !== $request->user()->organization_id) {
            return $this->notFound('Product not found.');
        }

        return $this->success(
            $found->productVariants->map(fn ($v) => [
                'id' => $v->id,
                'variant_group_id' => $v->variant_group_id,
                'variant_value' => $v->variant_value,
                'variant_value_ar' => $v->variant_value_ar,
                'sku' => $v->sku,
                'barcode' => $v->barcode,
                'price_adjustment' => (float) $v->price_adjustment,
                'is_active' => (bool) $v->is_active,
            ])
        );
    }

    public function syncVariants(Request $request, string $product): JsonResponse
    {
        $request->validate([
            'variants' => 'required|array',
            'variants.*.variant_group_id' => 'required|uuid|exists:product_variant_groups,id',
            'variants.*.variant_value' => 'required|string|max:100',
            'variants.*.variant_value_ar' => 'nullable|string|max:100',
            'variants.*.sku' => 'nullable|string|max:100',
            'variants.*.barcode' => 'nullable|string|max:50',
            'variants.*.price_adjustment' => 'nullable|numeric',
            'variants.*.is_active' => 'sometimes|boolean',
        ]);

        $found = $this->productService->find($product);

        if ($found->organization_id !== $request->user()->organization_id) {
            return $this->notFound('Product not found.');
        }

        $updated = $this->productService->syncVariants($found, $request->variants);

        return $this->success(new ProductResource($updated));
    }

    public function modifiers(Request $request, string $product): JsonResponse
    {
        $found = $this->productService->find($product);

        if ($found->organization_id !== $request->user()->organization_id) {
            return $this->notFound('Product not found.');
        }

        return $this->success(
            $found->modifierGroups->map(fn ($g) => [
                'id' => $g->id,
                'name' => $g->name,
                'name_ar' => $g->name_ar,
                'is_required' => (bool) $g->is_required,
                'min_select' => $g->min_select,
                'max_select' => $g->max_select,
                'options' => $g->modifierOptions->map(fn ($o) => [
                    'id' => $o->id,
                    'name' => $o->name,
                    'name_ar' => $o->name_ar,
                    'price_adjustment' => (float) $o->price_adjustment,
                    'is_default' => (bool) $o->is_default,
                ]),
            ])
        );
    }

    public function syncModifiers(Request $request, string $product): JsonResponse
    {
        $request->validate([
            'groups' => 'required|array',
            'groups.*.name' => 'required|string|max:255',
            'groups.*.name_ar' => 'nullable|string|max:255',
            'groups.*.is_required' => 'sometimes|boolean',
            'groups.*.min_select' => 'sometimes|integer|min:0',
            'groups.*.max_select' => 'sometimes|integer|min:0',
            'groups.*.options' => 'sometimes|array',
            'groups.*.options.*.name' => 'required|string|max:255',
            'groups.*.options.*.name_ar' => 'nullable|string|max:255',
            'groups.*.options.*.price_adjustment' => 'nullable|numeric',
            'groups.*.options.*.is_default' => 'sometimes|boolean',
        ]);

        $found = $this->productService->find($product);

        if ($found->organization_id !== $request->user()->organization_id) {
            return $this->notFound('Product not found.');
        }

        $updated = $this->productService->syncModifiers($found, $request->groups);

        return $this->success(new ProductResource($updated));
    }

    // ─── Bulk Actions ───────────────────────────────────────────

    public function bulkAction(BulkProductActionRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $affected = $this->productService->bulkAction(
            $request->user()->organization_id,
            $validated['product_ids'],
            $validated['action'],
            $validated['category_id'] ?? null,
        );

        return $this->success(['affected' => $affected], "Bulk action '{$validated['action']}' applied to {$affected} product(s).");
    }

    // ─── Duplicate ──────────────────────────────────────────────

    public function duplicate(Request $request, string $product): JsonResponse
    {
        $found = $this->productService->find($product);

        if ($found->organization_id !== $request->user()->organization_id) {
            return $this->notFound('Product not found.');
        }

        $copy = $this->productService->duplicate($found);

        return $this->created(new ProductResource($copy));
    }

    // ─── Store Prices ───────────────────────────────────────────

    public function storePrices(Request $request, string $product): JsonResponse
    {
        $found = $this->productService->find($product);

        if ($found->organization_id !== $request->user()->organization_id) {
            return $this->notFound('Product not found.');
        }

        return $this->success(StorePriceResource::collection($found->storePrices));
    }

    public function syncStorePrices(StorePriceRequest $request, string $product): JsonResponse
    {
        $found = $this->productService->find($product);

        if ($found->organization_id !== $request->user()->organization_id) {
            return $this->notFound('Product not found.');
        }

        $updated = $this->productService->syncStorePrices($found, $request->validated()['prices']);

        return $this->success(StorePriceResource::collection($updated->storePrices));
    }

    // ─── Product Suppliers ──────────────────────────────────────

    public function suppliers(Request $request, string $product): JsonResponse
    {
        $found = $this->productService->find($product);

        if ($found->organization_id !== $request->user()->organization_id) {
            return $this->notFound('Product not found.');
        }

        return $this->success(ProductSupplierResource::collection($found->productSuppliers));
    }

    public function syncSuppliers(LinkProductSupplierRequest $request, string $product): JsonResponse
    {
        $found = $this->productService->find($product);

        if ($found->organization_id !== $request->user()->organization_id) {
            return $this->notFound('Product not found.');
        }

        $updated = $this->productService->syncSuppliers($found, $request->validated()['suppliers']);

        return $this->success(ProductSupplierResource::collection($updated->productSuppliers));
    }
}
