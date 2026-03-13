<?php

namespace App\Domain\Catalog\Controllers\Api;

use App\Domain\Catalog\Requests\CreateCategoryRequest;
use App\Domain\Catalog\Requests\UpdateCategoryRequest;
use App\Domain\Catalog\Resources\CategoryResource;
use App\Domain\Catalog\Services\CategoryService;
use App\Http\Controllers\Api\BaseApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryController extends BaseApiController
{
    public function __construct(
        private readonly CategoryService $categoryService,
    ) {}

    public function tree(Request $request): JsonResponse
    {
        $categories = $this->categoryService->tree(
            $request->user()->organization_id,
            $request->boolean('active_only', false),
        );

        return $this->success(CategoryResource::collection($categories));
    }

    public function store(CreateCategoryRequest $request): JsonResponse
    {
        $category = $this->categoryService->create(
            $request->validated(),
            $request->user(),
        );

        return $this->created(new CategoryResource($category));
    }

    public function show(string $category): JsonResponse
    {
        $found = $this->categoryService->find($category);

        return $this->success(new CategoryResource($found));
    }

    public function update(UpdateCategoryRequest $request, string $category): JsonResponse
    {
        $found = $this->categoryService->find($category);

        if ($found->organization_id !== $request->user()->organization_id) {
            return $this->notFound('Category not found.');
        }

        $updated = $this->categoryService->update($found, $request->validated());

        return $this->success(new CategoryResource($updated));
    }

    public function destroy(Request $request, string $category): JsonResponse
    {
        $found = $this->categoryService->find($category);

        if ($found->organization_id !== $request->user()->organization_id) {
            return $this->notFound('Category not found.');
        }

        try {
            $this->categoryService->delete($found);
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }

        return $this->success(null, 'Category deleted successfully.');
    }
}
