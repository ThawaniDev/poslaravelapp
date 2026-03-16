<?php

namespace App\Domain\IndustryJewelry\Controllers\Api;

use App\Domain\IndustryJewelry\Services\JewelryService;
use App\Http\Controllers\Api\BaseApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class JewelryController extends BaseApiController
{
    public function __construct(private JewelryService $service) {}

    public function listMetalRates(Request $request): JsonResponse
    {
        $storeId = $request->user()->store_id;
        $data = $this->service->listMetalRates($storeId, $request->only(['metal_type', 'per_page']));
        return $this->success($data, __('industry.metal_rates_retrieved'));
    }

    public function upsertMetalRate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'metal_type' => 'required|string|in:gold,silver,platinum',
            'karat' => 'required|string|max:10',
            'rate_per_gram' => 'required|numeric|min:0',
            'buyback_rate_per_gram' => 'nullable|numeric|min:0',
            'effective_date' => 'required|date',
        ]);

        $storeId = $request->user()->store_id;
        $rate = $this->service->upsertMetalRate($storeId, $validated);
        return $this->success($rate, __('industry.metal_rate_saved'));
    }

    public function listProductDetails(Request $request): JsonResponse
    {
        $data = $this->service->listProductDetails($request->only(['metal_type', 'product_id', 'per_page']));
        return $this->success($data, __('industry.jewelry_details_retrieved'));
    }

    public function createProductDetail(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => 'required|string',
            'metal_type' => 'required|string|in:gold,silver,platinum',
            'karat' => 'required|string|max:10',
            'gross_weight_g' => 'required|numeric|min:0',
            'net_weight_g' => 'required|numeric|min:0',
            'making_charges_type' => 'required|string|in:flat,percentage,per_gram',
            'making_charges_value' => 'required|numeric|min:0',
            'stone_type' => 'nullable|string|max:100',
            'stone_weight_carat' => 'nullable|numeric|min:0',
            'stone_count' => 'nullable|integer|min:0',
            'certificate_number' => 'nullable|string|max:100',
            'certificate_url' => 'nullable|string|max:500',
        ]);

        $detail = $this->service->createProductDetail($validated);
        return $this->created($detail, __('industry.jewelry_detail_created'));
    }

    public function updateProductDetail(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'gross_weight_g' => 'nullable|numeric|min:0',
            'net_weight_g' => 'nullable|numeric|min:0',
            'making_charges_type' => 'nullable|string|in:flat,percentage,per_gram',
            'making_charges_value' => 'nullable|numeric|min:0',
            'stone_type' => 'nullable|string|max:100',
            'stone_weight_carat' => 'nullable|numeric|min:0',
            'stone_count' => 'nullable|integer|min:0',
            'certificate_number' => 'nullable|string|max:100',
            'certificate_url' => 'nullable|string|max:500',
        ]);

        $detail = $this->service->updateProductDetail($id, $validated);
        return $this->success($detail, __('industry.jewelry_detail_updated'));
    }

    public function listBuybacks(Request $request): JsonResponse
    {
        $storeId = $request->user()->store_id;
        $data = $this->service->listBuybacks($storeId, $request->only(['metal_type', 'customer_id', 'per_page']));
        return $this->success($data, __('industry.buybacks_retrieved'));
    }

    public function createBuyback(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'customer_id' => 'required|string',
            'metal_type' => 'required|string|in:gold,silver,platinum',
            'karat' => 'required|string|max:10',
            'weight_g' => 'required|numeric|min:0',
            'rate_per_gram' => 'required|numeric|min:0',
            'total_amount' => 'required|numeric|min:0',
            'payment_method' => 'required|string|in:cash,bank_transfer,credit_note',
            'staff_user_id' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        $storeId = $request->user()->store_id;
        $buyback = $this->service->createBuyback($storeId, $validated);
        return $this->created($buyback, __('industry.buyback_created'));
    }
}
