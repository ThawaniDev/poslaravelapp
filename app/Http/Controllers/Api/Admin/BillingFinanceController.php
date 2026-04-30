<?php

namespace App\Http\Controllers\Api\Admin;

use App\Domain\AdminPanel\Models\AdminActivityLog;
use App\Domain\Billing\Models\HardwareSale;
use App\Domain\Billing\Models\ImplementationFee;
use App\Domain\Billing\Models\PaymentGatewayConfig;
use App\Domain\Billing\Models\PaymentRetryRule;
use App\Domain\ProviderSubscription\Models\Invoice;
use App\Domain\ProviderSubscription\Models\InvoiceLineItem;
use App\Domain\ProviderSubscription\Models\StoreSubscription;
use App\Domain\ZatcaCompliance\Enums\InvoiceStatus;
use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Admin\CreateGatewayConfigRequest;
use App\Http\Requests\Admin\CreateHardwareSaleRequest;
use App\Http\Requests\Admin\CreateImplementationFeeRequest;
use App\Http\Requests\Admin\CreateManualInvoiceRequest;
use App\Http\Requests\Admin\ProcessRefundRequest;
use App\Http\Requests\Admin\UpdateGatewayConfigRequest;
use App\Http\Requests\Admin\UpdateHardwareSaleRequest;
use App\Http\Requests\Admin\UpdateImplementationFeeRequest;
use App\Http\Requests\Admin\UpdateRetryRulesRequest;
use App\Http\Resources\Admin\HardwareSaleResource;
use App\Http\Resources\Admin\ImplementationFeeResource;
use App\Http\Resources\Admin\InvoiceResource;
use App\Http\Resources\Admin\PaymentGatewayConfigResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class BillingFinanceController extends BaseApiController
{
    // ─── Invoices ──────────────────────────────────────────────

    public function listInvoices(Request $request): JsonResponse
    {
        $query = Invoice::with('invoiceLineItems');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%");
            });
        }

        if ($request->filled('date_from')) {
            $query->where('created_at', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->where('created_at', '<=', $request->input('date_to'));
        }

        if ($request->filled('amount_min')) {
            $query->where('total', '>=', $request->input('amount_min'));
        }

        if ($request->filled('amount_max')) {
            $query->where('total', '<=', $request->input('amount_max'));
        }

        $invoices = $query->orderBy('created_at', 'desc')
            ->paginate($request->integer('per_page', 20));

        return $this->success([
            'invoices' => InvoiceResource::collection($invoices->items())->resolve(),
            'pagination' => [
                'current_page' => $invoices->currentPage(),
                'last_page' => $invoices->lastPage(),
                'per_page' => $invoices->perPage(),
                'total' => $invoices->total(),
            ],
        ], 'Invoices retrieved');
    }

    public function showInvoice(string $invoiceId): JsonResponse
    {
        $invoice = Invoice::with('invoiceLineItems')->find($invoiceId);

        if (!$invoice) {
            return $this->notFound('Invoice not found');
        }

        return $this->success(
            (new InvoiceResource($invoice))->resolve(),
            'Invoice retrieved',
        );
    }

    public function createManualInvoice(CreateManualInvoiceRequest $request): JsonResponse
    {
        $subscription = StoreSubscription::find($request->input('store_subscription_id'));

        if (!$subscription) {
            return $this->notFound('Subscription not found');
        }

        $lineItems = $request->input('line_items');
        $subtotal = collect($lineItems)->sum(fn ($item) => $item['quantity'] * $item['unit_price']);
        $taxRate = $request->input('tax_rate', 15);
        $tax = round($subtotal * ($taxRate / 100), 2);
        $total = round($subtotal + $tax, 2);

        $invoice = Invoice::forceCreate([
            'id' => Str::uuid()->toString(),
            'store_subscription_id' => $subscription->id,
            'invoice_number' => 'INV-' . strtoupper(Str::random(8)),
            'amount' => $subtotal,
            'tax' => $tax,
            'total' => $total,
            'status' => 'pending',
            'due_date' => $request->input('due_date', now()->addDays(7)),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach ($lineItems as $item) {
            InvoiceLineItem::forceCreate([
                'id' => Str::uuid()->toString(),
                'invoice_id' => $invoice->id,
                'description' => $item['description'],
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'],
                'total' => round($item['quantity'] * $item['unit_price'], 2),
            ]);
        }

        $invoice = $invoice->fresh(['invoiceLineItems']);

        $this->logActivity('invoice_created', "Manual invoice {$invoice->invoice_number} created", $invoice->id);

        return $this->created(
            (new InvoiceResource($invoice))->resolve(),
            'Invoice created',
        );
    }

    public function markInvoicePaid(string $invoiceId): JsonResponse
    {
        $invoice = Invoice::find($invoiceId);

        if (!$invoice) {
            return $this->notFound('Invoice not found');
        }

        $statusValue = $invoice->status instanceof \BackedEnum ? $invoice->status->value : $invoice->status;

        if ($statusValue === 'paid') {
            return $this->error('Invoice is already paid', 422);
        }

        $invoice->update([
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        $this->logActivity('invoice_marked_paid', "Invoice {$invoice->invoice_number} marked as paid", $invoiceId);

        return $this->success(
            (new InvoiceResource($invoice->fresh('invoiceLineItems')))->resolve(),
            'Invoice marked as paid',
        );
    }

    public function processRefund(ProcessRefundRequest $request, string $invoiceId): JsonResponse
    {
        $invoice = Invoice::with('invoiceLineItems')->find($invoiceId);

        if (!$invoice) {
            return $this->notFound('Invoice not found');
        }

        $statusValue = $invoice->status instanceof \BackedEnum ? $invoice->status->value : $invoice->status;

        if ($statusValue !== 'paid') {
            return $this->error('Only paid invoices can be refunded', 422);
        }

        $refundAmount = $request->input('amount');

        if ($refundAmount > (float) $invoice->total) {
            return $this->error('Refund amount exceeds invoice total', 422);
        }

        // Create a negative line item for the refund
        InvoiceLineItem::forceCreate([
            'id' => Str::uuid()->toString(),
            'invoice_id' => $invoice->id,
            'description' => 'Refund: ' . $request->input('reason'),
            'quantity' => 1,
            'unit_price' => -$refundAmount,
            'total' => -$refundAmount,
        ]);

        // If full refund, mark as refunded
        if ($refundAmount >= (float) $invoice->total) {
            $invoice->update(['status' => 'refunded']);
        }

        $this->logActivity(
            'refund_processed',
            "Refund of {$refundAmount} SAR processed for invoice {$invoice->invoice_number}",
            $invoiceId,
        );

        return $this->success(
            (new InvoiceResource($invoice->fresh('invoiceLineItems')))->resolve(),
            'Refund processed',
        );
    }

    public function invoicePdfUrl(string $invoiceId): JsonResponse
    {
        $invoice = Invoice::find($invoiceId);

        if (!$invoice) {
            return $this->notFound('Invoice not found');
        }

        return $this->success([
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'pdf_url' => $invoice->pdf_url,
        ], 'Invoice PDF URL retrieved');
    }

    // ─── Failed Payments ───────────────────────────────────────

    public function listFailedPayments(Request $request): JsonResponse
    {
        $query = Invoice::where('status', 'failed');

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where('invoice_number', 'like', "%{$search}%");
        }

        $invoices = $query->orderBy('created_at', 'desc')
            ->paginate($request->integer('per_page', 20));

        return $this->success([
            'invoices' => InvoiceResource::collection($invoices->items())->resolve(),
            'pagination' => [
                'current_page' => $invoices->currentPage(),
                'last_page' => $invoices->lastPage(),
                'per_page' => $invoices->perPage(),
                'total' => $invoices->total(),
            ],
        ], 'Failed payments retrieved');
    }

    public function retryPayment(string $invoiceId): JsonResponse
    {
        $invoice = Invoice::find($invoiceId);

        if (!$invoice) {
            return $this->notFound('Invoice not found');
        }

        $statusValue = $invoice->status instanceof \BackedEnum ? $invoice->status->value : $invoice->status;

        if ($statusValue !== 'failed') {
            return $this->error('Only failed invoices can be retried', 422);
        }

        // In production, this would dispatch a job to process the payment
        // For now, mark as pending for retry
        $invoice->update(['status' => 'pending']);

        $this->logActivity('payment_retry', "Payment retry initiated for invoice {$invoice->invoice_number}", $invoiceId);

        return $this->success(
            (new InvoiceResource($invoice->fresh()))->resolve(),
            'Payment retry initiated',
        );
    }

    // ─── Payment Retry Rules ───────────────────────────────────

    public function getRetryRules(): JsonResponse
    {
        $rules = PaymentRetryRule::first();

        if (!$rules) {
            // Return defaults
            return $this->success([
                'max_retries' => 3,
                'retry_interval_hours' => 24,
                'grace_period_after_failure_days' => 7,
            ], 'Retry rules retrieved');
        }

        return $this->success([
            'id' => $rules->id,
            'max_retries' => (int) $rules->max_retries,
            'retry_interval_hours' => (int) $rules->retry_interval_hours,
            'grace_period_after_failure_days' => (int) $rules->grace_period_after_failure_days,
        ], 'Retry rules retrieved');
    }

    public function updateRetryRules(UpdateRetryRulesRequest $request): JsonResponse
    {
        $rules = PaymentRetryRule::first();

        if ($rules) {
            $rules->update([
                'max_retries' => $request->input('max_retries'),
                'retry_interval_hours' => $request->input('retry_interval_hours'),
                'grace_period_after_failure_days' => $request->input('grace_period_after_failure_days'),
                'updated_at' => now(),
            ]);
        } else {
            $rules = PaymentRetryRule::forceCreate([
                'id' => Str::uuid()->toString(),
                'max_retries' => $request->input('max_retries'),
                'retry_interval_hours' => $request->input('retry_interval_hours'),
                'grace_period_after_failure_days' => $request->input('grace_period_after_failure_days'),
                'updated_at' => now(),
            ]);
        }

        $this->logActivity('retry_rules_updated', 'Payment retry rules updated');

        return $this->success([
            'id' => $rules->id,
            'max_retries' => (int) $rules->max_retries,
            'retry_interval_hours' => (int) $rules->retry_interval_hours,
            'grace_period_after_failure_days' => (int) $rules->grace_period_after_failure_days,
        ], 'Retry rules updated');
    }

    // ─── Revenue Dashboard ─────────────────────────────────────

    public function revenueDashboard(): JsonResponse
    {
        // Monthly Recurring Revenue: sum of paid invoices this month
        $monthStart = now()->startOfMonth();
        $monthEnd = now()->endOfMonth();

        $mrr = (float) Invoice::where('status', 'paid')
            ->whereBetween('paid_at', [$monthStart, $monthEnd])
            ->sum('total');

        $arr = $mrr * 12;

        // Revenue by status
        $revenueByStatus = Invoice::selectRaw('status, COALESCE(SUM(total), 0) as revenue, COUNT(*) as count')
            ->groupBy('status')
            ->get()
            ->map(fn ($row) => [
                'status' => $row->status instanceof \BackedEnum ? $row->status->value : $row->status,
                'revenue' => (float) $row->revenue,
                'count' => (int) $row->count,
            ]);

        // Upcoming renewals (next 7 days)
        $upcomingRenewals = StoreSubscription::where('current_period_end', '<=', now()->addDays(7))
            ->where('current_period_end', '>=', now())
            ->where('status', 'active')
            ->count();

        // Hardware Sales revenue
        $hardwareRevenue = (float) HardwareSale::sum('amount');

        // Implementation fees revenue
        $implementationRevenue = (float) ImplementationFee::where('status', 'paid')->sum('amount');

        // Total invoices stats
        $totalInvoices = Invoice::count();
        $paidInvoices = Invoice::where('status', 'paid')->count();
        $failedInvoices = Invoice::where('status', 'failed')->count();

        return $this->success([
            'mrr' => $mrr,
            'arr' => $arr,
            'revenue_by_status' => $revenueByStatus,
            'upcoming_renewals' => $upcomingRenewals,
            'hardware_revenue' => $hardwareRevenue,
            'implementation_revenue' => $implementationRevenue,
            'total_invoices' => $totalInvoices,
            'paid_invoices' => $paidInvoices,
            'failed_invoices' => $failedInvoices,
        ], 'Revenue dashboard retrieved');
    }

    // ─── Payment Gateway Configs ───────────────────────────────

    public function listGateways(Request $request): JsonResponse
    {
        $query = PaymentGatewayConfig::query();

        if ($request->filled('environment')) {
            $query->where('environment', $request->input('environment'));
        }

        if ($request->boolean('active_only', false)) {
            $query->where('is_active', true);
        }

        $gateways = $query->orderBy('gateway_name')->get();

        return $this->success(
            PaymentGatewayConfigResource::collection($gateways)->resolve(),
            'Gateways retrieved',
        );
    }

    public function showGateway(string $gatewayId): JsonResponse
    {
        $gateway = PaymentGatewayConfig::find($gatewayId);

        if (!$gateway) {
            return $this->notFound('Gateway config not found');
        }

        return $this->success(
            (new PaymentGatewayConfigResource($gateway))->resolve(),
            'Gateway config retrieved',
        );
    }

    public function createGateway(CreateGatewayConfigRequest $request): JsonResponse
    {
        // Check for duplicate gateway+environment
        $exists = PaymentGatewayConfig::where('gateway_name', $request->input('gateway_name'))
            ->where('environment', $request->input('environment'))
            ->exists();

        if ($exists) {
            return $this->error('Gateway config already exists for this environment', 422);
        }

        $gateway = PaymentGatewayConfig::forceCreate([
            'id' => Str::uuid()->toString(),
            'gateway_name' => $request->input('gateway_name'),
            'credentials_encrypted' => encrypt($request->input('credentials')),
            'webhook_url' => $request->input('webhook_url'),
            'environment' => $request->input('environment'),
            'is_active' => $request->boolean('is_active', true),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->logActivity('gateway_created', "Payment gateway {$gateway->gateway_name} ({$gateway->environment}) created", $gateway->id);

        return $this->created(
            (new PaymentGatewayConfigResource($gateway->fresh()))->resolve(),
            'Gateway config created',
        );
    }

    public function updateGateway(UpdateGatewayConfigRequest $request, string $gatewayId): JsonResponse
    {
        $gateway = PaymentGatewayConfig::find($gatewayId);

        if (!$gateway) {
            return $this->notFound('Gateway config not found');
        }

        $data = $request->only(['gateway_name', 'webhook_url', 'environment', 'is_active']);

        if ($request->has('credentials')) {
            $data['credentials_encrypted'] = encrypt($request->input('credentials'));
        }

        $gateway->update($data);

        $this->logActivity('gateway_updated', "Payment gateway {$gateway->gateway_name} updated", $gatewayId);

        return $this->success(
            (new PaymentGatewayConfigResource($gateway->fresh()))->resolve(),
            'Gateway config updated',
        );
    }

    public function deleteGateway(string $gatewayId): JsonResponse
    {
        $gateway = PaymentGatewayConfig::find($gatewayId);

        if (!$gateway) {
            return $this->notFound('Gateway config not found');
        }

        $name = $gateway->gateway_name;
        $gateway->delete();

        $this->logActivity('gateway_deleted', "Payment gateway {$name} deleted", $gatewayId);

        return $this->success(null, 'Gateway config deleted');
    }

    public function testGatewayConnection(string $gatewayId): JsonResponse
    {
        $gateway = PaymentGatewayConfig::find($gatewayId);

        if (!$gateway) {
            return $this->notFound('Gateway config not found');
        }

        // In production, this would actually test the connection to the payment provider
        // For now, just verify credentials exist and return success
        $hasCredentials = !empty($gateway->credentials_encrypted);

        return $this->success([
            'gateway_id' => $gateway->id,
            'gateway_name' => $gateway->gateway_name,
            'environment' => $gateway->environment,
            'connection_status' => $hasCredentials ? 'ok' : 'missing_credentials',
            'tested_at' => now()->toIso8601String(),
        ], $hasCredentials ? 'Connection test successful' : 'Missing credentials');
    }

    // ─── Hardware Sales ────────────────────────────────────────

    public function listHardwareSales(Request $request): JsonResponse
    {
        $query = HardwareSale::with(['store', 'soldByAdmin']);

        if ($request->filled('store_id')) {
            $query->where('store_id', $request->input('store_id'));
        }

        if ($request->filled('item_type')) {
            $query->where('item_type', $request->input('item_type'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('serial_number', 'like', "%{$search}%")
                    ->orWhere('item_description', 'like', "%{$search}%");
            });
        }

        $sales = $query->orderBy('sold_at', 'desc')
            ->paginate($request->integer('per_page', 20));

        return $this->success([
            'hardware_sales' => HardwareSaleResource::collection($sales->items())->resolve(),
            'pagination' => [
                'current_page' => $sales->currentPage(),
                'last_page' => $sales->lastPage(),
                'per_page' => $sales->perPage(),
                'total' => $sales->total(),
            ],
        ], 'Hardware sales retrieved');
    }

    public function showHardwareSale(string $saleId): JsonResponse
    {
        $sale = HardwareSale::with(['store', 'soldByAdmin'])->find($saleId);

        if (!$sale) {
            return $this->notFound('Hardware sale not found');
        }

        return $this->success(
            (new HardwareSaleResource($sale))->resolve(),
            'Hardware sale retrieved',
        );
    }

    public function createHardwareSale(CreateHardwareSaleRequest $request): JsonResponse
    {
        $admin = $request->user('admin-api');

        $sale = HardwareSale::forceCreate([
            'id' => Str::uuid()->toString(),
            'store_id' => $request->input('store_id'),
            'sold_by' => $admin->id,
            'item_type' => $request->input('item_type'),
            'item_description' => $request->input('item_description'),
            'serial_number' => $request->input('serial_number'),
            'amount' => $request->input('amount'),
            'notes' => $request->input('notes'),
            'sold_at' => now(),
        ]);

        $this->logActivity('hardware_sale_created', "Hardware sale ({$sale->item_type}) recorded for store", $sale->id);

        return $this->created(
            (new HardwareSaleResource($sale->fresh(['store', 'soldByAdmin'])))->resolve(),
            'Hardware sale recorded',
        );
    }

    public function updateHardwareSale(UpdateHardwareSaleRequest $request, string $saleId): JsonResponse
    {
        $sale = HardwareSale::find($saleId);

        if (!$sale) {
            return $this->notFound('Hardware sale not found');
        }

        $sale->update($request->only([
            'item_type', 'item_description', 'serial_number', 'amount', 'notes',
        ]));

        return $this->success(
            (new HardwareSaleResource($sale->fresh(['store', 'soldByAdmin'])))->resolve(),
            'Hardware sale updated',
        );
    }

    public function deleteHardwareSale(string $saleId): JsonResponse
    {
        $sale = HardwareSale::find($saleId);

        if (!$sale) {
            return $this->notFound('Hardware sale not found');
        }

        $sale->delete();

        $this->logActivity('hardware_sale_deleted', 'Hardware sale deleted', $saleId);

        return $this->success(null, 'Hardware sale deleted');
    }

    // ─── Implementation / Training Fees ────────────────────────

    public function listImplementationFees(Request $request): JsonResponse
    {
        $query = ImplementationFee::with('store');

        if ($request->filled('store_id')) {
            $query->where('store_id', $request->input('store_id'));
        }

        if ($request->filled('fee_type')) {
            $query->where('fee_type', $request->input('fee_type'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $fees = $query->orderBy('created_at', 'desc')
            ->paginate($request->integer('per_page', 20));

        return $this->success([
            'implementation_fees' => ImplementationFeeResource::collection($fees->items())->resolve(),
            'pagination' => [
                'current_page' => $fees->currentPage(),
                'last_page' => $fees->lastPage(),
                'per_page' => $fees->perPage(),
                'total' => $fees->total(),
            ],
        ], 'Implementation fees retrieved');
    }

    public function showImplementationFee(string $feeId): JsonResponse
    {
        $fee = ImplementationFee::with('store')->find($feeId);

        if (!$fee) {
            return $this->notFound('Implementation fee not found');
        }

        return $this->success(
            (new ImplementationFeeResource($fee))->resolve(),
            'Implementation fee retrieved',
        );
    }

    public function createImplementationFee(CreateImplementationFeeRequest $request): JsonResponse
    {
        $fee = ImplementationFee::forceCreate([
            'id' => Str::uuid()->toString(),
            'store_id' => $request->input('store_id'),
            'fee_type' => $request->input('fee_type'),
            'amount' => $request->input('amount'),
            'status' => $request->input('status', 'invoiced'),
            'notes' => $request->input('notes'),
            'created_at' => now(),
        ]);

        $this->logActivity('implementation_fee_created', "Implementation fee ({$fee->fee_type}) created", $fee->id);

        return $this->created(
            (new ImplementationFeeResource($fee->fresh('store')))->resolve(),
            'Implementation fee created',
        );
    }

    public function updateImplementationFee(UpdateImplementationFeeRequest $request, string $feeId): JsonResponse
    {
        $fee = ImplementationFee::find($feeId);

        if (!$fee) {
            return $this->notFound('Implementation fee not found');
        }

        $fee->update($request->only(['fee_type', 'amount', 'status', 'notes']));

        return $this->success(
            (new ImplementationFeeResource($fee->fresh('store')))->resolve(),
            'Implementation fee updated',
        );
    }

    public function deleteImplementationFee(string $feeId): JsonResponse
    {
        $fee = ImplementationFee::find($feeId);

        if (!$fee) {
            return $this->notFound('Implementation fee not found');
        }

        $fee->delete();

        $this->logActivity('implementation_fee_deleted', 'Implementation fee deleted', $feeId);

        return $this->success(null, 'Implementation fee deleted');
    }

    // ─── Helpers ───────────────────────────────────────────────

    private function logActivity(string $action, string $description, ?string $targetId = null): void
    {
        try {
            $admin = request()->user('admin-api');
            AdminActivityLog::forceCreate([
                'id' => Str::uuid()->toString(),
                'admin_user_id' => $admin?->id,
                'action' => $action,
                'entity_type' => 'billing',
                'entity_id' => $targetId,
                'details' => ['description' => $description],
                'ip_address' => request()->ip(),
                'created_at' => now(),
            ]);
        } catch (\Throwable) {
            // Silently fail — logging should not break the operation
        }
    }
}
