<?php

namespace App\Domain\ProviderPayment\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProviderPaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'invoice_id' => $this->invoice_id,
            'purpose' => $this->purpose?->value,
            'purpose_label' => $this->purpose_label ?? $this->purpose?->label(),
            'purpose_reference_id' => $this->purpose_reference_id,
            'amount' => $this->amount,
            'tax_amount' => $this->tax_amount,
            'total_amount' => $this->total_amount,
            'currency' => $this->currency,
            'gateway' => $this->gateway,
            'tran_ref' => $this->tran_ref,
            'tran_type' => $this->tran_type,
            'cart_id' => $this->cart_id,
            'status' => $this->status?->value,
            'status_label' => $this->status?->label(),
            'response_status' => $this->response_status,
            'response_code' => $this->response_code,
            'response_message' => $this->response_message,
            'card_type' => $this->card_type,
            'card_scheme' => $this->card_scheme,
            'payment_description' => $this->payment_description,
            'payment_method' => $this->payment_method,
            'confirmation_email_sent' => $this->confirmation_email_sent,
            'confirmation_email_sent_at' => $this->confirmation_email_sent_at?->toIso8601String(),
            'confirmation_email_error' => $this->confirmation_email_error,
            'invoice_generated' => $this->invoice_generated,
            'invoice_generated_at' => $this->invoice_generated_at?->toIso8601String(),
            'ipn_received' => $this->ipn_received,
            'ipn_received_at' => $this->ipn_received_at?->toIso8601String(),
            'refund_amount' => $this->refund_amount,
            'refund_tran_ref' => $this->refund_tran_ref,
            'refunded_at' => $this->refunded_at?->toIso8601String(),
            'refund_reason' => $this->refund_reason,
            'notes' => $this->notes,
            'invoice' => $this->whenLoaded('invoice', function () {
                return [
                    'id' => $this->invoice->id,
                    'invoice_number' => $this->invoice->invoice_number,
                    'amount' => $this->invoice->amount,
                    'tax' => $this->invoice->tax,
                    'total' => $this->invoice->total,
                    'status' => $this->invoice->status?->value,
                    'due_date' => $this->invoice->due_date?->toDateString(),
                    'paid_at' => $this->invoice->paid_at?->toIso8601String(),
                    'pdf_url' => $this->invoice->pdf_url,
                    'email_sent' => $this->invoice->email_sent ?? false,
                ];
            }),
            'email_logs' => $this->whenLoaded('emailLogs', function () {
                return $this->emailLogs->map(fn ($log) => [
                    'id' => $log->id,
                    'email_type' => $log->email_type?->value,
                    'recipient_email' => $log->recipient_email,
                    'subject' => $log->subject,
                    'status' => $log->status,
                    'error_message' => $log->error_message,
                    'created_at' => $log->created_at?->toIso8601String(),
                ]);
            }),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
