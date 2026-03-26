<?php

namespace App\Domain\ContentOnboarding\Controllers\Api;

use App\Domain\ContentOnboarding\Services\MarketplaceService;
use App\Http\Controllers\Api\BaseApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MarketplaceController extends BaseApiController
{
    public function __construct(private readonly MarketplaceService $service) {}

    // ─── Browse ──────────────────────────────────────────

    public function listings(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'category_id' => ['sometimes', 'uuid'],
            'pricing_type' => ['sometimes', 'string', 'in:free,one_time,subscription'],
            'is_featured' => ['sometimes', 'boolean'],
            'search' => ['sometimes', 'string', 'max:100'],
            'min_rating' => ['sometimes', 'numeric', 'min:0', 'max:5'],
            'sort_by' => ['sometimes', 'string', 'in:published_at,average_rating,download_count,price_amount,title'],
            'sort_dir' => ['sometimes', 'string', 'in:asc,desc'],
        ]);

        $listings = $this->service->browseListings($filters);

        return $this->success($listings, __('ui.marketplace_listings_loaded'));
    }

    public function listing(string $id): JsonResponse
    {
        $listing = $this->service->getListing($id);

        if (! $listing) {
            return $this->notFound(__('ui.marketplace_listing_not_found'));
        }

        return $this->success($listing, __('ui.marketplace_listing_loaded'));
    }

    // ─── Categories ──────────────────────────────────────

    public function categories(): JsonResponse
    {
        $categories = $this->service->getCategories();

        return $this->success($categories, __('ui.marketplace_categories_loaded'));
    }

    public function category(string $id): JsonResponse
    {
        $category = $this->service->getCategory($id);

        if (! $category) {
            return $this->notFound(__('ui.marketplace_category_not_found'));
        }

        return $this->success($category, __('ui.marketplace_category_loaded'));
    }

    // ─── Purchases ───────────────────────────────────────

    public function purchase(Request $request, string $listingId): JsonResponse
    {
        $validated = $request->validate([
            'payment_reference' => ['nullable', 'string', 'max:100'],
            'payment_gateway' => ['nullable', 'string', 'max:30'],
            'auto_renew' => ['sometimes', 'boolean'],
        ]);

        $storeId = $request->user()->store_id;

        if (! $storeId) {
            return $this->error(__('ui.no_store_associated'), 403);
        }

        $purchase = $this->service->purchaseTemplate($storeId, $listingId, $validated);

        if (! $purchase) {
            return $this->error(__('ui.purchase_failed'), 422);
        }

        return $this->created($purchase, __('ui.purchase_completed'));
    }

    public function myPurchases(Request $request): JsonResponse
    {
        $storeId = $request->user()->store_id;

        if (! $storeId) {
            return $this->error(__('ui.no_store_associated'), 403);
        }

        $purchases = $this->service->getStorePurchases($storeId);

        return $this->success($purchases, __('ui.purchases_loaded'));
    }

    public function checkAccess(Request $request, string $listingId): JsonResponse
    {
        $storeId = $request->user()->store_id;

        if (! $storeId) {
            return $this->error(__('ui.no_store_associated'), 403);
        }

        $hasAccess = $this->service->hasActivePurchase($storeId, $listingId);

        return $this->success(['has_access' => $hasAccess], __('ui.access_checked'));
    }

    public function cancelSubscription(string $purchaseId): JsonResponse
    {
        $purchase = $this->service->cancelSubscription($purchaseId);

        if (! $purchase) {
            return $this->error(__('ui.cancel_subscription_failed'), 422);
        }

        return $this->success($purchase, __('ui.subscription_cancelled'));
    }

    // ─── Invoices ────────────────────────────────────────

    public function myInvoices(Request $request): JsonResponse
    {
        $storeId = $request->user()->store_id;

        if (! $storeId) {
            return $this->error(__('ui.no_store_associated'), 403);
        }

        $invoices = $this->service->getStoreInvoices($storeId);

        return $this->success($invoices, __('ui.invoices_loaded'));
    }

    public function invoice(string $invoiceId): JsonResponse
    {
        $invoice = $this->service->getInvoice($invoiceId);

        if (! $invoice) {
            return $this->notFound(__('ui.invoice_not_found'));
        }

        return $this->success($invoice, __('ui.invoice_loaded'));
    }

    // ─── Reviews ─────────────────────────────────────────

    public function reviews(string $listingId): JsonResponse
    {
        $reviews = $this->service->getListingReviews($listingId);

        return $this->success($reviews, __('ui.reviews_loaded'));
    }

    public function createReview(Request $request, string $listingId): JsonResponse
    {
        $validated = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'title' => ['nullable', 'string', 'max:200'],
            'body' => ['nullable', 'string', 'max:2000'],
        ]);

        $storeId = $request->user()->store_id;

        if (! $storeId) {
            return $this->error(__('ui.no_store_associated'), 403);
        }

        $review = $this->service->createReview(
            $listingId,
            $storeId,
            $request->user()->id,
            $validated,
        );

        if (! $review) {
            return $this->error(__('ui.review_create_failed'), 422);
        }

        return $this->created($review, __('ui.review_created'));
    }

    public function updateReview(Request $request, string $reviewId): JsonResponse
    {
        $validated = $request->validate([
            'rating' => ['sometimes', 'integer', 'min:1', 'max:5'],
            'title' => ['nullable', 'string', 'max:200'],
            'body' => ['nullable', 'string', 'max:2000'],
        ]);

        $review = $this->service->updateReview($reviewId, $validated);

        if (! $review) {
            return $this->notFound(__('ui.review_not_found'));
        }

        return $this->success($review, __('ui.review_updated'));
    }

    public function deleteReview(string $reviewId): JsonResponse
    {
        $deleted = $this->service->deleteReview($reviewId);

        if (! $deleted) {
            return $this->notFound(__('ui.review_not_found'));
        }

        return $this->success(null, __('ui.review_deleted'));
    }
}
