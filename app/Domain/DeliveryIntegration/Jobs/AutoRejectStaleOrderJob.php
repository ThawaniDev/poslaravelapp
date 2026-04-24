<?php

namespace App\Domain\DeliveryIntegration\Jobs;

use App\Domain\DeliveryIntegration\Enums\DeliveryOrderStatus;
use App\Domain\DeliveryIntegration\Models\DeliveryOrderMapping;
use App\Domain\DeliveryIntegration\Services\StatusPushService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Spec Rule #6: When auto-accept is disabled, manual-accept orders must be
 * accepted within the platform's timeout (default 5 minutes). If still
 * pending at the deadline, mark as rejected and push the rejection to
 * the platform so the customer can be re-routed.
 *
 * Dispatched with `delay()` from OrderIngestService when the config has
 * auto_accept = false.
 */
class AutoRejectStaleOrderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(public string $orderMappingId)
    {
        $this->onQueue('delivery');
    }

    public function handle(StatusPushService $pushService): void
    {
        $order = DeliveryOrderMapping::find($this->orderMappingId);

        if (! $order) {
            return;
        }

        $currentStatus = $order->delivery_status instanceof DeliveryOrderStatus
            ? $order->delivery_status->value
            : (string) $order->delivery_status;

        // Already actioned (accepted, preparing, ready, …) — nothing to do.
        if ($currentStatus !== DeliveryOrderStatus::Pending->value) {
            return;
        }

        $order->update([
            'delivery_status' => DeliveryOrderStatus::Rejected->value,
            'rejection_reason' => 'auto_rejected_timeout',
        ]);

        try {
            $pushService->pushStatus($order, DeliveryOrderStatus::Rejected->value, [
                'reason' => 'auto_rejected_timeout',
            ]);
        } catch (\Throwable $e) {
            Log::warning('delivery.auto_reject.push_failed', [
                'order_mapping_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
            // Swallow — the mapping is already marked rejected locally.
        }
    }
}
