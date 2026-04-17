<?php

namespace App\Domain\PredefinedCatalog\Controllers\Api;

use App\Domain\PredefinedCatalog\Requests\CreatePredefinedProductRequest;
use App\Domain\PredefinedCatalog\Requests\UpdatePredefinedProductRequest;
use App\Domain\PredefinedCatalog\Resources\PredefinedProductResource;
use App\Domain\PredefinedCatalog\Services\PredefinedCatalogService;
use App\Domain\PredefinedCatalog\Services\PredefinedImageUploadService;
use App\Http\Controllers\Api\BaseApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PredefinedProductController extends BaseApiController
{
    public function __construct(
        private readonly PredefinedCatalogService $service,
        private readonly PredefinedImageUploadService $imageService,
    ) {}

    /**
     * GET /predefined-catalog/products — Paginated list (auto-scoped to store's business type).
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'predefined_category_id' => 'nullable|uuid',
            'search' => 'nullable|string|max:100',
            'is_active' => 'nullable|boolean',
            'sort_by' => 'nullable|string|in:name,sell_price,created_at,sku',
            'sort_dir' => 'nullable|string|in:asc,desc',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $businessTypeId = $this->resolveStoreBusinessTypeId($request);

        $filters = $request->only([
            'predefined_category_id', 'search',
            'is_active', 'sort_by', 'sort_dir',
        ]);
        if ($businessTypeId) {
            $filters['business_type_id'] = $businessTypeId;
        }

        $paginator = $this->service->listProducts(
            filters: $filters,
            perPage: $request->integer('per_page', 25),
        );

        return $this->success([
            'data' => PredefinedProductResource::collection($paginator->items()),
            'total' => $paginator->total(),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
        ]);
    }

    /**
     * GET /predefined-catalog/products/{id} — Show single.
     */
    public function show(string $id): JsonResponse
    {
        $product = $this->service->findProduct($id);
        return $this->success(new PredefinedProductResource($product));
    }

    /**
     * POST /predefined-catalog/products — Create.
     */
    public function store(CreatePredefinedProductRequest $request): JsonResponse
    {
        $data = $request->validated();

        if ($request->hasFile('image')) {
            $data['image_url'] = $this->imageService->uploadProductImage($request->file('image'));
        }

        $product = $this->service->createProduct($data);
        return $this->created(new PredefinedProductResource($product));
    }

    /**
     * PUT /predefined-catalog/products/{id} — Update.
     */
    public function update(UpdatePredefinedProductRequest $request, string $id): JsonResponse
    {
        $product = $this->service->findProduct($id);
        $data = $request->validated();

        if ($request->hasFile('image')) {
            if ($product->image_url) {
                $this->imageService->deleteProductImage($product->image_url);
            }
            $data['image_url'] = $this->imageService->uploadProductImage($request->file('image'));
        }

        $updated = $this->service->updateProduct($product, $data);
        return $this->success(new PredefinedProductResource($updated));
    }

    /**
     * DELETE /predefined-catalog/products/{id} — Delete.
     */
    public function destroy(string $id): JsonResponse
    {
        $product = $this->service->findProduct($id);
        $this->service->deleteProduct($product);
        return $this->success(null, 'Predefined product deleted successfully.');
    }

    /**
     * POST /predefined-catalog/products/{id}/clone — Clone to store.
     */
    public function clone(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'category_id' => 'nullable|uuid|exists:categories,id',
        ]);

        $product = $this->service->findProduct($id);
        $user = $request->user();

        $cloned = $this->service->cloneProduct(
            $product,
            $user->organization_id,
            $request->input('category_id'),
        );

        return $this->created(
            ['product_id' => $cloned->id, 'name' => $cloned->name],
            'Product cloned to your store successfully.',
        );
    }

    /**
     * POST /predefined-catalog/products/bulk-action — Bulk operations.
     */
    public function bulkAction(Request $request): JsonResponse
    {
        $request->validate([
            'product_ids' => 'required|array|min:1',
            'product_ids.*' => 'uuid',
            'action' => 'required|string|in:activate,deactivate,delete',
        ]);

        $ids = $request->input('product_ids');
        $action = $request->input('action');

        $affected = match ($action) {
            'activate' => $this->service->bulkToggleProducts($ids, true),
            'deactivate' => $this->service->bulkToggleProducts($ids, false),
            'delete' => $this->service->bulkDeleteProducts($ids),
        };

        return $this->success(
            ['affected' => $affected],
            "Bulk action '{$action}' applied to {$affected} product(s).",
        );
    }

    /**
     * POST /predefined-catalog/clone-all — Clone entire predefined catalog for the store's business type.
     */
    public function cloneAll(Request $request): JsonResponse
    {
        $businessTypeId = $this->resolveStoreBusinessTypeId($request);

        if (! $businessTypeId) {
            return $this->error('No business type configured for this store.', 422);
        }

        $user = $request->user();

        $result = $this->service->cloneAllForBusinessType(
            $businessTypeId,
            $user->organization_id,
        );

        return $this->created(
            $result,
            "Cloned {$result['categories_cloned']} categories and {$result['products_cloned']} products to your store.",
        );
    }
}
