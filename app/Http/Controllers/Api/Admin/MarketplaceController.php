<?php

namespace App\Http\Controllers\Api\Admin;

use App\Domain\ThawaniIntegration\Models\ThawaniStoreConfig;
use App\Domain\ThawaniIntegration\Models\ThawaniProductMapping;
use App\Domain\ThawaniIntegration\Models\ThawaniOrderMapping;
use App\Domain\ThawaniIntegration\Models\ThawaniSettlement;
use App\Domain\Core\Models\Store;
use App\Http\Controllers\Api\BaseApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class MarketplaceController extends BaseApiController
{
    // ═══════════════════════════════════════════════════════════════
    //  STORE LISTINGS
    // ═══════════════════════════════════════════════════════════════

    public function listStores(Request $request): JsonResponse
    {
        $query = ThawaniStoreConfig::query()->with('store');

        if ($request->filled('is_connected')) {
            $query->where('is_connected', filter_var($request->is_connected, FILTER_VALIDATE_BOOLEAN));
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('store', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('name_ar', 'like', "%{$search}%");
            });
        }

        $stores = $query->orderBy('created_at', 'desc')
            ->paginate($request->integer('per_page', 15));

        return $this->success($stores, 'Marketplace stores retrieved');
    }

    public function showStore(string $id): JsonResponse
    {
        $config = ThawaniStoreConfig::with('store')->find($id);
        if (! $config) {
            return $this->notFound('Store config not found');
        }

        return $this->success($config, 'Store config retrieved');
    }

    public function updateStoreConfig(Request $request, string $id): JsonResponse
    {
        $config = ThawaniStoreConfig::find($id);
        if (! $config) {
            return $this->notFound('Store config not found');
        }

        $request->validate([
            'auto_sync_products'  => 'sometimes|boolean',
            'auto_sync_inventory' => 'sometimes|boolean',
            'auto_accept_orders'  => 'sometimes|boolean',
            'commission_rate'     => 'sometimes|numeric|min:0|max:100',
            'operating_hours_json' => 'sometimes|array',
        ]);

        $config->update($request->only([
            'auto_sync_products', 'auto_sync_inventory',
            'auto_accept_orders', 'commission_rate', 'operating_hours_json',
        ]));

        return $this->success($config->fresh('store'), 'Store config updated');
    }

    public function connectStore(Request $request, string $storeId): JsonResponse
    {
        $store = Store::find($storeId);
        if (! $store) {
            return $this->notFound('Store not found');
        }

        $existing = ThawaniStoreConfig::where('store_id', $storeId)->first();
        if ($existing && $existing->is_connected) {
            return $this->error('Store is already connected', 422);
        }

        $config = $existing ?? new ThawaniStoreConfig();
        $config->forceFill([
            'id'                   => $existing ? $existing->id : Str::uuid()->toString(),
            'store_id'             => $storeId,
            'thawani_store_id'     => 'TH-' . strtoupper(Str::random(8)),
            'is_connected'         => true,
            'auto_sync_products'   => $request->boolean('auto_sync_products', true),
            'auto_sync_inventory'  => $request->boolean('auto_sync_inventory', true),
            'auto_accept_orders'   => $request->boolean('auto_accept_orders', false),
            'commission_rate'      => $request->input('commission_rate', 5.00),
            'connected_at'         => now(),
        ]);
        $config->save();

        return $this->created($config->load('store'), 'Store connected to marketplace');
    }

    public function disconnectStore(string $id): JsonResponse
    {
        $config = ThawaniStoreConfig::find($id);
        if (! $config) {
            return $this->notFound('Store config not found');
        }

        $config->update([
            'is_connected'  => false,
        ]);

        return $this->success($config->fresh('store'), 'Store disconnected from marketplace');
    }

    // ═══════════════════════════════════════════════════════════════
    //  PRODUCT LISTINGS
    // ═══════════════════════════════════════════════════════════════

    public function listProducts(Request $request): JsonResponse
    {
        $query = ThawaniProductMapping::query()->with(['store', 'product']);

        if ($request->filled('store_id')) {
            $query->where('store_id', $request->store_id);
        }
        if ($request->filled('is_published')) {
            $query->where('is_published', filter_var($request->is_published, FILTER_VALIDATE_BOOLEAN));
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('product', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('sku', 'like', "%{$search}%");
            });
        }

        $products = $query->orderBy('display_order')
            ->paginate($request->integer('per_page', 15));

        return $this->success($products, 'Marketplace products retrieved');
    }

    public function showProduct(string $id): JsonResponse
    {
        $mapping = ThawaniProductMapping::with(['store', 'product'])->find($id);
        if (! $mapping) {
            return $this->notFound('Product mapping not found');
        }

        return $this->success($mapping, 'Product mapping retrieved');
    }

    public function updateProduct(Request $request, string $id): JsonResponse
    {
        $mapping = ThawaniProductMapping::find($id);
        if (! $mapping) {
            return $this->notFound('Product mapping not found');
        }

        $request->validate([
            'is_published'  => 'sometimes|boolean',
            'online_price'  => 'sometimes|numeric|min:0',
            'display_order' => 'sometimes|integer|min:0',
        ]);

        $mapping->update($request->only(['is_published', 'online_price', 'display_order']));

        return $this->success($mapping->fresh(['store', 'product']), 'Product listing updated');
    }

    public function bulkPublish(Request $request): JsonResponse
    {
        $request->validate([
            'product_ids'  => 'required|array|min:1',
            'product_ids.*' => 'string',
            'is_published' => 'required|boolean',
        ]);

        $count = ThawaniProductMapping::whereIn('id', $request->product_ids)
            ->update(['is_published' => $request->is_published]);

        return $this->success(
            ['updated_count' => $count],
            $request->is_published ? 'Products published' : 'Products unpublished',
        );
    }

    // ═══════════════════════════════════════════════════════════════
    //  ORDERS
    // ═══════════════════════════════════════════════════════════════

    public function listOrders(Request $request): JsonResponse
    {
        $query = ThawaniOrderMapping::query()->with('store');

        if ($request->filled('store_id'))  $query->where('store_id', $request->store_id);
        if ($request->filled('status'))    $query->where('status', $request->status);
        if ($request->filled('delivery_type')) $query->where('delivery_type', $request->delivery_type);
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('thawani_order_number', 'like', "%{$search}%")
                  ->orWhere('customer_name', 'like', "%{$search}%")
                  ->orWhere('customer_phone', 'like', "%{$search}%");
            });
        }

        $orders = $query->orderBy('created_at', 'desc')
            ->paginate($request->integer('per_page', 15));

        return $this->success($orders, 'Marketplace orders retrieved');
    }

    public function showOrder(string $id): JsonResponse
    {
        $order = ThawaniOrderMapping::with('store')->find($id);
        if (! $order) {
            return $this->notFound('Order not found');
        }

        return $this->success($order, 'Marketplace order retrieved');
    }

    // ═══════════════════════════════════════════════════════════════
    //  SETTLEMENTS
    // ═══════════════════════════════════════════════════════════════

    public function listSettlements(Request $request): JsonResponse
    {
        $query = ThawaniSettlement::query()->with('store');

        if ($request->filled('store_id')) $query->where('store_id', $request->store_id);
        if ($request->filled('date_from')) $query->where('settlement_date', '>=', $request->date_from);
        if ($request->filled('date_to')) $query->where('settlement_date', '<=', $request->date_to);

        $settlements = $query->orderBy('settlement_date', 'desc')
            ->paginate($request->integer('per_page', 15));

        return $this->success($settlements, 'Settlements retrieved');
    }

    public function showSettlement(string $id): JsonResponse
    {
        $settlement = ThawaniSettlement::with('store')->find($id);
        if (! $settlement) {
            return $this->notFound('Settlement not found');
        }

        return $this->success($settlement, 'Settlement retrieved');
    }

    public function settlementSummary(Request $request): JsonResponse
    {
        $query = ThawaniSettlement::query();

        if ($request->filled('store_id'))  $query->where('store_id', $request->store_id);
        if ($request->filled('date_from')) $query->where('settlement_date', '>=', $request->date_from);
        if ($request->filled('date_to'))   $query->where('settlement_date', '<=', $request->date_to);

        $summary = [
            'total_gross'       => round((float) $query->clone()->sum('gross_amount'), 3),
            'total_commission'  => round((float) $query->clone()->sum('commission_amount'), 3),
            'total_net'         => round((float) $query->clone()->sum('net_amount'), 3),
            'total_orders'      => (int) $query->clone()->sum('order_count'),
            'settlement_count'  => $query->clone()->count(),
        ];

        return $this->success($summary, 'Settlement summary retrieved');
    }
}
