<?php

namespace App\Domain\ZatcaCompliance\Jobs;

use App\Domain\ZatcaCompliance\Enums\ZatcaInvoiceFlow;
use App\Domain\ZatcaCompliance\Enums\ZatcaSubmissionStatus;
use App\Domain\ZatcaCompliance\Models\ZatcaInvoice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

/**
 * Walks invoices that previously failed and dispatches the appropriate
 * clearance/reporting job again, following the spec's escalating retry
 * cadence:
 *   attempt 1 → +30s   2 → +2min   3 → +10min   4 → +1h   5 → +6h
 *   attempt ≥ 6 → stop and flip to Warning so the dashboard surfaces it.
 */
class RetryFailedSubmissionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public const SCHEDULE_SECONDS = [30, 120, 600, 3600, 21600];

    public static function nextAttemptAt(int $attempt): Carbon
    {
        $idx = max(0, min($attempt - 1, count(self::SCHEDULE_SECONDS) - 1));
        return now()->addSeconds(self::SCHEDULE_SECONDS[$idx]);
    }

    public function handle(): int
    {
        $due = ZatcaInvoice::where('submission_status', ZatcaSubmissionStatus::Rejected->value)
            ->whereNotNull('next_attempt_at')
            ->where('next_attempt_at', '<=', now())
            ->limit(100)
            ->get();

        $dispatched = 0;
        foreach ($due as $invoice) {
            $attempts = (int) $invoice->submission_attempts;
            if ($attempts >= count(self::SCHEDULE_SECONDS) + 1) {
                $invoice->update([
                    'submission_status' => ZatcaSubmissionStatus::Warning,
                    'next_attempt_at' => null,
                ]);
                continue;
            }
            if ($invoice->flow === ZatcaInvoiceFlow::Clearance->value) {
                SubmitClearanceInvoiceJob::dispatch($invoice->id)->onQueue('zatca');
            } else {
                SubmitReportingInvoiceJob::dispatch($invoice->id)->onQueue('zatca');
            }
            $dispatched++;
        }
        return $dispatched;
    }
}
