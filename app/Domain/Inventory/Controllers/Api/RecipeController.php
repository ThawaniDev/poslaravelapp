<?php

namespace App\Domain\Inventory\Controllers\Api;

use App\Domain\Inventory\Requests\CreateRecipeRequest;
use App\Domain\Inventory\Requests\UpdateRecipeRequest;
use App\Domain\Inventory\Resources\RecipeResource;
use App\Domain\Inventory\Services\RecipeService;
use App\Http\Controllers\Api\BaseApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RecipeController extends BaseApiController
{
    public function __construct(
        private readonly RecipeService $recipeService,
    ) {}

    /**
     * GET /api/v2/inventory/recipes
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $paginator = $this->recipeService->list(
            organizationId: $request->user()->organization_id,
            perPage: $request->integer('per_page', 25),
        );

        $data = $paginator->toArray();
        $data['data'] = RecipeResource::collection($paginator->items())->resolve();

        return $this->success($data);
    }

    /**
     * POST /api/v2/inventory/recipes
     */
    public function store(CreateRecipeRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $recipe = $this->recipeService->create(
            array_merge($validated, ['organization_id' => $request->user()->organization_id]),
            $validated['ingredients'],
        );

        return $this->created(new RecipeResource($recipe));
    }

    /**
     * GET /api/v2/inventory/recipes/{id}
     */
    public function show(string $recipe): JsonResponse
    {
        $found = $this->recipeService->find($recipe);

        return $this->success(new RecipeResource($found));
    }

    /**
     * PUT /api/v2/inventory/recipes/{id}
     */
    public function update(UpdateRecipeRequest $request, string $recipe): JsonResponse
    {
        $validated = $request->validated();

        $updated = $this->recipeService->update(
            $recipe,
            $validated,
            $validated['ingredients'] ?? null,
        );

        return $this->success(new RecipeResource($updated));
    }

    /**
     * DELETE /api/v2/inventory/recipes/{id}
     */
    public function destroy(string $recipe): JsonResponse
    {
        try {
            $this->recipeService->delete($recipe);

            return $this->success(null, 'Recipe deleted successfully.');
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }
    }
}
