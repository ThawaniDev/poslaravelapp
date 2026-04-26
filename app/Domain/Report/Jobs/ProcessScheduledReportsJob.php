<?php

namespace App\Domain\Report\Jobs;

use App\Domain\Report\Models\ScheduledReport;
use App\Domain\Report\Services\ReportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Processes all due scheduled reports.
 *
 * Runs on a schedule (e.g., every hour). For each active ScheduledReport
 * whose `next_run_at` is in the past, this job:
 *  1. Generates the report data via ReportService.
 *  2. Builds a simple text/HTML email with the report summary.
 *  3. Sends the email to configured recipients.
 *  4. Updates `last_run_at` and advances `next_run_at` for the next cycle.
 */
class ProcessScheduledReportsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;

    public function handle(ReportService $reportService): void
    {
        $due = ScheduledReport::where('is_active', true)
            ->where('next_run_at', '<=', now())
            ->get();

        if ($due->isEmpty()) {
            return;
        }

        Log::info("ProcessScheduledReportsJob: processing {$due->count()} due scheduled reports.");

        foreach ($due as $schedule) {
            try {
                $this->processOne($schedule, $reportService);
            } catch (\Throwable $e) {
                Log::error(
                    "ProcessScheduledReportsJob: failed to process scheduled report {$schedule->id}: {$e->getMessage()}",
                    ['exception' => $e],
                );
            }
        }
    }

    private function processOne(ScheduledReport $schedule, ReportService $reportService): void
    {
        $filters = $schedule->filters ?? [];

        // Default date range: last N days based on frequency
        if (empty($filters['date_from'])) {
            $days = match ($schedule->frequency) {
                'weekly' => 7,
                'monthly' => 30,
                default => 1,
            };
            $filters['date_from'] = now()->subDays($days)->toDateString();
            $filters['date_to'] = now()->subDay()->toDateString();
        }

        // Generate the report data
        $exportData = $reportService->exportReport(
            $schedule->store_id,
            $schedule->report_type,
            $filters,
            $schedule->format ?? 'pdf',
        );

        // Send email to all recipients
        $subject = "[Report] {$schedule->name} — " . now()->format('Y-m-d');
        $body = $this->buildEmailBody($schedule, $exportData, $filters);

        foreach ($schedule->recipients as $recipient) {
            try {
                Mail::raw($body, function ($message) use ($recipient, $subject) {
                    $message->to($recipient)->subject($subject);
                });
            } catch (\Throwable $e) {
                Log::warning(
                    "ProcessScheduledReportsJob: email delivery failed to {$recipient} for schedule {$schedule->id}: {$e->getMessage()}",
                );
            }
        }

        // Advance next_run_at
        $nextRun = match ($schedule->frequency) {
            'daily' => now()->addDay()->startOfDay()->addHours(2),
            'weekly' => now()->next('monday')->startOfDay()->addHours(2),
            'monthly' => now()->addMonth()->startOfMonth()->addHours(2),
            default => now()->addDay(),
        };

        $schedule->update([
            'last_run_at' => now(),
            'next_run_at' => $nextRun,
        ]);

        Log::info(
            "ProcessScheduledReportsJob: completed schedule {$schedule->id} ({$schedule->name}). Next run: {$nextRun->toIso8601String()}",
        );
    }

    private function buildEmailBody(ScheduledReport $schedule, array $exportData, array $filters): string
    {
        $period = ($filters['date_from'] ?? '') . ' to ' . ($filters['date_to'] ?? 'today');

        $lines = [
            "Scheduled Report: {$schedule->name}",
            "Report Type: {$schedule->report_type}",
            "Period: {$period}",
            "Generated at: " . now()->toDateTimeString(),
            '',
            'Report Summary',
            '==============',
        ];

        // Append a brief summary depending on report type
        $data = $exportData['data'] ?? [];

        if (isset($data['totals'])) {
            foreach ($data['totals'] as $key => $value) {
                $lines[] = ucfirst(str_replace('_', ' ', $key)) . ': ' . (is_numeric($value) ? number_format((float)$value, 2) : $value);
            }
        } elseif (is_array($data)) {
            $count = is_array($data) && isset($data[0]) ? count($data) : (isset($data['sessions']) ? count($data['sessions']) : 0);
            $lines[] = "Records returned: {$count}";
        }

        $lines[] = '';
        $lines[] = 'This report was automatically generated by Wameed POS.';
        $lines[] = 'To manage your scheduled reports, log in to the Wameed POS app.';

        return implode("\n", $lines);
    }
}
