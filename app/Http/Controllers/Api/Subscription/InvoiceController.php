<?php

namespace App\Http\Controllers\Api\Subscription;

use App\Domain\ProviderSubscription\Services\BillingService;
use App\Http\Controllers\Api\BaseApiController;
use App\Http\Resources\Subscription\InvoiceResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InvoiceController extends BaseApiController
{
    public function __construct(
        private readonly BillingService $billingService,
    ) {}

    /**
     * GET /subscription/invoices — List invoices for the store.
     */
    public function index(Request $request): JsonResponse
    {
        $organizationId = $request->user()->organization_id;

        if (! $organizationId) {
            return $this->error('No organization assigned to this user.', 404);
        }

        $perPage = $request->integer('per_page', 20);
        $invoices = $this->billingService->getInvoices($organizationId, $perPage);

        return $this->success([
            'data' => InvoiceResource::collection($invoices->items()),
            'meta' => [
                'current_page' => $invoices->currentPage(),
                'last_page' => $invoices->lastPage(),
                'per_page' => $invoices->perPage(),
                'total' => $invoices->total(),
            ],
        ]);
    }

    /**
     * GET /subscription/invoices/{invoiceId} — Get a single invoice.
     */
    public function show(string $invoiceId): JsonResponse
    {
        try {
            $invoice = $this->billingService->getInvoice($invoiceId);

            return $this->success(new InvoiceResource($invoice));
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->notFound('Invoice not found.');
        }
    }
}
