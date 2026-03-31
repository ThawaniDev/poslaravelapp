<?php

namespace App\Domain\Inventory\Controllers\Api;

use App\Domain\Inventory\Requests\CreateWasteRecordRequest;
use App\Domain\Inventory\Resources\WasteRecordResource;
use App\Domain\Inventory\Services\WasteService;
use App\Http\Controllers\Api\BaseApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WasteController extends BaseApiController
{
    public function __construct(
        private readonly WasteService $wasteService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'store_id' => 'required|uuid',
            'product_id' => 'nullable|uuid',
            'reason' => 'nullable|string',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $paginator = $this->wasteService->list(
            storeId: $request->input('store_id'),
            filters: $request->only(['product_id', 'reason', 'date_from', 'date_to']),
            perPage: $request->integer('per_page', 25),
        );

        $data = $paginator->toArray();
        $data['data'] = WasteRecordResource::collection($paginator->items())->resolve();

        return $this->success($data);
    }

    public function store(CreateWasteRecordRequest $request): JsonResponse
    {
        $waste = $this->wasteService->create(
            $request->validated(),
            $request->user()->id,
        );

        return $this->created(new WasteRecordResource($waste));
    }
}
