<?php

namespace App\Domain\ZatcaCompliance\Jobs;

use App\Domain\ZatcaCompliance\Enums\ZatcaSubmissionStatus;
use App\Domain\ZatcaCompliance\Models\ZatcaInvoice;
use App\Domain\ZatcaCompliance\Services\CertificateService;
use App\Domain\ZatcaCompliance\Services\ZatcaApiClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SubmitReportingInvoiceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(public string $invoiceId) {}

    public function handle(ZatcaApiClient $api, CertificateService $certs): void
    {
        $invoice = ZatcaInvoice::find($this->invoiceId);
        if (! $invoice) {
            return;
        }

        $material = $certs->activeMaterial($invoice->store_id);
        $resp = $api->reportInvoice(
            $invoice->invoice_xml,
            $invoice->invoice_hash,
            (string) ($invoice->uuid ?? $invoice->id),
            $material['certificate']->certificate_pem,
        );

        $update = [
            'submission_attempts' => ((int) $invoice->submission_attempts) + 1,
            'last_attempt_at' => now(),
            'zatca_response_code' => $resp['response_code'] ?? null,
            'zatca_response_message' => $resp['message'] ?? null,
        ];

        if (! empty($resp['reported'])) {
            $update['submission_status'] = ZatcaSubmissionStatus::Reported;
            $update['submitted_at'] = now();
            $update['next_attempt_at'] = null;
            $update['rejection_errors'] = null;
        } else {
            $update['submission_status'] = ZatcaSubmissionStatus::Rejected;
            $update['rejection_errors'] = $resp['errors'] ?? [['message' => $resp['message'] ?? 'unknown']];
            $update['next_attempt_at'] = RetryFailedSubmissionJob::nextAttemptAt(((int) $invoice->submission_attempts) + 1);
            Log::warning('ZATCA reporting rejected', ['invoice_id' => $invoice->id, 'resp' => $resp]);
        }

        $invoice->update($update);
    }
}
