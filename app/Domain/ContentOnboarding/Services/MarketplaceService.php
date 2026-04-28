<?php

namespace App\Domain\ContentOnboarding\Services;

use App\Domain\ContentOnboarding\Enums\MarketplaceListingStatus;
use App\Domain\ContentOnboarding\Enums\MarketplacePricingType;
use App\Domain\ContentOnboarding\Enums\PurchaseType;
use App\Domain\ContentOnboarding\Models\MarketplaceCategory;
use App\Domain\ContentOnboarding\Models\MarketplacePurchaseInvoice;
use App\Domain\ContentOnboarding\Models\TemplateMarketplaceListing;
use App\Domain\ContentOnboarding\Models\TemplatePurchase;
use App\Domain\ContentOnboarding\Models\TemplateReview;
use App\Domain\Core\Models\Store;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MarketplaceService
{
    // ─── Browse Listings ─────────────────────────────────

    public function browseListings(array $filters = []): Collection
    {
        $query = TemplateMarketplaceListing::where('status', MarketplaceListingStatus::Approved)
            ->with(['category', 'bundledTheme']);

        if (! empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        if (! empty($filters['pricing_type'])) {
            $query->where('pricing_type', $filters['pricing_type']);
        }

        if (! empty($filters['is_featured'])) {
            $query->where('is_featured', true);
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $like = DB::getDriverName() === 'pgsql' ? 'ilike' : 'like';
            $query->where(function ($q) use ($search, $like) {
                $q->where('title', $like, "%{$search}%")
                    ->orWhere('title_ar', $like, "%{$search}%")
                    ->orWhere('description', $like, "%{$search}%");
            });
        }

        if (! empty($filters['min_rating'])) {
            $query->where('average_rating', '>=', $filters['min_rating']);
        }

        $sortBy = $filters['sort_by'] ?? 'published_at';
        $sortDir = $filters['sort_dir'] ?? 'desc';
        $allowedSorts = ['published_at', 'average_rating', 'download_count', 'price_amount', 'title'];

        if (in_array($sortBy, $allowedSorts, true)) {
            $query->orderBy($sortBy, $sortDir === 'asc' ? 'asc' : 'desc');
        }

        return $query->get();
    }

    public function getListing(string $listingId): ?TemplateMarketplaceListing
    {
        return TemplateMarketplaceListing::with(['category', 'bundledTheme', 'layoutTemplate'])
            ->find($listingId);
    }

    public function getListingByTemplate(string $templateId): ?TemplateMarketplaceListing
    {
        return TemplateMarketplaceListing::where('pos_layout_template_id', $templateId)
            ->with(['category', 'bundledTheme'])
            ->first();
    }

    // ─── Categories ──────────────────────────────────────

    public function getCategories(): Collection
    {
        return MarketplaceCategory::where('is_active', true)
            ->whereNull('parent_id')
            ->with('children')
            ->orderBy('sort_order')
            ->get();
    }

    public function getCategory(string $categoryId): ?MarketplaceCategory
    {
        return MarketplaceCategory::with(['children', 'listings'])->find($categoryId);
    }

    // ─── Listing Management ──────────────────────────────

    public function createListing(array $data): TemplateMarketplaceListing
    {
        $data['status'] = MarketplaceListingStatus::Draft;

        return TemplateMarketplaceListing::create($data);
    }

    public function updateListing(string $listingId, array $data): ?TemplateMarketplaceListing
    {
        $listing = TemplateMarketplaceListing::find($listingId);
        if (! $listing) {
            return null;
        }

        // Prevent updating pricing_type if there are active purchases
        if (isset($data['pricing_type']) && $data['pricing_type'] !== $listing->pricing_type?->value) {
            $activePurchases = TemplatePurchase::where('marketplace_listing_id', $listingId)
                ->where('is_active', true)
                ->exists();

            if ($activePurchases) {
                unset($data['pricing_type']);
            }
        }

        $listing->update($data);

        return $listing->fresh();
    }

    // ─── Approval Workflow ───────────────────────────────

    public function submitForReview(string $listingId): ?TemplateMarketplaceListing
    {
        $listing = TemplateMarketplaceListing::find($listingId);
        if (! $listing || $listing->status !== MarketplaceListingStatus::Draft) {
            return null;
        }

        $listing->update(['status' => MarketplaceListingStatus::PendingReview]);

        return $listing->fresh();
    }

    public function approveListing(string $listingId, string $approvedBy): ?TemplateMarketplaceListing
    {
        $listing = TemplateMarketplaceListing::find($listingId);
        if (! $listing || $listing->status !== MarketplaceListingStatus::PendingReview) {
            return null;
        }

        $listing->update([
            'status' => MarketplaceListingStatus::Approved,
            'approved_by' => $approvedBy,
            'approved_at' => now(),
            'published_at' => now(),
        ]);

        return $listing->fresh();
    }

    public function rejectListing(string $listingId, string $reason): ?TemplateMarketplaceListing
    {
        $listing = TemplateMarketplaceListing::find($listingId);
        if (! $listing || $listing->status !== MarketplaceListingStatus::PendingReview) {
            return null;
        }

        $listing->update([
            'status' => MarketplaceListingStatus::Rejected,
            'rejection_reason' => $reason,
        ]);

        return $listing->fresh();
    }

    public function suspendListing(string $listingId): ?TemplateMarketplaceListing
    {
        $listing = TemplateMarketplaceListing::find($listingId);
        if (! $listing) {
            return null;
        }

        $listing->update(['status' => MarketplaceListingStatus::Suspended]);

        return $listing->fresh();
    }

    // ─── Purchases ───────────────────────────────────────

    public function purchaseTemplate(string $storeId, string $listingId, array $paymentData): ?TemplatePurchase
    {
        $listing = TemplateMarketplaceListing::find($listingId);
        if (! $listing || $listing->status !== MarketplaceListingStatus::Approved) {
            return null;
        }

        // Check if store already has an active purchase for this listing
        $existingPurchase = TemplatePurchase::where('store_id', $storeId)
            ->where('marketplace_listing_id', $listingId)
            ->where('is_active', true)
            ->first();

        if ($existingPurchase) {
            return null;
        }

        // For paid listings without any payment proof, don't activate yet
        $isPaid = $listing->pricing_type !== MarketplacePricingType::Free && (float) $listing->price_amount > 0;
        $hasPayment = ! empty($paymentData['provider_payment_id']) || ! empty($paymentData['payment_reference']);

        // If paid listing and no payment provided, create inactive purchase (pending payment)
        $shouldActivate = ! $isPaid || $hasPayment;

        return DB::transaction(function () use ($storeId, $listingId, $listing, $paymentData, $shouldActivate) {
            $purchaseType = $listing->pricing_type === MarketplacePricingType::Subscription
                ? PurchaseType::Subscription
                : PurchaseType::OneTime;

            $purchaseData = [
                'store_id' => $storeId,
                'marketplace_listing_id' => $listingId,
                'purchase_type' => $purchaseType,
                'amount_paid' => $listing->pricing_type === MarketplacePricingType::Free ? 0 : $listing->price_amount,
                'currency' => $listing->price_currency ?? 'SAR',
                'payment_reference' => $paymentData['payment_reference'] ?? null,
                'payment_gateway' => $paymentData['payment_gateway'] ?? null,
                'provider_payment_id' => $paymentData['provider_payment_id'] ?? null,
                'is_active' => $shouldActivate,
            ];

            if ($purchaseType === PurchaseType::Subscription) {
                $purchaseData['subscription_starts_at'] = now();
                $interval = $listing->subscription_interval?->value ?? 'monthly';
                $purchaseData['subscription_expires_at'] = $interval === 'yearly'
                    ? now()->addYear()
                    : now()->addMonth();
                $purchaseData['auto_renew'] = $paymentData['auto_renew'] ?? true;
            }

            $purchase = TemplatePurchase::create($purchaseData);

            // Generate invoice
            $invoice = $this->generateInvoice($purchase, $listing, $storeId);
            $purchase->update(['invoice_id' => $invoice->id]);

            // Increment download count
            $listing->increment('download_count');

            return $purchase->fresh(['invoice']);
        });
    }

    // ─── Invoice Generation ──────────────────────────────

    public function generateInvoice(TemplatePurchase $purchase, TemplateMarketplaceListing $listing, string $storeId): MarketplacePurchaseInvoice
    {
        $store = Store::with('organization')->find($storeId);

        $unitPrice = (float) $purchase->amount_paid;
        $taxRate = 15.00; // Saudi VAT
        $subtotal = $unitPrice;
        $taxAmount = round($subtotal * $taxRate / 100, 2);
        $totalAmount = round($subtotal + $taxAmount, 2);

        $isSubscription = $purchase->purchase_type === PurchaseType::Subscription;
        $billingPeriod = null;
        if ($isSubscription && $purchase->subscription_starts_at && $purchase->subscription_expires_at) {
            $billingPeriod = $purchase->subscription_starts_at->format('Y-m-d')
                . ' to '
                . $purchase->subscription_expires_at->format('Y-m-d');
        }

        $invoiceNumber = 'MKT-' . now()->format('Ymd') . '-' . strtoupper(substr(md5($purchase->id), 0, 6));

        return MarketplacePurchaseInvoice::create([
            'template_purchase_id' => $purchase->id,
            'invoice_number' => $invoiceNumber,
            'status' => $unitPrice > 0 ? 'paid' : 'paid',
            'store_id' => $storeId,
            'seller_name' => $listing->publisher_name,
            'seller_email' => null,
            'seller_vat_number' => null,
            'buyer_store_name' => $store?->name ?? 'Unknown Store',
            'buyer_organization_name' => $store?->organization?->name ?? null,
            'buyer_vat_number' => $store?->organization?->vat_number ?? null,
            'buyer_email' => $store?->email ?? null,
            'item_description' => 'Marketplace Template: ' . $listing->title,
            'quantity' => 1,
            'unit_price' => $unitPrice,
            'subtotal' => $subtotal,
            'tax_rate' => $taxRate,
            'tax_amount' => $taxAmount,
            'discount_amount' => 0.00,
            'total_amount' => $totalAmount,
            'currency' => $purchase->currency ?? 'SAR',
            'payment_method' => $purchase->payment_gateway,
            'payment_reference' => $purchase->payment_reference,
            'paid_at' => now(),
            'billing_period' => $billingPeriod,
            'is_recurring' => $isSubscription,
            'notes' => $isSubscription
                ? 'Subscription purchase – auto-renew ' . ($purchase->auto_renew ? 'enabled' : 'disabled')
                : 'One-time purchase',
            'notes_ar' => $isSubscription
                ? 'شراء اشتراك – التجديد التلقائي ' . ($purchase->auto_renew ? 'مفعّل' : 'معطّل')
                : 'شراء لمرة واحدة',
        ]);
    }

    public function getInvoice(string $invoiceId): ?MarketplacePurchaseInvoice
    {
        return MarketplacePurchaseInvoice::with('purchase.listing')->find($invoiceId);
    }

    public function getStoreInvoices(string $storeId): Collection
    {
        return MarketplacePurchaseInvoice::where('store_id', $storeId)
            ->with('purchase.listing')
            ->orderByDesc('created_at')
            ->get();
    }

    public function getStorePurchases(string $storeId): Collection
    {
        return TemplatePurchase::where('store_id', $storeId)
            ->with('listing')
            ->orderByDesc('created_at')
            ->get();
    }

    public function hasActivePurchase(string $storeId, string $listingId): bool
    {
        return TemplatePurchase::where('store_id', $storeId)
            ->where('marketplace_listing_id', $listingId)
            ->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('subscription_expires_at')
                    ->orWhere('subscription_expires_at', '>', now());
            })
            ->exists();
    }

    public function cancelSubscription(string $purchaseId): ?TemplatePurchase
    {
        $purchase = TemplatePurchase::find($purchaseId);
        if (! $purchase || $purchase->purchase_type !== PurchaseType::Subscription) {
            return null;
        }

        $purchase->update([
            'auto_renew' => false,
            'cancelled_at' => now(),
        ]);

        return $purchase->fresh();
    }

    // ─── Reviews ─────────────────────────────────────────

    public function getListingReviews(string $listingId): Collection
    {
        return TemplateReview::where('marketplace_listing_id', $listingId)
            ->where('is_published', true)
            ->orderByDesc('created_at')
            ->get();
    }

    public function createReview(string $listingId, string $storeId, string $userId, array $data): ?TemplateReview
    {
        // Check if user already reviewed this listing
        $existing = TemplateReview::where('marketplace_listing_id', $listingId)
            ->where('user_id', $userId)
            ->exists();

        if ($existing) {
            return null;
        }

        $isVerified = TemplatePurchase::where('store_id', $storeId)
            ->where('marketplace_listing_id', $listingId)
            ->where('is_active', true)
            ->exists();

        $review = TemplateReview::create([
            'marketplace_listing_id' => $listingId,
            'store_id' => $storeId,
            'user_id' => $userId,
            'rating' => $data['rating'],
            'title' => $data['title'] ?? null,
            'body' => $data['body'] ?? null,
            'is_verified_purchase' => $isVerified,
            'is_published' => true,
        ]);

        $this->recalculateRating($listingId);

        return $review;
    }

    public function updateReview(string $reviewId, array $data): ?TemplateReview
    {
        $review = TemplateReview::find($reviewId);
        if (! $review) {
            return null;
        }

        $allowed = ['rating', 'title', 'body'];
        $review->update(array_intersect_key($data, array_flip($allowed)));

        $this->recalculateRating($review->marketplace_listing_id);

        return $review->fresh();
    }

    public function deleteReview(string $reviewId): bool
    {
        $review = TemplateReview::find($reviewId);
        if (! $review) {
            return false;
        }

        $listingId = $review->marketplace_listing_id;
        $review->delete();

        $this->recalculateRating($listingId);

        return true;
    }

    public function respondToReview(string $reviewId, string $response): ?TemplateReview
    {
        $review = TemplateReview::find($reviewId);
        if (! $review) {
            return null;
        }

        $review->update([
            'admin_response' => $response,
            'admin_responded_at' => now(),
        ]);

        return $review->fresh();
    }

    // ─── Rating Recalculation ────────────────────────────

    private function recalculateRating(string $listingId): void
    {
        $stats = TemplateReview::where('marketplace_listing_id', $listingId)
            ->where('is_published', true)
            ->selectRaw('COALESCE(AVG(rating), 0) as avg_rating, COUNT(*) as review_count')
            ->first();

        TemplateMarketplaceListing::where('id', $listingId)->update([
            'average_rating' => round($stats->avg_rating, 1),
            'review_count' => $stats->review_count,
        ]);
    }

    // ─── Payment Activation ─────────────────────────────

    /**
     * Activate a pending marketplace purchase after payment confirmation.
     * Called from ProviderPaymentService::activatePurpose() on IPN success.
     */
    public function activatePurchaseByPayment(string $providerPaymentId): void
    {
        $purchase = TemplatePurchase::where('provider_payment_id', $providerPaymentId)
            ->where('is_active', false)
            ->first();

        if (! $purchase) {
            return;
        }

        $purchase->update([
            'is_active' => true,
            'payment_gateway' => 'paytabs',
        ]);

        // Update invoice status
        if ($purchase->invoice_id) {
            MarketplacePurchaseInvoice::where('id', $purchase->invoice_id)->update([
                'status' => 'paid',
                'paid_at' => now(),
                'payment_method' => 'paytabs',
            ]);
        }
    }
}
