<?php

namespace App\Console\Commands;

use App\Domain\PosTerminal\Services\HeldCartService;
use Illuminate\Console\Command;

class PurgeExpiredHeldCarts extends Command
{
    protected $signature = 'pos:purge-expired-held-carts';

    protected $description = 'Delete held carts older than the per-store held_cart_expiry_hours threshold (default 24h).';

    public function handle(HeldCartService $service): int
    {
        $deleted = $service->purgeExpired();
        $this->info("Purged {$deleted} expired held cart(s).");
        return self::SUCCESS;
    }
}
