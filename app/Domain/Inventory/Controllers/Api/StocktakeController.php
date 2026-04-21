<?php

namespace App\Domain\Inventory\Controllers\Api;

use App\Domain\Inventory\Requests\CreateStocktakeRequest;
use App\Domain\Inventory\Requests\UpdateStocktakeCountsRequest;
use App\Domain\Inventory\Resources\StocktakeResource;
use App\Domain\Inventory\Services\StocktakeService;
use App\Http\Controllers\Api\BaseApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StocktakeController extends BaseApiController
{
    public function __construct(
        private readonly StocktakeService $stocktakeService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'store_id' => 'required|uuid',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $paginator = $this->stocktakeService->list(
            storeId: $request->input('store_id'),
            perPage: $request->integer('per_page', 25),
        );

        $data = $paginator->toArray();
        $data['data'] = StocktakeResource::collection($paginator->items())->resolve();

        return $this->success($data);
    }

    public function store(CreateStocktakeRequest $request): JsonResponse
    {
        $stocktake = $this->stocktakeService->create(
            $request->validated(),
            $request->user()->id,
        );

        return $this->created(new StocktakeResource($stocktake));
    }

    public function show(Request $request, string $stocktake): JsonResponse
    {
        $storeId = $this->resolvedStoreId($request) ?? $request->user()->store_id;
        $found = $this->stocktakeService->find($storeId, $stocktake);

        return $this->success(new StocktakeResource($found));
    }

    public function updateCounts(UpdateStocktakeCountsRequest $request, string $stocktake): JsonResponse
    {
        try {
            $updated = $this->stocktakeService->updateCounts(
                $stocktake,
                $request->validated()['items'],
                $request->user()->id,
            );

            return $this->success(new StocktakeResource($updated));
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    public function apply(Request $request, string $stocktake): JsonResponse
    {
        try {
            $applied = $this->stocktakeService->apply($stocktake, $request->user()->id);

            return $this->success(new StocktakeResource($applied), 'Stocktake applied successfully.');
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    public function cancel(string $stocktake): JsonResponse
    {
        try {
            $cancelled = $this->stocktakeService->cancel($stocktake);

            return $this->success(new StocktakeResource($cancelled), 'Stocktake cancelled.');
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }
    }
}
