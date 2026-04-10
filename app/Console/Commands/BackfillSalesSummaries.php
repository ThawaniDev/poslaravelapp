<?php

namespace App\Console\Commands;

use App\Domain\Customer\Models\Customer;
use App\Domain\Inventory\Enums\StockMovementType;
use App\Domain\Inventory\Enums\StockReferenceType;
use App\Domain\Inventory\Models\StockLevel;
use App\Domain\Inventory\Models\StockMovement;
use App\Domain\PosTerminal\Enums\TransactionStatus;
use App\Domain\PosTerminal\Enums\TransactionType;
use App\Domain\PosTerminal\Models\Transaction;
use App\Domain\Report\Models\DailySalesSummary;
use App\Domain\Report\Models\ProductSalesSummary;
use Illuminate\Console\Command;

class BackfillSalesSummaries extends Command
{
    protected $signature = 'sales:backfill-summaries {--store= : Specific store ID} {--date= : Specific date (Y-m-d)} {--fresh : Clear existing summaries before backfilling} {--with-inventory : Also backfill stock_levels and stock_movements} {--with-customers : Also backfill customer stats}';

    protected $description = 'Backfill daily_sales_summary and product_sales_summary from existing POS transactions';

    public function handle(): int
    {
        $storeId = $this->option('store');
        $date = $this->option('date');

        if ($this->option('fresh')) {
            $this->warn('Clearing existing summary data...');
            $dailyQuery = DailySalesSummary::query();
            $productQuery = ProductSalesSummary::query();

            if ($storeId) {
                $dailyQuery->where('store_id', $storeId);
                $productQuery->where('store_id', $storeId);
            }
            if ($date) {
                $dailyQuery->whereDate('date', $date);
                $productQuery->whereDate('date', $date);
            }

            $dailyQuery->delete();
            $productQuery->delete();
        }

        $query = Transaction::with(['transactionItems', 'payments'])
            ->where('status', TransactionStatus::Completed);

        if ($storeId) {
            $query->where('store_id', $storeId);
        }
        if ($date) {
            $query->whereDate('created_at', $date);
        }

        $total = $query->count();
        $this->info("Processing {$total} transactions...");

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $query->orderBy('created_at')->chunk(100, function ($transactions) use ($bar) {
            foreach ($transactions as $transaction) {
                $this->processTransaction($transaction);
                if ($this->option('with-inventory')) {
                    $this->processInventory($transaction);
                }
                if ($this->option('with-customers')) {
                    $this->processCustomerStats($transaction);
                }
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();
        $this->info('Sales summaries backfilled successfully.');
        if ($this->option('with-inventory')) {
            $this->info('Inventory (stock_levels + stock_movements) also backfilled.');
        }
        if ($this->option('with-customers')) {
            $this->info('Customer stats (total_spend, visit_count, last_visit_at) also backfilled.');
        }

        return self::SUCCESS;
    }

    private function processTransaction(Transaction $transaction): void
    {
        $date = $transaction->created_at->toDateString();
        $storeId = $transaction->store_id;
        $isReturn = $transaction->type === TransactionType::Return
            || $transaction->type === TransactionType::Return->value;

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

    private function processInventory(Transaction $transaction): void
    {
        $storeId = $transaction->store_id;
        $isReturn = $transaction->type === TransactionType::Return
            || $transaction->type === TransactionType::Return->value;

        foreach ($transaction->transactionItems as $item) {
            if (! $item->product_id) {
                continue;
            }

            $qty = (float) $item->quantity;

            if ($isReturn || $item->is_return_item) {
                $movementType = StockMovementType::AdjustmentIn;
                $stockChange = $qty;
                $reason = 'Backfill return - ' . $transaction->transaction_number;
            } else {
                $movementType = StockMovementType::Sale;
                $stockChange = -$qty;
                $reason = 'Backfill sale - ' . $transaction->transaction_number;
            }

            // Check if movement already exists (avoid duplicates)
            $exists = StockMovement::where('reference_type', StockReferenceType::Transaction->value)
                ->where('reference_id', $transaction->id)
                ->where('product_id', $item->product_id)
                ->exists();

            if ($exists) {
                continue;
            }

            $stockLevel = StockLevel::where('store_id', $storeId)
                ->where('product_id', $item->product_id)
                ->first();

            if ($stockLevel) {
                $stockLevel->quantity = (float) $stockLevel->quantity + $stockChange;
                $stockLevel->sync_version = ($stockLevel->sync_version ?? 0) + 1;
                $stockLevel->save();
            }

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

    private function processCustomerStats(Transaction $transaction): void
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
            if (! $customer->last_visit_at || $transaction->created_at->gt($customer->last_visit_at)) {
                $customer->last_visit_at = $transaction->created_at;
            }
        }

        $customer->save();
    }
}
