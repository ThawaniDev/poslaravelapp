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
}
