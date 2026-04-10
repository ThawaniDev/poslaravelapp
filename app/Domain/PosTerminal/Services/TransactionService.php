<?php

namespace App\Domain\PosTerminal\Services;

use App\Domain\Auth\Models\User;
use App\Domain\Customer\Models\Customer;
use App\Domain\Inventory\Enums\StockMovementType;
use App\Domain\Inventory\Enums\StockReferenceType;
use App\Domain\Inventory\Models\StockLevel;
use App\Domain\Inventory\Models\StockMovement;
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
            $query->where('transaction_number', 'like', "%{$filters['search']}%");
        }

        return $query->orderByDesc('created_at')->paginate($perPage);
    }

    public function find(string $transactionId): Transaction
    {
        return Transaction::with(['transactionItems', 'payments'])->findOrFail($transactionId);
    }

    public function findByNumber(string $storeId, string $number): Transaction
    {
        return Transaction::with(['transactionItems', 'payments'])
            ->where('store_id', $storeId)
            ->where('transaction_number', $number)
            ->firstOrFail();
    }

    public function create(array $data, User $actor): Transaction
    {
        return DB::transaction(function () use ($data, $actor) {
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
                        'tax_rate' => $item['tax_rate'] ?? 0,
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

                    if ($type === TransactionType::Sale) {
                        $this->updateSessionSales($session, $data['payments'] ?? []);
                    } elseif ($type === TransactionType::Return) {
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

            return $transaction->load(['transactionItems', 'payments']);
        });
    }

    public function createReturn(array $data, User $actor): Transaction
    {
        $originalTransaction = $this->find($data['return_transaction_id']);

        if ($originalTransaction->status !== TransactionStatus::Completed) {
            throw new \RuntimeException(__('pos.return_original_not_completed'));
        }

        if ($originalTransaction->type !== TransactionType::Sale) {
            throw new \RuntimeException(__('pos.return_only_sales'));
        }

        $data['type'] = TransactionType::Return->value;
        $data['return_transaction_id'] = $originalTransaction->id;

        return $this->create($data, $actor);
    }

    public function void(Transaction $transaction, User $actor): Transaction
    {
        if ($transaction->status === TransactionStatus::Voided) {
            throw new \RuntimeException('This transaction is already voided.');
        }

        if ($transaction->status !== TransactionStatus::Completed) {
            throw new \RuntimeException('Only completed transactions can be voided.');
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

        // Upsert daily_sales_summary
        $existing = DailySalesSummary::where('store_id', $storeId)
            ->whereDate('date', $date)
            ->first();

        if ($existing) {
            if ($isReturn) {
                $existing->total_refunds = (float) $existing->total_refunds + $totalAmount;
                $existing->net_revenue = (float) $existing->net_revenue - $totalAmount;
            } else {
                $existing->total_transactions = $existing->total_transactions + 1;
                $existing->total_revenue = (float) $existing->total_revenue + $totalAmount;
                $existing->total_cost = (float) $existing->total_cost + $totalCost;
                $existing->total_discount = (float) $existing->total_discount + $discountAmount;
                $existing->total_tax = (float) $existing->total_tax + $taxAmount;
                $existing->net_revenue = (float) $existing->net_revenue + $totalAmount;
                $existing->cash_revenue = (float) $existing->cash_revenue + $cashRevenue;
                $existing->card_revenue = (float) $existing->card_revenue + $cardRevenue;
                $existing->other_revenue = (float) $existing->other_revenue + $otherRevenue;
                $existing->avg_basket_size = $existing->total_transactions > 0
                    ? round((float) $existing->total_revenue / $existing->total_transactions, 2)
                    : 0;
            }
            $existing->save();
        } else {
            DailySalesSummary::create([
                'store_id' => $storeId,
                'date' => $date,
                'total_transactions' => $isReturn ? 0 : 1,
                'total_revenue' => $isReturn ? 0 : $totalAmount,
                'total_cost' => $isReturn ? 0 : $totalCost,
                'total_discount' => $isReturn ? 0 : $discountAmount,
                'total_tax' => $isReturn ? 0 : $taxAmount,
                'total_refunds' => $isReturn ? $totalAmount : 0,
                'net_revenue' => $isReturn ? -$totalAmount : $totalAmount,
                'cash_revenue' => $isReturn ? 0 : $cashRevenue,
                'card_revenue' => $isReturn ? 0 : $cardRevenue,
                'other_revenue' => $isReturn ? 0 : $otherRevenue,
                'avg_basket_size' => $isReturn ? 0 : $totalAmount,
                'unique_customers' => $transaction->customer_id ? 1 : 0,
            ]);
        }

        // Upsert product_sales_summary for each item
        foreach ($transaction->transactionItems as $item) {
            if (! $item->product_id) {
                continue;
            }

            $existingProduct = ProductSalesSummary::where('store_id', $storeId)
                ->where('product_id', $item->product_id)
                ->whereDate('date', $date)
                ->first();

            $qty = (float) $item->quantity;
            $revenue = (float) $item->line_total;
            $cost = (float) $item->cost_price * $qty;
            $discount = (float) $item->discount_amount;
            $tax = (float) $item->tax_amount;

            if ($existingProduct) {
                if ($isReturn || $item->is_return_item) {
                    $existingProduct->return_quantity = (float) $existingProduct->return_quantity + $qty;
                    $existingProduct->return_amount = (float) $existingProduct->return_amount + $revenue;
                } else {
                    $existingProduct->quantity_sold = (float) $existingProduct->quantity_sold + $qty;
                    $existingProduct->revenue = (float) $existingProduct->revenue + $revenue;
                    $existingProduct->cost = (float) $existingProduct->cost + $cost;
                    $existingProduct->discount_amount = (float) $existingProduct->discount_amount + $discount;
                    $existingProduct->tax_amount = (float) $existingProduct->tax_amount + $tax;
                }
                $existingProduct->save();
            } else {
                ProductSalesSummary::create([
                    'store_id' => $storeId,
                    'product_id' => $item->product_id,
                    'date' => $date,
                    'quantity_sold' => ($isReturn || $item->is_return_item) ? 0 : $qty,
                    'revenue' => ($isReturn || $item->is_return_item) ? 0 : $revenue,
                    'cost' => ($isReturn || $item->is_return_item) ? 0 : $cost,
                    'discount_amount' => ($isReturn || $item->is_return_item) ? 0 : $discount,
                    'tax_amount' => ($isReturn || $item->is_return_item) ? 0 : $tax,
                    'return_quantity' => ($isReturn || $item->is_return_item) ? $qty : 0,
                    'return_amount' => ($isReturn || $item->is_return_item) ? $revenue : 0,
                ]);
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
            if ($isReturn) {
                $existing->total_refunds = max(0, (float) $existing->total_refunds - $totalAmount);
                $existing->net_revenue = (float) $existing->net_revenue + $totalAmount;
            } else {
                $existing->total_transactions = max(0, $existing->total_transactions - 1);
                $existing->total_revenue = max(0, (float) $existing->total_revenue - $totalAmount);
                $existing->total_cost = max(0, (float) $existing->total_cost - $totalCost);
                $existing->total_discount = max(0, (float) $existing->total_discount - $discountAmount);
                $existing->total_tax = max(0, (float) $existing->total_tax - $taxAmount);
                $existing->net_revenue = (float) $existing->net_revenue - $totalAmount;
                $existing->cash_revenue = max(0, (float) $existing->cash_revenue - $cashRevenue);
                $existing->card_revenue = max(0, (float) $existing->card_revenue - $cardRevenue);
                $existing->other_revenue = max(0, (float) $existing->other_revenue - $otherRevenue);
                $existing->avg_basket_size = $existing->total_transactions > 0
                    ? round((float) $existing->total_revenue / $existing->total_transactions, 2)
                    : 0;
            }
            $existing->save();
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
                $existingProduct->return_quantity = max(0, (float) $existingProduct->return_quantity - $qty);
                $existingProduct->return_amount = max(0, (float) $existingProduct->return_amount - $revenue);
            } else {
                $existingProduct->quantity_sold = max(0, (float) $existingProduct->quantity_sold - $qty);
                $existingProduct->revenue = max(0, (float) $existingProduct->revenue - $revenue);
                $existingProduct->cost = max(0, (float) $existingProduct->cost - $cost);
                $existingProduct->discount_amount = max(0, (float) $existingProduct->discount_amount - $discount);
                $existingProduct->tax_amount = max(0, (float) $existingProduct->tax_amount - $tax);
            }
            $existingProduct->save();
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

            // Update stock_levels
            $stockLevel = StockLevel::where('store_id', $storeId)
                ->where('product_id', $item->product_id)
                ->first();

            if ($stockLevel) {
                $stockLevel->quantity = (float) $stockLevel->quantity + $stockChange;
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

    private function generateNumber(string $storeId): string
    {
        $date = now()->format('Ymd');
        $count = Transaction::where('store_id', $storeId)
            ->where('transaction_number', 'like', "TXN-{$date}-%")
            ->count();

        return sprintf('TXN-%s-%04d', $date, $count + 1);
    }
}
