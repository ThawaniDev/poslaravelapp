<?php

namespace Database\Seeders;

use App\Domain\ContentOnboarding\Enums\MarketplaceListingStatus;
use App\Domain\ContentOnboarding\Models\TemplateMarketplaceListing;
use App\Domain\ContentOnboarding\Services\MarketplaceService;
use App\Domain\Core\Models\Store;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;

class ComprehensiveMarketplacePurchaseSeeder extends Seeder
{
    public function run(): void
    {
        // Find or expect a store to use as buyer
        $store = Store::first();
        if (! $store) {
            Log::warning('ComprehensiveMarketplacePurchaseSeeder: No store found – skipping purchase seed.');

            return;
        }

        // Pick the first approved paid listing (Restaurant Table Manager Pro at 49.99 SAR)
        $listing = TemplateMarketplaceListing::where('status', MarketplaceListingStatus::Approved)
            ->where('price_amount', '>', 0)
            ->orderBy('price_amount')
            ->first();

        if (! $listing) {
            Log::warning('ComprehensiveMarketplacePurchaseSeeder: No approved paid listing found – skipping.');

            return;
        }

        $service = app(MarketplaceService::class);

        $purchase = $service->purchaseTemplate(
            storeId: $store->id,
            listingId: $listing->id,
            paymentData: [
                'payment_reference' => 'SEED-' . strtoupper(substr(md5(now()->toISOString()), 0, 10)),
                'payment_gateway'   => 'thawani_gateway',
                'auto_renew'        => true,
            ],
        );

        if ($purchase) {
            Log::info("ComprehensiveMarketplacePurchaseSeeder: Purchase #{$purchase->id} created for listing '{$listing->title}', invoice #{$purchase->invoice_id}");
        } else {
            Log::warning("ComprehensiveMarketplacePurchaseSeeder: purchaseTemplate returned null – listing may already be purchased or invalid.");
        }
    }
}
