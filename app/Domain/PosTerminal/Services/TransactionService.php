<?php

namespace App\Domain\PosTerminal\Services;

use App\Domain\Auth\Models\User;
use App\Domain\Core\Models\StoreSettings;
use App\Domain\Customer\Enums\LoyaltyTransactionType;
use App\Domain\Customer\Models\Customer;
use App\Domain\Customer\Models\LoyaltyConfig;
use App\Domain\Customer\Services\LoyaltyService;
use App\Domain\Inventory\Enums\StockMovementType;
use App\Domain\Inventory\Enums\StockReferenceType;
use App\Domain\Inventory\Models\StockLevel;
use App\Domain\Inventory\Models\StockMovement;
use App\Domain\Inventory\Services\RecipeService;
use App\Domain\Payment\Models\Payment;
use App\Domain\PosTerminal\Enums\TransactionStatus;
use App\Domain\PosTerminal\Enums\TransactionType;
use App\Domain\PosTerminal\Models\Transaction;
use App\Domain\PosTerminal\Models\TransactionItem;
use App\Domain\PosTerminal\Models\PosSession;
use App\Domain\Report\Models\DailySalesSummary;
use App\Domain\Report\Models\ProductSalesSummary;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class TransactionService
{
    public function __construct(
        private readonly RecipeService $recipeService,
    ) {}

    public function list(string $storeId, array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = Transaction::where('store_id', $storeId);

        if (!empty($filters['session_id'])) {
            $query->where('pos_session_id', $filters['session_id']);
        }

        if (!empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['search'])) {
            $escaped = str_replace(['%', '_'], ['\%', '\_'], $filters['search']);
            $query->where('transaction_number', 'like', "%{$escaped}%");
        }

        return $query->orderByDesc('created_at')->paginate($perPage);
    }

    public function find(string $storeId, string $transactionId): Transaction
    {
        return Transaction::with(['transactionItems', 'payments', 'returns.transactionItems'])
            ->where('store_id', $storeId)
            ->findOrFail($transactionId);
    }

    public function findByNumber(string $storeId, string $number): Transaction
    {
        return Transaction::with(['transactionItems', 'payments', 'returns.transactionItems'])
            ->where('store_id', $storeId)
            ->where('transaction_number', $number)
            ->firstOrFail();
    }

    public function create(array $data, User $actor): Transaction
    {
        return DB::transaction(function () use ($data, $actor) {
            // Load store settings for enforcement
            $settings = StoreSettings::where('store_id', $actor->store_id)->first();

            // Enforce: require_customer_for_sale
            $isSale = ($data['type'] ?? TransactionType::Sale->value) === TransactionType::Sale->value;
            if ($settings && $isSale && $settings->require_customer_for_sale && empty($data['customer_id'])) {
                throw new \RuntimeException(__('pos.customer_required_for_sale'));
            }

            // Enforce: max_discount_percent
            if ($settings && $settings->max_discount_percent !== null && $settings->max_discount_percent > 0) {
                $maxPct = (float) $settings->max_discount_percent;
                $subtotal = (float) ($data['subtotal'] ?? 0);
                $discountAmount = (float) ($data['discount_amount'] ?? 0);
                if ($subtotal > 0 && $discountAmount > 0) {
                    $discountPct = ($discountAmount / $subtotal) * 100;
                    if ($discountPct > $maxPct) {
                        throw new \RuntimeException(__('pos.discount_exceeds_maximum', ['max' => $maxPct]));
                    }
                }
                // Also validate per-item discounts
                foreach ($data['items'] ?? [] as $item) {
                    $itemTotal = (float) ($item['unit_price'] ?? 0) * (float) ($item['quantity'] ?? 1);
                    $itemDiscount = (float) ($item['discount_amount'] ?? 0);
                    if ($itemTotal > 0 && $itemDiscount > 0) {
                        $itemDiscPct = ($itemDiscount / $itemTotal) * 100;
                        if ($itemDiscPct > $maxPct) {
                            throw new \RuntimeException(__('pos.discount_exceeds_maximum', ['max' => $maxPct]));
                        }
                    }
                }
            }

            // Determine store tax_rate from settings (use as default when client doesn't provide)
            $storeTaxRate = $settings ? (float) $settings->tax_rate : 0;

            // Resolve register_id from session if not provided
            $registerId = $data['register_id'] ?? null;
            $sessionId = $data['pos_session_id'] ?? null;
            if (! $registerId && $sessionId) {
                $session = PosSession::find($sessionId);
                $registerId = $session?->register_id;
            }

            $transaction = Transaction::create([
                'organization_id' => $actor->organization_id,
                'store_id' => $actor->store_id,
                'register_id' => $registerId,
                'pos_session_id' => $sessionId,
                'cashier_id' => $actor->id,
                'customer_id' => $data['customer_id'] ?? null,
                'transaction_number' => $data['transaction_number'] ?? $this->generateNumber($actor->store_id),
                'type' => $data['type'] ?? TransactionType::Sale->value,
                'status' => $data['status'] ?? TransactionStatus::Completed->value,
                'subtotal' => $data['subtotal'] ?? 0,
                'discount_amount' => $data['discount_amount'] ?? 0,
                'tax_amount' => $data['tax_amount'] ?? 0,
                'tip_amount' => $data['tip_amount'] ?? 0,
                'total_amount' => $data['total_amount'] ?? 0,
                'is_tax_exempt' => $data['is_tax_exempt'] ?? false,
                'return_transaction_id' => $data['return_transaction_id'] ?? null,
                'notes' => $data['notes'] ?? null,
                'sync_version' => 1,
            ]);

            // Create transaction items
            if (!empty($data['items'])) {
                foreach ($data['items'] as $item) {
                    TransactionItem::create([
                        'transaction_id' => $transaction->id,
                        'product_id' => $item['product_id'] ?? null,
                        'barcode' => $item['barcode'] ?? null,
                        'product_name' => $item['product_name'],
                        'product_name_ar' => $item['product_name_ar'] ?? null,
                        'quantity' => $item['quantity'],
                        'unit_price' => $item['unit_price'],
                        'cost_price' => $item['cost_price'] ?? 0,
                        'discount_amount' => $item['discount_amount'] ?? 0,
                        'tax_rate' => $item['tax_rate'] ?? $storeTaxRate,
                        'tax_amount' => $item['tax_amount'] ?? 0,
                        'line_total' => $item['line_total'],
                        'is_return_item' => $item['is_return_item'] ?? false,
                    ]);
                }
            }

            // Create payment records
            if (!empty($data['payments'])) {
                foreach ($data['payments'] as $payment) {
                    $paymentData = [
                        'transaction_id' => $transaction->id,
                        'method' => $payment['method'],
                        'amount' => $payment['amount'],
                        'tip_amount' => $payment['tip_amount'] ?? 0,
                        'loyalty_points_used' => $payment['loyalty_points_used'] ?? 0,
                    ];

                    // Only set nullable fields if provided
                    foreach (['cash_tendered', 'change_given', 'card_brand', 'card_last_four', 'card_auth_code', 'card_reference', 'gift_card_code', 'coupon_code'] as $field) {
                        if (!empty($payment[$field])) {
                            $paymentData[$field] = $payment[$field];
                        }
                    }

                    Payment::create($paymentData);
                }
            }

            // Update session counters if session exists
            if ($transaction->pos_session_id) {
                $session = $transaction->posSession;
                if ($session) {
                    $session->increment('transaction_count');

                    $type = $transaction->type instanceof TransactionType
                        ? $transaction->type
                        : TransactionType::from($transaction->type);

                    if ($type === TransactionType::Sale || $type === TransactionType::Exchange) {
                        $this->updateSessionSales($session, $data['payments'] ?? []);
                    } elseif ($type === TransactionType::Return) {
                        // Track each payment method separately so the cash drawer
                        // reconciliation at close stays correct regardless of how
                        // the refund was settled (cash vs card vs other).
                        $this->updateSessionRefunds($session, $data['payments'] ?? []);
                        $session->increment('total_refunds', (float) $transaction->total_amount);
                    }
                }
            }

            // Update sales summary tables for reports/dashboard
            $this->updateSalesSummaries($transaction);

            // Update inventory (stock_levels + stock_movements)
            $this->updateInventory($transaction);

            // Update customer stats (total_spend, visit_count, last_visit_at)
            $this->updateCustomerStats($transaction);

            // Auto-earn loyalty points for sale transactions with a customer
            if ($isSale && $transaction->customer_id) {
                $this->earnLoyaltyPoints($transaction, $actor);
            }

            // If the customer paid with loyalty_points, redeem them now.
            // The Flutter dialog supplies `loyalty_points_used` on the payment leg
            // converted to SAR via LoyaltyConfig.points_per_sar; we just need to
            // burn the equivalent point balance and write a `redeem` ledger entry.
            if ($isSale && $transaction->customer_id) {
                $this->redeemLoyaltyPoints($transaction, $data['payments'] ?? [], $actor);
            }

            // Deduct loyalty points when a refund is issued against a sale that
            // had a customer attached, so the customer cannot keep points they
            // earned on the refunded value.
            $isReturn = $transaction->type === TransactionType::Return
                || $transaction->type === TransactionType::Return->value;
            if ($isReturn && $transaction->customer_id) {
                $this->refundLoyaltyPoints($transaction, $actor);
            }

            return $transaction->load(['transactionItems', 'payments']);
        });
    }

    public function createReturn(array $data, User $actor): Transaction
    {
        // Enforce: enable_refunds setting
        $settings = StoreSettings::where('store_id', $actor->store_id)->first();
        if ($settings && !$settings->enable_refunds) {
            throw new \RuntimeException(__('pos.refunds_disabled'));
        }

        $originalTransaction = $this->find($actor->store_id, $data['return_transaction_id']);

        if ($originalTransaction->status !== TransactionStatus::Completed) {
            throw new \RuntimeException(__('pos.return_original_not_completed'));
        }

        if ($originalTransaction->type !== TransactionType::Sale) {
            throw new \RuntimeException(__('pos.return_only_sales'));
        }

        // Cap the total refundable quantity per product_id to what was
        // originally sold, minus what has already been refunded on prior
        // returns. Prevents unlimited refunds against the same sale.
        $this->assertReturnQuantitiesAvailable($originalTransaction, $data['items'] ?? []);

        $data['type'] = TransactionType::Return->value;
        $data['return_transaction_id'] = $originalTransaction->id;

        // Inherit register/session from original sale if not explicitly provided,
        // so the return is anchored to the same physical till. register_id is
        // NOT NULL in the schema; without this fallback a refund initiated outside
        // an active session (e.g. from the receipt lookup dialog) would crash with
        // a not-null violation.
        if (empty($data['register_id'])) {
            $data['register_id'] = $originalTransaction->register_id;
        }
        if (empty($data['pos_session_id'])) {
            $data['pos_session_id'] = $originalTransaction->pos_session_id;
        }

        // Inherit customer from the original sale so that customer_stats,
        // loyalty points and customer_lifetime_value are reversed consistently.
        if (empty($data['customer_id']) && $originalTransaction->customer_id) {
            $data['customer_id'] = $originalTransaction->customer_id;
        }

        return $this->create($data, $actor);
    }

    /**
     * Cap the combined refunded quantity per product to what was originally
     * sold on `$original`. Looks at every non-voided prior return that
     * references this sale and subtracts their quantities from the original
     * line quantities. Throws before any write if the requested return would
     * exceed the remaining refundable quantity.
     */
    private function assertReturnQuantitiesAvailable(Transaction $original, array $requestedItems): void
    {
        // Original sold quantities, keyed by product_id.
        $soldByProduct = [];
        foreach ($original->transactionItems as $row) {
            $pid = $row->product_id;
            if (!$pid) continue;
            $soldByProduct[$pid] = ($soldByProduct[$pid] ?? 0) + (float) $row->quantity;
        }

        // Already-refunded quantities across prior non-voided returns.
        $priorReturns = Transaction::where('return_transaction_id', $original->id)
            ->where('type', TransactionType::Return)
            ->where('status', '!=', TransactionStatus::Voided)
            ->with('transactionItems')
            ->get();

        $refundedByProduct = [];
        foreach ($priorReturns as $ret) {
            foreach ($ret->transactionItems as $row) {
                $pid = $row->product_id;
                if (!$pid) continue;
                $refundedByProduct[$pid] = ($refundedByProduct[$pid] ?? 0) + (float) $row->quantity;
            }
        }

        // Short-circuit: every sold unit is already refunded.
        $totalSold = array_sum($soldByProduct);
        $totalRefunded = array_sum($refundedByProduct);
        if ($totalSold > 0 && $totalRefunded >= $totalSold) {
            throw new \RuntimeException(__('pos.return_already_fully_refunded'));
        }

        // Per-product check of the incoming request.
        $requestedByProduct = [];
        $nameByProduct = [];
        foreach ($requestedItems as $item) {
            $pid = $item['product_id'] ?? null;
            $qty = (float) ($item['quantity'] ?? 0);
            if (!$pid || $qty <= 0) continue;
            $requestedByProduct[$pid] = ($requestedByProduct[$pid] ?? 0) + $qty;
            $nameByProduct[$pid] = $item['product_name'] ?? $pid;
        }

        foreach ($requestedByProduct as $pid => $qty) {
            $sold = $soldByProduct[$pid] ?? 0;
            $refunded = $refundedByProduct[$pid] ?? 0;
            $remaining = $sold - $refunded;
            if ($qty > $remaining) {
                throw new \RuntimeException(__('pos.return_quantity_exceeds_remaining', [
                    'product' => $nameByProduct[$pid] ?? $pid,
                    'requested' => rtrim(rtrim(number_format($qty, 2, '.', ''), '0'), '.'),
                    'remaining' => rtrim(rtrim(number_format(max(0, $remaining), 2, '.', ''), '0'), '.'),
                ]));
            }
        }
    }

    public function void(Transaction $transaction, User $actor): Transaction
    {
        if ($transaction->status === TransactionStatus::Voided) {
            throw new \RuntimeException(__('pos.transaction_already_voided'));
        }

        if ($transaction->status !== TransactionStatus::Completed) {
            throw new \RuntimeException(__('pos.only_completed_can_void'));
        }

        $transaction->update([
            'status' => TransactionStatus::Voided,
            'sync_version' => ($transaction->sync_version ?? 0) + 1,
        ]);

        // Reverse the sales summaries for the voided transaction
        $this->reverseSalesSummaries($transaction);

        // Reverse inventory changes
        $this->reverseInventory($transaction);

        // Reverse customer stats
        $this->reverseCustomerStats($transaction);

        // Reverse loyalty points earned on the voided transaction
        $this->reverseLoyaltyPoints($transaction, $actor);

        // Update session counters
        if ($transaction->pos_session_id) {
            $session = $transaction->posSession;
            if ($session) {
                $session->increment('total_voids', (float) $transaction->total_amount);
            }
        }

        return $transaction->fresh(['transactionItems', 'payments']);
    }

    private function updateSessionSales($session, array $payments): void
    {
        foreach ($payments as $payment) {
            $amount = (float) ($payment['amount'] ?? 0);
            $method = $payment['method'] ?? 'cash';

            if ($method === 'cash') {
                $session->increment('total_cash_sales', $amount);
            } elseif (str_starts_with($method, 'card') || $method === 'mada' || $method === 'apple_pay') {
                $session->increment('total_card_sales', $amount);
            } else {
                $session->increment('total_other_sales', $amount);
            }
        }
    }

    /**
     * Deduct refund amounts per payment method so the session's cash/card/other
     * counters stay net-of-refunds. This keeps the close-session
     * `expected_cash = opening_cash + total_cash_sales` reconciliation correct
     * regardless of which method the refund was issued to.
     */
    private function updateSessionRefunds($session, array $payments): void
    {
        foreach ($payments as $payment) {
            $amount = (float) ($payment['amount'] ?? 0);
            $method = $payment['method'] ?? 'cash';

            if ($method === 'cash') {
                $session->decrement('total_cash_sales', $amount);
            } elseif (str_starts_with($method, 'card') || $method === 'mada' || $method === 'apple_pay') {
                $session->decrement('total_card_sales', $amount);
            } else {
                $session->decrement('total_other_sales', $amount);
            }
        }
    }

    /**
     * Update daily_sales_summary and product_sales_summary after a transaction is created.
     */
    private function updateSalesSummaries(Transaction $transaction): void
    {
        $date = $transaction->created_at->toDateString();
        $storeId = $transaction->store_id;
        $isReturn = $transaction->type === TransactionType::Return
            || $transaction->type === TransactionType::Return->value;

        // Determine payment breakdown
        $cashRevenue = 0;
        $cardRevenue = 0;
        $otherRevenue = 0;
        foreach ($transaction->payments as $payment) {
            $amount = (float) $payment->amount;
            $method = $payment->method;
            if ($method === 'cash') {
                $cashRevenue += $amount;
            } elseif (in_array($method, ['card', 'mada', 'apple_pay', 'visa', 'mastercard'])) {
                $cardRevenue += $amount;
            } else {
                $otherRevenue += $amount;
            }
        }

        $totalAmount = (float) $transaction->total_amount;
        $discountAmount = (float) $transaction->discount_amount;
        $taxAmount = (float) $transaction->tax_amount;
        $totalCost = $transaction->transactionItems->sum(fn ($item) => (float) $item->cost_price * (float) $item->quantity);

        // Upsert daily_sales_summary atomically using firstOrCreate + parameterized update
        $customerIncrement = $transaction->customer_id ? 1 : 0;

        $summary = DailySalesSummary::firstOrCreate(
            ['store_id' => $storeId, 'date' => $date],
            [
                'total_transactions' => 0, 'total_revenue' => 0, 'total_cost' => 0,
                'total_discount' => 0, 'total_tax' => 0, 'total_refunds' => 0,
                'net_revenue' => 0, 'cash_revenue' => 0, 'card_revenue' => 0,
                'other_revenue' => 0, 'avg_basket_size' => 0, 'unique_customers' => 0,
            ]
        );

        if ($isReturn) {
            DB::update(
                'UPDATE daily_sales_summary SET total_refunds = total_refunds + ?, net_revenue = net_revenue - ? WHERE id = ?',
                [$totalAmount, $totalAmount, $summary->id]
            );
        } else {
            DB::update(
                'UPDATE daily_sales_summary SET '
                . 'total_transactions = total_transactions + 1, '
                . 'total_revenue = total_revenue + ?, '
                . 'total_cost = total_cost + ?, '
                . 'total_discount = total_discount + ?, '
                . 'total_tax = total_tax + ?, '
                . 'net_revenue = net_revenue + ?, '
                . 'cash_revenue = cash_revenue + ?, '
                . 'card_revenue = card_revenue + ?, '
                . 'other_revenue = other_revenue + ?, '
                . 'unique_customers = unique_customers + ?, '
                . 'avg_basket_size = CASE WHEN total_transactions + 1 > 0 THEN ROUND((total_revenue + ?) / (total_transactions + 1), 2) ELSE 0 END '
                . 'WHERE id = ?',
                [$totalAmount, $totalCost, $discountAmount, $taxAmount, $totalAmount, $cashRevenue, $cardRevenue, $otherRevenue, $customerIncrement, $totalAmount, $summary->id]
            );
        }

        // Upsert product_sales_summary atomically for each item
        foreach ($transaction->transactionItems as $item) {
            if (! $item->product_id) {
                continue;
            }

            $qty = (float) $item->quantity;
            $revenue = (float) $item->line_total;
            $cost = (float) $item->cost_price * $qty;
            $discount = (float) $item->discount_amount;
            $tax = (float) $item->tax_amount;

            // Use firstOrCreate + atomic update to avoid race conditions
            $productSummary = ProductSalesSummary::firstOrCreate(
                ['store_id' => $storeId, 'product_id' => $item->product_id, 'date' => $date],
                ['quantity_sold' => 0, 'revenue' => 0, 'cost' => 0, 'discount_amount' => 0, 'tax_amount' => 0, 'return_quantity' => 0, 'return_amount' => 0]
            );

            if ($isReturn || $item->is_return_item) {
                DB::update(
                    'UPDATE product_sales_summary SET return_quantity = return_quantity + ?, return_amount = return_amount + ? WHERE id = ?',
                    [$qty, $revenue, $productSummary->id]
                );
            } else {
                DB::update(
                    'UPDATE product_sales_summary SET quantity_sold = quantity_sold + ?, revenue = revenue + ?, cost = cost + ?, discount_amount = discount_amount + ?, tax_amount = tax_amount + ? WHERE id = ?',
                    [$qty, $revenue, $cost, $discount, $tax, $productSummary->id]
                );
            }
        }
    }

    /**
     * Reverse sales summaries when a transaction is voided.
     */
    private function reverseSalesSummaries(Transaction $transaction): void
    {
        $date = $transaction->created_at->toDateString();
        $storeId = $transaction->store_id;
        $isReturn = $transaction->type === TransactionType::Return
            || $transaction->type === TransactionType::Return->value;

        // Determine payment breakdown
        $cashRevenue = 0;
        $cardRevenue = 0;
        $otherRevenue = 0;
        foreach ($transaction->payments as $payment) {
            $amount = (float) $payment->amount;
            $method = $payment->method;
            if ($method === 'cash') {
                $cashRevenue += $amount;
            } elseif (in_array($method, ['card', 'mada', 'apple_pay', 'visa', 'mastercard'])) {
                $cardRevenue += $amount;
            } else {
                $otherRevenue += $amount;
            }
        }

        $totalAmount = (float) $transaction->total_amount;
        $discountAmount = (float) $transaction->discount_amount;
        $taxAmount = (float) $transaction->tax_amount;
        $totalCost = $transaction->transactionItems->sum(fn ($item) => (float) $item->cost_price * (float) $item->quantity);

        $existing = DailySalesSummary::where('store_id', $storeId)
            ->whereDate('date', $date)
            ->first();

        if ($existing) {
            $customerDecrement = $transaction->customer_id ? 1 : 0;

            if ($isReturn) {
                DB::update(
                    'UPDATE daily_sales_summary SET total_refunds = GREATEST(0, total_refunds - ?), net_revenue = net_revenue + ? WHERE id = ?',
                    [$totalAmount, $totalAmount, $existing->id]
                );
            } else {
                DB::update(
                    'UPDATE daily_sales_summary SET '
                    . 'total_transactions = GREATEST(0, total_transactions - 1), '
                    . 'total_revenue = GREATEST(0, total_revenue - ?), '
                    . 'total_cost = GREATEST(0, total_cost - ?), '
                    . 'total_discount = GREATEST(0, total_discount - ?), '
                    . 'total_tax = GREATEST(0, total_tax - ?), '
                    . 'net_revenue = net_revenue - ?, '
                    . 'cash_revenue = GREATEST(0, cash_revenue - ?), '
                    . 'card_revenue = GREATEST(0, card_revenue - ?), '
                    . 'other_revenue = GREATEST(0, other_revenue - ?), '
                    . 'unique_customers = GREATEST(0, unique_customers - ?), '
                    . 'avg_basket_size = CASE WHEN GREATEST(0, total_transactions - 1) > 0 THEN ROUND(GREATEST(0, total_revenue - ?) / GREATEST(1, total_transactions - 1), 2) ELSE 0 END '
                    . 'WHERE id = ?',
                    [$totalAmount, $totalCost, $discountAmount, $taxAmount, $totalAmount, $cashRevenue, $cardRevenue, $otherRevenue, $customerDecrement, $totalAmount, $existing->id]
                );
            }
        }

        // Reverse product summaries
        foreach ($transaction->transactionItems as $item) {
            if (! $item->product_id) {
                continue;
            }

            $existingProduct = ProductSalesSummary::where('store_id', $storeId)
                ->where('product_id', $item->product_id)
                ->whereDate('date', $date)
                ->first();

            if (! $existingProduct) {
                continue;
            }

            $qty = (float) $item->quantity;
            $revenue = (float) $item->line_total;
            $cost = (float) $item->cost_price * $qty;
            $discount = (float) $item->discount_amount;
            $tax = (float) $item->tax_amount;

            if ($isReturn || $item->is_return_item) {
                DB::update(
                    'UPDATE product_sales_summary SET return_quantity = GREATEST(0, return_quantity - ?), return_amount = GREATEST(0, return_amount - ?) WHERE id = ?',
                    [$qty, $revenue, $existingProduct->id]
                );
            } else {
                DB::update(
                    'UPDATE product_sales_summary SET quantity_sold = GREATEST(0, quantity_sold - ?), revenue = GREATEST(0, revenue - ?), cost = GREATEST(0, cost - ?), discount_amount = GREATEST(0, discount_amount - ?), tax_amount = GREATEST(0, tax_amount - ?) WHERE id = ?',
                    [$qty, $revenue, $cost, $discount, $tax, $existingProduct->id]
                );
            }
        }
    }

    /**
     * Update stock_levels and create stock_movements for each item in the transaction.
     */
    private function updateInventory(Transaction $transaction): void
    {
        $storeId = $transaction->store_id;
        $isReturn = $transaction->type === TransactionType::Return
            || $transaction->type === TransactionType::Return->value;

        // Load settings for allow_negative_stock and track_inventory checks
        $settings = StoreSettings::where('store_id', $storeId)->first();

        // If inventory tracking is disabled, skip all stock operations
        if ($settings && !$settings->track_inventory) {
            return;
        }

        foreach ($transaction->transactionItems as $item) {
            if (! $item->product_id) {
                continue;
            }

            $qty = (float) $item->quantity;

            // Sale = decrement stock, Return = increment stock
            if ($isReturn || $item->is_return_item) {
                $movementType = StockMovementType::AdjustmentIn;
                $stockChange = $qty;
                $reason = 'POS return - ' . $transaction->transaction_number;
            } else {
                $movementType = StockMovementType::Sale;
                $stockChange = -$qty;
                $reason = 'POS sale - ' . $transaction->transaction_number;
            }

            // Update stock_levels with row lock to prevent race conditions
            $stockLevel = StockLevel::where('store_id', $storeId)
                ->where('product_id', $item->product_id)
                ->lockForUpdate()
                ->first();

            if ($stockLevel) {
                $newQuantity = (float) $stockLevel->quantity + $stockChange;
                // Available = on-hand minus stock reserved for in-transit transfers.
                $newAvailable = $newQuantity - (float) ($stockLevel->reserved_quantity ?? 0);

                // Enforce: allow_negative_stock — prevent available stock going below zero on a sale.
                if ($settings && !$settings->allow_negative_stock && $newAvailable < 0 && !$isReturn) {
                    throw new \RuntimeException(
                        __('pos.insufficient_stock', [
                            'product' => $item->product_name,
                            'available' => $stockLevel->quantity - (float) ($stockLevel->reserved_quantity ?? 0),
                            'requested' => $qty,
                        ])
                    );
                }

                $stockLevel->quantity = $newQuantity;
                $stockLevel->sync_version = ($stockLevel->sync_version ?? 0) + 1;
                $stockLevel->save();
            }

            // Create stock_movement audit record
            StockMovement::create([
                'store_id' => $storeId,
                'product_id' => $item->product_id,
                'type' => $movementType->value,
                'quantity' => $qty,
                'unit_cost' => $item->cost_price,
                'reference_type' => StockReferenceType::Transaction->value,
                'reference_id' => $transaction->id,
                'reason' => $reason,
                'performed_by' => $transaction->cashier_id,
            ]);

            // Cascade recipe ingredient deduction / restoration when the
            // sold/returned product has an active recipe. Without this, an
            // organization selling composite products (e.g. a burger combo)
            // would never see flour/cheese/tomato stock decrease.
            $product = $item->product()->first();
            $organizationId = $product?->organization_id ?? optional($transaction->store)->organization_id;
            if ($organizationId) {
                $recipe = $this->recipeService->findByProductId($item->product_id, $organizationId);
                if ($recipe) {
                    $idempotencyKey = substr(hash('sha256', $transaction->id . ':' . $item->id), 0, 64);
                    if ($isReturn || $item->is_return_item) {
                        $this->recipeService->reverseIngredients(
                            recipeId: $recipe->id,
                            storeId: $storeId,
                            quantitySold: $qty,
                            performedBy: $transaction->cashier_id,
                            referenceType: StockReferenceType::Transaction,
                            referenceId: $transaction->id,
                            idempotencyKey: $idempotencyKey,
                        );
                    } else {
                        $this->recipeService->deductIngredients(
                            recipeId: $recipe->id,
                            storeId: $storeId,
                            quantitySold: $qty,
                            performedBy: $transaction->cashier_id,
                            referenceType: StockReferenceType::Transaction,
                            referenceId: $transaction->id,
                            idempotencyKey: $idempotencyKey,
                        );
                    }
                }
            }
        }
    }

    /**
     * Reverse inventory changes when a transaction is voided.
     */
    private function reverseInventory(Transaction $transaction): void
    {
        $storeId = $transaction->store_id;
        $isReturn = $transaction->type === TransactionType::Return
            || $transaction->type === TransactionType::Return->value;

        foreach ($transaction->transactionItems as $item) {
            if (! $item->product_id) {
                continue;
            }

            $qty = (float) $item->quantity;

            // Reverse: voiding a sale = add stock back, voiding a return = remove stock
            if ($isReturn || $item->is_return_item) {
                $stockChange = -$qty;
                $reason = 'Void return - ' . $transaction->transaction_number;
            } else {
                $stockChange = $qty;
                $reason = 'Void sale - ' . $transaction->transaction_number;
            }

            $stockLevel = StockLevel::where('store_id', $storeId)
                ->where('product_id', $item->product_id)
                ->first();

            if ($stockLevel) {
                $stockLevel->quantity = (float) $stockLevel->quantity + $stockChange;
                $stockLevel->sync_version = ($stockLevel->sync_version ?? 0) + 1;
                $stockLevel->save();
            }

            // Create reversal stock_movement
            StockMovement::create([
                'store_id' => $storeId,
                'product_id' => $item->product_id,
                'type' => $stockChange > 0
                    ? StockMovementType::AdjustmentIn->value
                    : StockMovementType::AdjustmentOut->value,
                'quantity' => $qty,
                'unit_cost' => $item->cost_price,
                'reference_type' => StockReferenceType::Transaction->value,
                'reference_id' => $transaction->id,
                'reason' => $reason,
                'performed_by' => $transaction->cashier_id,
            ]);

            // Mirror the recipe-ingredient cascade: voiding a sale restores
            // ingredients, voiding a return re-deducts them. Idempotency key
            // is distinct from the original sale's key so the void writes
            // its own movements.
            $product = $item->product()->first();
            $organizationId = $product?->organization_id ?? optional($transaction->store)->organization_id;
            if ($organizationId) {
                $recipe = $this->recipeService->findByProductId($item->product_id, $organizationId);
                if ($recipe) {
                    $idempotencyKey = substr(hash('sha256', $transaction->id . ':void:' . $item->id), 0, 64);
                    if ($isReturn || $item->is_return_item) {
                        // Voiding a return = re-deduct ingredients.
                        $this->recipeService->deductIngredients(
                            recipeId: $recipe->id,
                            storeId: $storeId,
                            quantitySold: $qty,
                            performedBy: $transaction->cashier_id,
                            referenceType: StockReferenceType::Transaction,
                            referenceId: $transaction->id,
                            idempotencyKey: $idempotencyKey,
                        );
                    } else {
                        // Voiding a sale = restore ingredients.
                        $this->recipeService->reverseIngredients(
                            recipeId: $recipe->id,
                            storeId: $storeId,
                            quantitySold: $qty,
                            performedBy: $transaction->cashier_id,
                            referenceType: StockReferenceType::Transaction,
                            referenceId: $transaction->id,
                            idempotencyKey: $idempotencyKey,
                        );
                    }
                }
            }
        }
    }

    /**
     * Update customer total_spend, visit_count, last_visit_at after a transaction.
     */
    private function updateCustomerStats(Transaction $transaction): void
    {
        if (! $transaction->customer_id) {
            return;
        }

        $customer = Customer::find($transaction->customer_id);
        if (! $customer) {
            return;
        }

        $isReturn = $transaction->type === TransactionType::Return
            || $transaction->type === TransactionType::Return->value;

        $totalAmount = (float) $transaction->total_amount;

        if ($isReturn) {
            $customer->total_spend = max(0, (float) $customer->total_spend - $totalAmount);
        } else {
            $customer->total_spend = (float) $customer->total_spend + $totalAmount;
            $customer->visit_count = ($customer->visit_count ?? 0) + 1;
            $customer->last_visit_at = $transaction->created_at;
        }

        $customer->save();
    }

    /**
     * Reverse customer stats when a transaction is voided.
     */
    private function reverseCustomerStats(Transaction $transaction): void
    {
        if (! $transaction->customer_id) {
            return;
        }

        $customer = Customer::find($transaction->customer_id);
        if (! $customer) {
            return;
        }

        $isReturn = $transaction->type === TransactionType::Return
            || $transaction->type === TransactionType::Return->value;

        $totalAmount = (float) $transaction->total_amount;

        if ($isReturn) {
            // Voiding a return = add spend back
            $customer->total_spend = (float) $customer->total_spend + $totalAmount;
        } else {
            // Voiding a sale = subtract spend, decrement visit
            $customer->total_spend = max(0, (float) $customer->total_spend - $totalAmount);
            $customer->visit_count = max(0, ($customer->visit_count ?? 0) - 1);
        }

        $customer->save();
    }

    /**
     * Auto-earn loyalty points based on LoyaltyConfig for the organization.
     */
    private function earnLoyaltyPoints(Transaction $transaction, User $actor): void
    {
        $config = LoyaltyConfig::where('organization_id', $transaction->organization_id)->first();
        if (! $config || ! $config->is_active || $config->points_per_sar <= 0) {
            return;
        }

        $totalAmount = (float) $transaction->total_amount;
        $earnedPoints = (int) floor($totalAmount * $config->points_per_sar);
        if ($earnedPoints <= 0) {
            return;
        }

        try {
            app(LoyaltyService::class)->adjustPoints(
                customerId: $transaction->customer_id,
                points: $earnedPoints,
                type: LoyaltyTransactionType::Earn->value,
                actor: $actor,
                notes: 'Auto-earned from sale ' . $transaction->transaction_number,
                orderId: $transaction->id,
            );
        } catch (\Throwable $e) {
            // Loyalty earning failure should not block the transaction
            \Illuminate\Support\Facades\Log::warning('Loyalty points auto-earn failed', [
                'transaction_id' => $transaction->id,
                'customer_id' => $transaction->customer_id,
                'points' => $earnedPoints,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Burn loyalty points whenever a payment leg is settled with the
     * `loyalty_points` method. Each payment carries `loyalty_points_used`
     * so we know exactly how many to deduct, and we also write a `redeem`
     * row in loyalty_transactions for audit.
     */
    private function redeemLoyaltyPoints(Transaction $transaction, array $payments, User $actor): void
    {
        $totalRedeemed = 0;
        foreach ($payments as $payment) {
            if (($payment['method'] ?? null) !== 'loyalty_points') {
                continue;
            }
            $totalRedeemed += (int) ($payment['loyalty_points_used'] ?? 0);
        }
        if ($totalRedeemed <= 0) {
            return;
        }

        try {
            app(LoyaltyService::class)->adjustPoints(
                customerId: $transaction->customer_id,
                points: -$totalRedeemed,
                type: LoyaltyTransactionType::Redeem->value,
                actor: $actor,
                notes: 'Redeemed at POS - ' . $transaction->transaction_number,
                orderId: $transaction->id,
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Loyalty points redemption failed', [
                'transaction_id' => $transaction->id,
                'customer_id' => $transaction->customer_id,
                'points' => $totalRedeemed,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Reverse loyalty points when a transaction is voided.
     */
    private function reverseLoyaltyPoints(Transaction $transaction, User $actor): void
    {
        if (! $transaction->customer_id) {
            return;
        }

        $config = LoyaltyConfig::where('organization_id', $transaction->organization_id)->first();
        if (! $config || ! $config->is_active || $config->points_per_sar <= 0) {
            return;
        }

        $totalAmount = (float) $transaction->total_amount;
        $isReturn = $transaction->type === TransactionType::Return
            || $transaction->type === TransactionType::Return->value;

        if ($isReturn) {
            // Voiding a refund should restore the points we deducted in
            // refundLoyaltyPoints so the customer's balance snaps back.
            $pointsToRestore = (int) floor($totalAmount * $config->points_per_sar);
            if ($pointsToRestore <= 0) {
                return;
            }

            try {
                app(LoyaltyService::class)->adjustPoints(
                    customerId: $transaction->customer_id,
                    points: $pointsToRestore,
                    type: LoyaltyTransactionType::VoidReversal->value,
                    actor: $actor,
                    notes: 'Restored - voided refund ' . $transaction->transaction_number,
                    orderId: $transaction->id,
                );
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Loyalty refund void restore failed', [
                    'transaction_id' => $transaction->id,
                    'customer_id' => $transaction->customer_id,
                    'points' => $pointsToRestore,
                    'error' => $e->getMessage(),
                ]);
            }
            return;
        }

        $earnedPoints = (int) floor($totalAmount * $config->points_per_sar);
        if ($earnedPoints <= 0) {
            return;
        }

        try {
            app(LoyaltyService::class)->adjustPoints(
                customerId: $transaction->customer_id,
                points: -$earnedPoints,
                type: LoyaltyTransactionType::VoidReversal->value,
                actor: $actor,
                notes: 'Reversed - voided transaction ' . $transaction->transaction_number,
                orderId: $transaction->id,
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Loyalty points reversal failed', [
                'transaction_id' => $transaction->id,
                'customer_id' => $transaction->customer_id,
                'points' => $earnedPoints,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Deduct loyalty points proportional to a refund amount, so the customer
     * does not retain points earned on the refunded portion of the original
     * sale. Writes a negative `adjust` entry in loyalty_transactions and
     * decrements the customer's cumulative balance.
     */
    private function refundLoyaltyPoints(Transaction $transaction, User $actor): void
    {
        $config = LoyaltyConfig::where('organization_id', $transaction->organization_id)->first();
        if (! $config || ! $config->is_active || $config->points_per_sar <= 0) {
            return;
        }

        $refundAmount = (float) $transaction->total_amount;
        $pointsToDeduct = (int) floor($refundAmount * $config->points_per_sar);
        if ($pointsToDeduct <= 0) {
            return;
        }

        try {
            app(LoyaltyService::class)->adjustPoints(
                customerId: $transaction->customer_id,
                points: -$pointsToDeduct,
                type: LoyaltyTransactionType::Adjust->value,
                actor: $actor,
                notes: 'Refund deduction - ' . $transaction->transaction_number,
                orderId: $transaction->id,
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Loyalty points refund deduction failed', [
                'transaction_id' => $transaction->id,
                'customer_id' => $transaction->customer_id,
                'points' => $pointsToDeduct,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function generateNumber(string $storeId): string
    {
        $date   = now()->format('Ymd');
        $prefix = "TXN-{$date}-";

        // Postgres rejects `SELECT count(*) ... FOR UPDATE`. Use a per-(store,date)
        // advisory lock so concurrent transactions serialize on the counter without
        // locking aggregate rows. SQLite/MySQL fall back to a no-op lock.
        $driver = DB::connection()->getDriverName();
        if ($driver === 'pgsql') {
            $lockKey = crc32($storeId . '|' . $date);
            DB::statement('SELECT pg_advisory_xact_lock(?)', [$lockKey]);
        }

        $count = DB::table('transactions')
            ->where('store_id', $storeId)
            ->where('transaction_number', 'like', "{$prefix}%")
            ->count();

        $number = sprintf('TXN-%s-%04d', $date, $count + 1);

        // Retry with incrementing suffix if collision occurs (defence-in-depth).
        $maxRetries = 5;
        $attempt    = 0;
        while (Transaction::where('transaction_number', $number)->exists() && $attempt < $maxRetries) {
            $attempt++;
            $number = sprintf('TXN-%s-%04d', $date, $count + 1 + $attempt);
        }

        return $number;
    }
}
