<?php

namespace App\Domain\DeliveryIntegration\Services;

use App\Domain\DeliveryIntegration\DTOs\IngestOrderDTO;
use App\Domain\DeliveryIntegration\Enums\DeliveryOrderStatus;
use App\Domain\DeliveryIntegration\Events\DeliveryOrderReceived;
use App\Domain\DeliveryIntegration\Models\DeliveryOrderMapping;
use App\Domain\DeliveryIntegration\Models\DeliveryPlatformConfig;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderIngestService
{
    public function ingest(IngestOrderDTO $dto): ?DeliveryOrderMapping
    {
        $config = DeliveryPlatformConfig::where('store_id', $dto->storeId)
            ->where('platform', $dto->platform)
            ->enabled()
            ->first();

        if (! $config) {
            Log::warning('Order ingest: no active config', [
                'store_id' => $dto->storeId,
                'platform' => $dto->platform,
            ]);

            return null;
        }

        if ($config->isDailyLimitReached()) {
            Log::warning('Order ingest: daily limit reached', [
                'store_id' => $dto->storeId,
                'platform' => $dto->platform,
                'daily_count' => $config->daily_order_count,
                'max' => $config->max_daily_orders,
            ]);

            return null;
        }

        $existing = DeliveryOrderMapping::where('platform', $dto->platform)
            ->where('external_order_id', $dto->externalOrderId)
            ->first();

        if ($existing) {
            Log::info('Order ingest: duplicate order', [
                'external_order_id' => $dto->externalOrderId,
                'platform' => $dto->platform,
            ]);

            return $existing;
        }

        $order = DB::transaction(function () use ($dto, $config) {
            $order = DeliveryOrderMapping::create([
                'store_id' => $dto->storeId,
                'platform' => $dto->platform,
                'external_order_id' => $dto->externalOrderId,
                'order_id' => null,
                'delivery_status' => $config->auto_accept
                    ? DeliveryOrderStatus::Accepted->value
                    : DeliveryOrderStatus::Pending->value,
                'customer_name' => $dto->customerName,
                'customer_phone' => $dto->customerPhone,
                'delivery_address' => $dto->deliveryAddress,
                'delivery_fee' => $dto->deliveryFee,
                'subtotal' => $dto->subtotal,
                'total_amount' => $dto->totalAmount,
                'items_count' => count($dto->items),
                'estimated_prep_minutes' => $dto->estimatedPrepMinutes,
                'notes' => $dto->notes,
                'raw_payload' => $dto->rawPayload,
                'accepted_at' => $config->auto_accept ? now() : null,
            ]);

            $config->incrementDailyOrderCount();

            return $order;
        });

        event(new DeliveryOrderReceived($order));

        return $order;
    }

    public function ingestFromWebhook(string $storeId, string $platform, array $rawPayload): ?DeliveryOrderMapping
    {
        $adapter = DeliveryAdapterFactory::makeFromSlug($platform, []);
        $normalized = $adapter->normalizeOrderPayload($rawPayload);

        $dto = IngestOrderDTO::fromWebhookPayload(
            storeId: $storeId,
            platform: $platform,
            payload: $normalized,
        );

        return $this->ingest($dto);
    }
}
