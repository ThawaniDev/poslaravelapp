<?php

namespace App\Http\Controllers\Api\Admin;

use App\Domain\AccountingIntegration\Models\AccountingExport;
use App\Domain\AccountingIntegration\Models\AccountMapping;
use App\Domain\AccountingIntegration\Models\AutoExportConfig;
use App\Domain\AccountingIntegration\Models\StoreAccountingConfig;
use App\Domain\Payment\Models\CashEvent;
use App\Domain\Payment\Models\CashSession;
use App\Domain\Payment\Models\Expense;
use App\Domain\Payment\Models\GiftCard;
use App\Domain\Payment\Models\GiftCardTransaction;
use App\Domain\Payment\Models\Payment;
use App\Domain\Payment\Models\Refund;
use App\Domain\Report\Models\DailySalesSummary;
use App\Domain\Report\Models\ProductSalesSummary;
use App\Domain\ThawaniIntegration\Models\ThawaniOrderMapping;
use App\Domain\ThawaniIntegration\Models\ThawaniSettlement;
use App\Domain\ThawaniIntegration\Models\ThawaniStoreConfig;
use App\Domain\AccountingIntegration\Enums\AccountingExportStatus;
use App\Domain\AccountingIntegration\Enums\ExportTriggeredBy;
use App\Domain\Payment\Enums\GiftCardStatus;
use App\Domain\Payment\Enums\RefundStatus;
use App\Domain\Security\Enums\SessionStatus;
use App\Http\Controllers\Api\BaseApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FinancialOperationsController extends BaseApiController
{
    // ── Overview ─────────────────────────────────────────────
    public function overview(): JsonResponse
    {
        return $this->success([
            'payments' => [
                'total'       => Payment::count(),
                'total_amount' => (float) Payment::sum('amount'),
            ],
            'refunds' => [
                'total'       => Refund::count(),
                'total_amount' => (float) Refund::sum('amount'),
                'pending'     => Refund::where('status', 'pending')->count(),
                'completed'   => Refund::where('status', 'completed')->count(),
            ],
            'cash_sessions' => [
                'total' => CashSession::count(),
                'open'  => CashSession::where('status', 'open')->count(),
            ],
            'expenses' => [
                'total'       => Expense::count(),
                'total_amount' => (float) Expense::sum('amount'),
            ],
            'gift_cards' => [
                'total'       => GiftCard::count(),
                'active'      => GiftCard::where('status', 'active')->count(),
                'total_balance' => (float) GiftCard::sum('balance'),
            ],
            'accounting_exports' => [
                'total'   => AccountingExport::count(),
                'pending' => AccountingExport::where('status', 'pending')->count(),
                'success' => AccountingExport::where('status', 'success')->count(),
                'failed'  => AccountingExport::where('status', 'failed')->count(),
            ],
            'thawani_settlements' => [
                'total'      => ThawaniSettlement::count(),
                'total_gross' => (float) ThawaniSettlement::sum('gross_amount'),
                'total_net'  => (float) ThawaniSettlement::sum('net_amount'),
            ],
        ], 'Financial operations overview');
    }

    // ── Payments ─────────────────────────────────────────────
    public function payments(Request $request): JsonResponse
    {
        $q = Payment::query()->latest();

        if ($request->filled('method')) {
            $q->where('method', $request->method);
        }

        return $this->success($q->paginate($request->integer('per_page', 15)), 'Payments retrieved');
    }

    public function showPayment(string $id): JsonResponse
    {
        $payment = Payment::find($id);
        if (! $payment) return $this->notFound('Payment not found');
        return $this->success($payment, 'Payment details');
    }

    // ── Refunds ──────────────────────────────────────────────
    public function refunds(Request $request): JsonResponse
    {
        $q = Refund::query()->latest('created_at');

        if ($request->filled('status')) {
            $q->where('status', $request->status);
        }

        return $this->success($q->paginate($request->integer('per_page', 15)), 'Refunds retrieved');
    }

    public function showRefund(string $id): JsonResponse
    {
        $refund = Refund::find($id);
        if (! $refund) return $this->notFound('Refund not found');
        return $this->success($refund, 'Refund details');
    }

    public function processRefund(Request $request, string $id): JsonResponse
    {
        $refund = Refund::find($id);
        if (! $refund) return $this->notFound('Refund not found');

        $validated = $request->validate([
            'status'           => 'required|string|in:completed,failed',
            'reference_number' => 'nullable|string|max:100',
        ]);

        $refund->forceFill([
            'status'           => RefundStatus::from($validated['status']),
            'reference_number' => $validated['reference_number'] ?? $refund->reference_number,
            'processed_by'     => $request->user('admin-api')?->id,
        ])->save();

        return $this->success($refund->fresh(), 'Refund processed');
    }

    // ── Cash Sessions ────────────────────────────────────────
    public function cashSessions(Request $request): JsonResponse
    {
        $q = CashSession::query()->latest();

        if ($request->filled('status')) {
            $q->where('status', $request->status);
        }
        if ($request->filled('store_id')) {
            $q->where('store_id', $request->store_id);
        }

        return $this->success($q->paginate($request->integer('per_page', 15)), 'Cash sessions retrieved');
    }

    public function showCashSession(string $id): JsonResponse
    {
        $session = CashSession::find($id);
        if (! $session) return $this->notFound('Cash session not found');
        return $this->success($session, 'Cash session details');
    }

    public function forceCloseCashSession(Request $request, string $id): JsonResponse
    {
        $session = CashSession::find($id);
        if (! $session) return $this->notFound('Cash session not found');

        if ($session->status !== SessionStatus::Open) {
            return $this->error('Cash session is not open', 422);
        }

        $session->forceFill([
            'status'      => SessionStatus::Closed,
            'closed_by'   => $request->user('admin-api')?->id,
            'closed_at'   => now(),
            'close_notes' => $request->input('notes', 'Force-closed by admin'),
        ])->save();

        return $this->success($session->fresh(), 'Cash session force-closed');
    }

    // ── Cash Events ──────────────────────────────────────────
    public function cashEvents(Request $request): JsonResponse
    {
        $q = CashEvent::query()->latest();

        if ($request->filled('cash_session_id')) {
            $q->where('cash_session_id', $request->cash_session_id);
        }
        if ($request->filled('type')) {
            $q->where('type', $request->type);
        }

        return $this->success($q->paginate($request->integer('per_page', 15)), 'Cash events retrieved');
    }

    public function showCashEvent(string $id): JsonResponse
    {
        $event = CashEvent::find($id);
        if (! $event) return $this->notFound('Cash event not found');
        return $this->success($event, 'Cash event details');
    }

    // ── Expenses ─────────────────────────────────────────────
    public function expenses(Request $request): JsonResponse
    {
        $q = Expense::query()->latest();

        if ($request->filled('category')) {
            $q->where('category', $request->category);
        }
        if ($request->filled('store_id')) {
            $q->where('store_id', $request->store_id);
        }

        return $this->success($q->paginate($request->integer('per_page', 15)), 'Expenses retrieved');
    }

    public function showExpense(string $id): JsonResponse
    {
        $expense = Expense::find($id);
        if (! $expense) return $this->notFound('Expense not found');
        return $this->success($expense, 'Expense details');
    }

    public function createExpense(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'store_id'     => 'required|uuid',
            'amount'       => 'required|numeric|min:0.01',
            'category'     => 'required|string|max:30',
            'description'  => 'nullable|string|max:1000',
            'expense_date' => 'nullable|date',
        ]);

        $expense = Expense::forceCreate([
            ...$validated,
            'recorded_by' => $request->user('admin-api')?->id,
        ]);

        return $this->created($expense, 'Expense created');
    }

    public function updateExpense(Request $request, string $id): JsonResponse
    {
        $expense = Expense::find($id);
        if (! $expense) return $this->notFound('Expense not found');

        $validated = $request->validate([
            'amount'       => 'sometimes|numeric|min:0.01',
            'category'     => 'sometimes|string|max:30',
            'description'  => 'sometimes|nullable|string|max:1000',
            'expense_date' => 'sometimes|nullable|date',
        ]);

        $expense->forceFill($validated)->save();
        return $this->success($expense->fresh(), 'Expense updated');
    }

    public function deleteExpense(string $id): JsonResponse
    {
        $expense = Expense::find($id);
        if (! $expense) return $this->notFound('Expense not found');
        $expense->delete();
        return $this->success(null, 'Expense deleted');
    }

    // ── Gift Cards ───────────────────────────────────────────
    public function giftCards(Request $request): JsonResponse
    {
        $q = GiftCard::query()->latest();

        if ($request->filled('status')) {
            $q->where('status', $request->status);
        }

        return $this->success($q->paginate($request->integer('per_page', 15)), 'Gift cards retrieved');
    }

    public function showGiftCard(string $id): JsonResponse
    {
        $card = GiftCard::find($id);
        if (! $card) return $this->notFound('Gift card not found');
        return $this->success($card, 'Gift card details');
    }

    public function issueGiftCard(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'organization_id' => 'required|uuid',
            'code'            => 'required|string|max:50|unique:gift_cards,code',
            'initial_amount'  => 'required|numeric|min:0.01',
            'recipient_name'  => 'nullable|string|max:255',
            'expires_at'      => 'nullable|date|after:today',
        ]);

        $card = GiftCard::forceCreate([
            ...$validated,
            'balance'         => $validated['initial_amount'],
            'status'          => 'active',
            'issued_by'       => $request->user('admin-api')?->id,
        ]);

        return $this->created($card, 'Gift card issued');
    }

    public function updateGiftCard(Request $request, string $id): JsonResponse
    {
        $card = GiftCard::find($id);
        if (! $card) return $this->notFound('Gift card not found');

        $validated = $request->validate([
            'status'         => 'sometimes|string|in:active,deactivated,expired,redeemed',
            'recipient_name' => 'sometimes|nullable|string|max:255',
            'expires_at'     => 'sometimes|nullable|date',
        ]);

        if (isset($validated['status'])) {
            $validated['status'] = GiftCardStatus::from($validated['status']);
        }

        $card->forceFill($validated)->save();
        return $this->success($card->fresh(), 'Gift card updated');
    }

    public function voidGiftCard(string $id): JsonResponse
    {
        $card = GiftCard::find($id);
        if (! $card) return $this->notFound('Gift card not found');

        $card->forceFill(['status' => GiftCardStatus::Deactivated, 'balance' => 0])->save();
        return $this->success($card->fresh(), 'Gift card voided');
    }

    // ── Gift Card Transactions ───────────────────────────────
    public function giftCardTransactions(Request $request): JsonResponse
    {
        $q = GiftCardTransaction::query()->latest('created_at');

        if ($request->filled('gift_card_id')) {
            $q->where('gift_card_id', $request->gift_card_id);
        }
        if ($request->filled('type')) {
            $q->where('type', $request->type);
        }

        return $this->success($q->paginate($request->integer('per_page', 15)), 'Gift card transactions retrieved');
    }

    public function showGiftCardTransaction(string $id): JsonResponse
    {
        $transaction = GiftCardTransaction::find($id);
        if (! $transaction) return $this->notFound('Gift card transaction not found');
        return $this->success($transaction, 'Gift card transaction details');
    }

    // ── Accounting Configs ───────────────────────────────────
    public function accountingConfigs(Request $request): JsonResponse
    {
        $q = StoreAccountingConfig::query()->latest();

        if ($request->filled('provider')) {
            $q->where('provider', $request->provider);
        }

        return $this->success($q->paginate($request->integer('per_page', 15)), 'Accounting configs retrieved');
    }

    public function showAccountingConfig(string $id): JsonResponse
    {
        $config = StoreAccountingConfig::find($id);
        if (! $config) return $this->notFound('Accounting config not found');
        return $this->success($config, 'Accounting config details');
    }

    public function createAccountingConfig(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'store_id'     => 'required|uuid|unique:store_accounting_configs,store_id',
            'provider'     => 'required|string|max:20',
            'company_name' => 'nullable|string|max:255',
            'realm_id'     => 'nullable|string|max:50',
            'tenant_id'    => 'nullable|string|max:50',
        ]);

        $config = StoreAccountingConfig::forceCreate([
            ...$validated,
            'access_token_encrypted'  => '',
            'refresh_token_encrypted' => '',
            'connected_at' => now(),
        ]);

        return $this->created($config, 'Accounting config created');
    }

    public function updateAccountingConfig(Request $request, string $id): JsonResponse
    {
        $config = StoreAccountingConfig::find($id);
        if (! $config) return $this->notFound('Accounting config not found');

        $validated = $request->validate([
            'provider'     => 'sometimes|string|max:20',
            'company_name' => 'sometimes|nullable|string|max:255',
            'realm_id'     => 'sometimes|nullable|string|max:50',
            'tenant_id'    => 'sometimes|nullable|string|max:50',
        ]);

        $config->forceFill($validated)->save();
        return $this->success($config->fresh(), 'Accounting config updated');
    }

    public function deleteAccountingConfig(string $id): JsonResponse
    {
        $config = StoreAccountingConfig::find($id);
        if (! $config) return $this->notFound('Accounting config not found');
        $config->delete();
        return $this->success(null, 'Accounting config deleted');
    }

    // ── Account Mappings ─────────────────────────────────────
    public function accountMappings(Request $request): JsonResponse
    {
        $q = AccountMapping::query()->latest();

        if ($request->filled('store_id')) {
            $q->where('store_id', $request->store_id);
        }

        return $this->success($q->paginate($request->integer('per_page', 15)), 'Account mappings retrieved');
    }

    public function showAccountMapping(string $id): JsonResponse
    {
        $mapping = AccountMapping::find($id);
        if (! $mapping) return $this->notFound('Account mapping not found');
        return $this->success($mapping, 'Account mapping details');
    }

    public function createAccountMapping(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'store_id'              => 'required|uuid',
            'pos_account_key'       => 'required|string|max:50',
            'provider_account_id'   => 'required|string|max:100',
            'provider_account_name' => 'required|string|max:255',
        ]);

        $mapping = AccountMapping::forceCreate($validated);
        return $this->created($mapping, 'Account mapping created');
    }

    public function updateAccountMapping(Request $request, string $id): JsonResponse
    {
        $mapping = AccountMapping::find($id);
        if (! $mapping) return $this->notFound('Account mapping not found');

        $validated = $request->validate([
            'pos_account_key'       => 'sometimes|string|max:50',
            'provider_account_id'   => 'sometimes|string|max:100',
            'provider_account_name' => 'sometimes|string|max:255',
        ]);

        $mapping->forceFill($validated)->save();
        return $this->success($mapping->fresh(), 'Account mapping updated');
    }

    public function deleteAccountMapping(string $id): JsonResponse
    {
        $mapping = AccountMapping::find($id);
        if (! $mapping) return $this->notFound('Account mapping not found');
        $mapping->delete();
        return $this->success(null, 'Account mapping deleted');
    }

    // ── Accounting Exports ───────────────────────────────────
    public function accountingExports(Request $request): JsonResponse
    {
        $q = AccountingExport::query()->latest();

        if ($request->filled('status')) {
            $q->where('status', $request->status);
        }
        if ($request->filled('store_id')) {
            $q->where('store_id', $request->store_id);
        }

        return $this->success($q->paginate($request->integer('per_page', 15)), 'Accounting exports retrieved');
    }

    public function showAccountingExport(string $id): JsonResponse
    {
        $export = AccountingExport::find($id);
        if (! $export) return $this->notFound('Accounting export not found');
        return $this->success($export, 'Accounting export details');
    }

    public function triggerAccountingExport(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'store_id'     => 'required|uuid',
            'provider'     => 'required|string|max:20',
            'start_date'   => 'required|date',
            'end_date'     => 'required|date|after_or_equal:start_date',
            'export_types' => 'nullable|array',
        ]);

        $export = AccountingExport::forceCreate([
            ...$validated,
            'status'       => AccountingExportStatus::Pending,
            'triggered_by' => ExportTriggeredBy::Manual,
        ]);

        return $this->created($export, 'Accounting export triggered');
    }

    public function retryAccountingExport(string $id): JsonResponse
    {
        $export = AccountingExport::find($id);
        if (! $export) return $this->notFound('Accounting export not found');

        if ($export->status !== AccountingExportStatus::Failed) {
            return $this->error('Only failed exports can be retried', 422);
        }

        $export->forceFill([
            'status'        => AccountingExportStatus::Pending,
            'error_message' => null,
        ])->save();

        return $this->success($export->fresh(), 'Accounting export queued for retry');
    }

    // ── Auto Export Configs ──────────────────────────────────
    public function autoExportConfigs(Request $request): JsonResponse
    {
        $q = AutoExportConfig::query()->latest();

        if ($request->filled('store_id')) {
            $q->where('store_id', $request->store_id);
        }

        return $this->success($q->paginate($request->integer('per_page', 15)), 'Auto export configs retrieved');
    }

    public function showAutoExportConfig(string $id): JsonResponse
    {
        $config = AutoExportConfig::find($id);
        if (! $config) return $this->notFound('Auto export config not found');
        return $this->success($config, 'Auto export config details');
    }

    public function updateAutoExportConfig(Request $request, string $id): JsonResponse
    {
        $config = AutoExportConfig::find($id);
        if (! $config) return $this->notFound('Auto export config not found');

        $validated = $request->validate([
            'enabled'          => 'sometimes|boolean',
            'frequency'        => 'sometimes|string|in:daily,weekly,monthly',
            'day_of_week'      => 'sometimes|nullable|integer|between:0,6',
            'day_of_month'     => 'sometimes|nullable|integer|between:1,31',
            'time'             => 'sometimes|string',
            'export_types'     => 'sometimes|array',
            'notify_email'     => 'sometimes|nullable|email',
            'retry_on_failure' => 'sometimes|boolean',
        ]);

        $config->forceFill($validated)->save();
        return $this->success($config->fresh(), 'Auto export config updated');
    }

    public function createAutoExportConfig(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'store_id'         => 'required|uuid|unique:auto_export_configs,store_id',
            'enabled'          => 'sometimes|boolean',
            'frequency'        => 'sometimes|string|in:daily,weekly,monthly',
            'day_of_week'      => 'nullable|integer|between:0,6',
            'day_of_month'     => 'nullable|integer|between:1,31',
            'time'             => 'sometimes|string',
            'export_types'     => 'nullable|array',
            'notify_email'     => 'nullable|email',
            'retry_on_failure' => 'sometimes|boolean',
        ]);

        $config = AutoExportConfig::forceCreate($validated);
        return $this->created($config, 'Auto export config created');
    }

    public function deleteAutoExportConfig(string $id): JsonResponse
    {
        $config = AutoExportConfig::find($id);
        if (! $config) return $this->notFound('Auto export config not found');
        $config->delete();
        return $this->success(null, 'Auto export config deleted');
    }

    // ── Thawani Settlements ──────────────────────────────────
    public function thawaniSettlements(Request $request): JsonResponse
    {
        $q = ThawaniSettlement::query()->latest();

        if ($request->filled('store_id')) {
            $q->where('store_id', $request->store_id);
        }

        return $this->success($q->paginate($request->integer('per_page', 15)), 'Thawani settlements retrieved');
    }

    public function showThawaniSettlement(string $id): JsonResponse
    {
        $settlement = ThawaniSettlement::find($id);
        if (! $settlement) return $this->notFound('Thawani settlement not found');
        return $this->success($settlement, 'Thawani settlement details');
    }

    public function reconcileThawaniSettlement(Request $request, string $id): JsonResponse
    {
        $settlement = ThawaniSettlement::find($id);
        if (! $settlement) return $this->notFound('Thawani settlement not found');

        $validated = $request->validate([
            'reconciled' => 'required|boolean',
            'notes'      => 'nullable|string|max:500',
        ]);

        $settlement->forceFill([
            'reconciled'    => $validated['reconciled'],
            'reconciled_at' => $validated['reconciled'] ? now() : null,
            'reconciled_by' => $request->user('admin-api')?->id,
        ])->save();

        return $this->success($settlement->fresh(), 'Settlement reconciliation updated');
    }

    // ── Thawani Orders ───────────────────────────────────────
    public function thawaniOrders(Request $request): JsonResponse
    {
        $q = ThawaniOrderMapping::query()->latest();

        if ($request->filled('store_id')) {
            $q->where('store_id', $request->store_id);
        }
        if ($request->filled('status')) {
            $q->where('status', $request->status);
        }

        return $this->success($q->paginate($request->integer('per_page', 15)), 'Thawani orders retrieved');
    }

    public function showThawaniOrder(string $id): JsonResponse
    {
        $order = ThawaniOrderMapping::find($id);
        if (! $order) return $this->notFound('Thawani order not found');
        return $this->success($order, 'Thawani order details');
    }

    // ── Thawani Store Configs ────────────────────────────────
    public function thawaniStoreConfigs(Request $request): JsonResponse
    {
        $q = ThawaniStoreConfig::query()->latest();
        return $this->success($q->paginate($request->integer('per_page', 15)), 'Thawani store configs retrieved');
    }

    public function showThawaniStoreConfig(string $id): JsonResponse
    {
        $config = ThawaniStoreConfig::find($id);
        if (! $config) return $this->notFound('Thawani store config not found');
        return $this->success($config, 'Thawani store config details');
    }

    // ── Daily Sales Summary ──────────────────────────────────
    public function dailySalesSummary(Request $request): JsonResponse
    {
        $q = DailySalesSummary::query()->latest('date');

        if ($request->filled('store_id')) {
            $q->where('store_id', $request->store_id);
        }
        if ($request->filled('date_from')) {
            $q->whereDate('date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $q->whereDate('date', '<=', $request->date_to);
        }

        return $this->success($q->paginate($request->integer('per_page', 15)), 'Daily sales summary retrieved');
    }

    public function showDailySalesSummary(string $id): JsonResponse
    {
        $summary = DailySalesSummary::find($id);
        if (! $summary) return $this->notFound('Daily sales summary not found');
        return $this->success($summary, 'Daily sales summary details');
    }

    // ── Product Sales Summary ────────────────────────────────
    public function productSalesSummary(Request $request): JsonResponse
    {
        $q = ProductSalesSummary::query()->latest('date');

        if ($request->filled('store_id')) {
            $q->where('store_id', $request->store_id);
        }
        if ($request->filled('product_id')) {
            $q->where('product_id', $request->product_id);
        }
        if ($request->filled('date_from')) {
            $q->whereDate('date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $q->whereDate('date', '<=', $request->date_to);
        }

        return $this->success($q->paginate($request->integer('per_page', 15)), 'Product sales summary retrieved');
    }

    public function showProductSalesSummary(string $id): JsonResponse
    {
        $summary = ProductSalesSummary::find($id);
        if (! $summary) return $this->notFound('Product sales summary not found');
        return $this->success($summary, 'Product sales summary details');
    }
}
