<?php

namespace App\Console\Commands;

use App\Domain\WameedAI\Services\AIBillingService;
use Illuminate\Console\Command;

class GenerateAIBillingInvoices extends Command
{
    protected $signature = 'ai-billing:generate-invoices
                            {--year= : Year to generate invoices for (defaults to previous month)}
                            {--month= : Month to generate invoices for (defaults to previous month)}';

    protected $description = 'Generate monthly AI billing invoices for all stores that used AI features';

    public function handle(AIBillingService $billingService): int
    {
        $year = $this->option('year') ? (int) $this->option('year') : null;
        $month = $this->option('month') ? (int) $this->option('month') : null;

        $periodLabel = $year && $month
            ? "{$year}-" . str_pad($month, 2, '0', STR_PAD_LEFT)
            : now()->subMonth()->format('Y-m');

        $this->info("Generating AI billing invoices for {$periodLabel}...");

        $result = $billingService->generateMonthlyInvoices($year, $month);

        $this->info("Generated: {$result['generated']} invoices");
        $this->info("Skipped: {$result['skipped']} stores (already invoiced or below minimum)");

        if ($result['generated'] > 0) {
            $this->table(
                ['Invoice #', 'Store', 'Billed Amount'],
                collect($result['invoices'])->map(fn ($inv) => [
                    $inv->invoice_number,
                    $inv->store_id,
                    '$' . number_format((float) $inv->billed_amount_usd, 3),
                ])->toArray(),
            );
        }

        return self::SUCCESS;
    }
}
