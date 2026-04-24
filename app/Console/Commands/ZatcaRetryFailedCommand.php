<?php

namespace App\Console\Commands;

use App\Domain\ZatcaCompliance\Jobs\RetryFailedSubmissionJob;
use Illuminate\Console\Command;

class ZatcaRetryFailedCommand extends Command
{
    protected $signature = 'zatca:retry-failed';
    protected $description = 'Re-dispatch ZATCA submissions whose retry window has elapsed.';

    public function handle(): int
    {
        $count = (new RetryFailedSubmissionJob)->handle();
        $this->info("Dispatched {$count} ZATCA invoice retries.");
        return self::SUCCESS;
    }
}
