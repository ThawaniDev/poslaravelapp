<?php

namespace App\Domain\PredefinedCatalog\Controllers\Api;

use App\Domain\PredefinedCatalog\Requests\CreatePredefinedCategoryRequest;
use App\Domain\PredefinedCatalog\Requests\UpdatePredefinedCategoryRequest;
use App\Domain\PredefinedCatalog\Resources\PredefinedCategoryResource;
use App\Domain\PredefinedCatalog\Services\PredefinedCatalogService;
use App\Domain\PredefinedCatalog\Services\PredefinedImageUploadService;
use App\Http\Controllers\Api\BaseApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PredefinedCategoryController extends BaseApiController
{
    public function __construct(
        private readonly PredefinedCatalogService $service,
        private readonly PredefinedImageUploadService $imageService,
    ) {}

    /**
     * GET /predefined-catalog/categories — Paginated list (auto-scoped to store's business type).
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'search' => 'nullable|string|max:100',
            'is_active' => 'nullable|boolean',
            'parent_only' => 'nullable|boolean',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $businessTypeId = $this->resolveStoreBusinessTypeId($request);

        $filters = $request->only(['search', 'is_active', 'parent_only']);
        if ($businessTypeId) {
            $filters['business_type_id'] = $businessTypeId;
        }

        $paginator = $this->service->listCategories(
            filters: $filters,
            perPage: $request->integer('per_page', 25),
        );

        return $this->success([
            'data' => PredefinedCategoryResource::collection($paginator->items()),
            'total' => $paginator->total(),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
        ]);
    }

    /**
     * GET /predefined-catalog/categories/tree — Tree for the authenticated store's business type.
     */
    public function tree(Request $request): JsonResponse
    {
        $businessTypeId = $this->resolveStoreBusinessTypeId($request);

        if (! $businessTypeId) {
            return $this->success([]);
        }

        $categories = $this->service->categoryTree($businessTypeId);

        return $this->success(PredefinedCategoryResource::collection($categories));
    }

    /**
     * GET /predefined-catalog/categories/{id} — Show single.
     */
    public function show(string $id): JsonResponse
    {
        $category = $this->service->findCategory($id);
        return $this->success(new PredefinedCategoryResource($category));
    }

    /**
     * POST /predefined-catalog/categories — Create.
     */
    public function store(CreatePredefinedCategoryRequest $request): JsonResponse
    {
        $data = $request->validated();

        if ($request->hasFile('image')) {
            $data['image_url'] = $this->imageService->uploadCategoryImage($request->file('image'));
        }

        $category = $this->service->createCategory($data);
        return $this->created(new PredefinedCategoryResource($category));
    }

    /**
     * PUT /predefined-catalog/categories/{id} — Update.
     */
    public function update(UpdatePredefinedCategoryRequest $request, string $id): JsonResponse
    {
        $category = $this->service->findCategory($id);
        $data = $request->validated();

        if ($request->hasFile('image')) {
            if ($category->image_url) {
                $this->imageService->deleteCategoryImage($category->image_url);
            }
            $data['image_url'] = $this->imageService->uploadCategoryImage($request->file('image'));
        }

        $updated = $this->service->updateCategory($category, $data);
        return $this->success(new PredefinedCategoryResource($updated));
    }

    /**
     * DELETE /predefined-catalog/categories/{id} — Delete.
     */
    public function destroy(string $id): JsonResponse
    {
        $category = $this->service->findCategory($id);

        try {
            $this->service->deleteCategory($category);
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }

        return $this->success(null, 'Predefined category deleted successfully.');
    }

    /**
     * POST /predefined-catalog/categories/{id}/clone — Clone to store.
     */
    public function clone(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'include_products' => 'sometimes|boolean',
        ]);

        $category = $this->service->findCategory($id);
        $user = $request->user();

        $cloned = $this->service->cloneCategory(
            $category,
            $user->organization_id,
            $request->boolean('include_products', true),
        );

        return $this->created(
            ['category_id' => $cloned->id, 'name' => $cloned->name],
            'Category cloned to your store successfully.',
        );
    }
}
