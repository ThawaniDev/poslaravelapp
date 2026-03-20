<?php

namespace App\Domain\IndustryJewelry\Controllers\Api;

use App\Domain\IndustryJewelry\Requests\CreateBuybackTransactionRequest;
use App\Domain\IndustryJewelry\Requests\CreateDailyMetalRateRequest;
use App\Domain\IndustryJewelry\Requests\CreateJewelryProductDetailRequest;
use App\Domain\IndustryJewelry\Requests\UpdateJewelryProductDetailRequest;
use App\Domain\IndustryJewelry\Resources\BuybackTransactionResource;
use App\Domain\IndustryJewelry\Resources\DailyMetalRateResource;
use App\Domain\IndustryJewelry\Resources\JewelryProductDetailResource;
use App\Domain\IndustryJewelry\Services\JewelryService;
use App\Http\Controllers\Api\BaseApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class JewelryController extends BaseApiController
{
    public function __construct(private readonly JewelryService $service) {}

    public function listMetalRates(Request $request): JsonResponse
    {
        $storeId = $request->user()->store_id;
        $paginator = $this->service->listMetalRates($storeId, $request->only(['metal_type', 'per_page']));

        $data = $paginator->toArray();
        $data['data'] = DailyMetalRateResource::collection($paginator->items())->resolve();

        return $this->success($data, __('industry.metal_rates_retrieved'));
    }

    public function upsertMetalRate(CreateDailyMetalRateRequest $request): JsonResponse
    {
        $storeId = $request->user()->store_id;
        $rate = $this->service->upsertMetalRate($storeId, $request->validated());
        return $this->success(new DailyMetalRateResource($rate), __('industry.metal_rate_saved'));
    }

    public function listProductDetails(Request $request): JsonResponse
    {
        $paginator = $this->service->listProductDetails($request->only(['metal_type', 'product_id', 'per_page']));

        $data = $paginator->toArray();
        $data['data'] = JewelryProductDetailResource::collection($paginator->items())->resolve();

        return $this->success($data, __('industry.jewelry_details_retrieved'));
    }

    public function createProductDetail(CreateJewelryProductDetailRequest $request): JsonResponse
    {
        $detail = $this->service->createProductDetail($request->validated());
        return $this->created(new JewelryProductDetailResource($detail), __('industry.jewelry_detail_created'));
    }

    public function updateProductDetail(UpdateJewelryProductDetailRequest $request, string $id): JsonResponse
    {
        $detail = $this->service->updateProductDetail($id, $request->validated());
        return $this->success(new JewelryProductDetailResource($detail), __('industry.jewelry_detail_updated'));
    }

    public function listBuybacks(Request $request): JsonResponse
    {
        $storeId = $request->user()->store_id;
        $paginator = $this->service->listBuybacks($storeId, $request->only(['metal_type', 'customer_id', 'per_page']));

        $data = $paginator->toArray();
        $data['data'] = BuybackTransactionResource::collection($paginator->items())->resolve();

        return $this->success($data, __('industry.buybacks_retrieved'));
    }

    public function createBuyback(CreateBuybackTransactionRequest $request): JsonResponse
    {
        $storeId = $request->user()->store_id;
        $buyback = $this->service->createBuyback($storeId, $request->validated());
        return $this->created(new BuybackTransactionResource($buyback), __('industry.buyback_created'));
    }
}
