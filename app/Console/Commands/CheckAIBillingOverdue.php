<?php

namespace App\Console\Commands;

use App\Domain\WameedAI\Services\AIBillingService;
use Illuminate\Console\Command;

class CheckAIBillingOverdue extends Command
{
    protected $signature = 'ai-billing:check-overdue';

    protected $description = 'Check for overdue AI billing invoices and auto-disable stores that exceed the grace period';

    public function handle(AIBillingService $billingService): int
    {
        $this->info('Checking for overdue AI billing invoices...');

        $result = $billingService->checkAndDisableOverdueStores();

        $this->info("Overdue invoices found: {$result['overdue_invoices']}");
        $this->info("Stores disabled: {$result['disabled']}");

        if (!empty($result['stores'])) {
            $this->warn('Disabled store IDs: ' . implode(', ', $result['stores']));
        }

        return self::SUCCESS;
    }
}
