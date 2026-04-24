<?php

namespace App\Domain\Customer\Services;

use App\Domain\Auth\Models\User;
use App\Domain\Customer\Enums\DigitalReceiptChannel;
use App\Domain\Customer\Enums\DigitalReceiptStatus;
use App\Domain\Customer\Models\Customer;
use App\Domain\Customer\Models\DigitalReceiptLog;
use App\Domain\Order\Models\Order;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class DigitalReceiptService
{
    /**
     * Dispatch a digital receipt for an order to the customer via the given channel.
     *
     * The transport itself (SMTP, WhatsApp Business API, Twilio, ...) is wired by the
     * platform/integrator. This service is responsible for:
     *  - validating that the customer has a valid destination
     *  - persisting an audit log row (rule #7)
     *  - dispatching the queued mail/notification job and updating the log status
     */
    public function send(Order $order, Customer $customer, DigitalReceiptChannel $channel, ?string $overrideDestination = null, ?User $actor = null): DigitalReceiptLog
    {
        $destination = $overrideDestination
            ?? ($channel === DigitalReceiptChannel::Email ? $customer->email : $customer->phone);

        if (empty($destination)) {
            throw new \RuntimeException(__('customers.receipt_no_destination'));
        }

        $log = DigitalReceiptLog::create([
            'order_id' => $order->id,
            'customer_id' => $customer->id,
            'channel' => $channel->value,
            'destination' => $destination,
            'status' => DigitalReceiptStatus::Pending->value,
            'sent_at' => now(),
        ]);

        try {
            $this->dispatch($order, $customer, $channel, $destination);
            $log->status = DigitalReceiptStatus::Sent->value;
            $log->save();
        } catch (\Throwable $e) {
            Log::warning('Digital receipt dispatch failed', [
                'order_id' => $order->id,
                'channel' => $channel->value,
                'error' => $e->getMessage(),
            ]);
            $log->status = DigitalReceiptStatus::Failed->value;
            $log->save();
        }

        return $log;
    }

    /**
     * Dispatch the actual message. Implemented by side-effect: sends mail when channel
     * is email; for WhatsApp this is a no-op stub that integrators wire up to a queue
     * (Twilio / WhatsApp Business API). The Flutter client always falls back to
     * `wa.me` deep-linking so the cashier can manually share if the platform has not
     * configured an automated channel.
     */
    protected function dispatch(Order $order, Customer $customer, DigitalReceiptChannel $channel, string $destination): void
    {
        if ($channel === DigitalReceiptChannel::Email) {
            // Only send through SMTP when a `from` address is actually configured.
            if (! config('mail.from.address')) {
                return;
            }
            try {
                Mail::raw(
                    sprintf("Receipt for order %s\nTotal: %s", $order->order_number ?? $order->id, $order->total),
                    function ($message) use ($destination, $customer) {
                        $message->to($destination, $customer->name)
                            ->subject('Your receipt');
                    }
                );
            } catch (\Throwable $e) {
                // Re-throw so caller can mark the log as failed.
                throw $e;
            }
        }
        // For WhatsApp + SMS we simply persist the log; the desktop client falls back
        // to a deep-link if the integrator has not wired up an automated transport.
    }

    /**
     * Receipts that have been sent for the given order, newest first.
     */
    public function logForOrder(string $orderId): \Illuminate\Database\Eloquent\Collection
    {
        return DigitalReceiptLog::where('order_id', $orderId)->orderByDesc('sent_at')->get();
    }
}
