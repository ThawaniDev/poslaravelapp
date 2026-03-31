<?php

namespace App\Domain\Payment\Services;

use Illuminate\Support\Facades\DB;

class FinancialSummaryService
{
    /**
     * Get a financial daily summary for a given store and date.
     */
    public function dailySummary(string $storeId, string $date): array
    {
        // Payment totals by method
        $paymentBreakdown = DB::table('payments')
            ->join('transactions', 'payments.transaction_id', '=', 'transactions.id')
            ->where('transactions.store_id', $storeId)
            ->whereDate('payments.created_at', $date)
            ->selectRaw("
                payments.method,
                COUNT(*) as count,
                COALESCE(SUM(payments.amount), 0) as total
            ")
            ->groupBy('payments.method')
            ->get()
            ->map(fn ($row) => [
                'method' => $row->method,
                'count' => (int) $row->count,
                'total' => round((float) $row->total, 2),
            ])
            ->toArray();

        $totalRevenue = collect($paymentBreakdown)->sum('total');
        $totalTransactions = collect($paymentBreakdown)->sum('count');

        // Refund totals
        $refunds = DB::table('refunds')
            ->join('payments', 'refunds.payment_id', '=', 'payments.id')
            ->join('transactions', 'payments.transaction_id', '=', 'transactions.id')
            ->where('transactions.store_id', $storeId)
            ->whereDate('refunds.created_at', $date)
            ->where('refunds.status', 'completed')
            ->selectRaw("
                COUNT(*) as count,
                COALESCE(SUM(refunds.amount), 0) as total
            ")
            ->first();

        // Cash sessions
        $cashSessions = DB::table('cash_sessions')
            ->where('store_id', $storeId)
            ->where(function ($q) use ($date) {
                $q->whereDate('opened_at', $date)
                    ->orWhereDate('closed_at', $date);
            })
            ->selectRaw("
                COUNT(*) as count,
                COALESCE(SUM(CASE WHEN status = 'closed' THEN variance ELSE 0 END), 0) as total_variance,
                COALESCE(SUM(CASE WHEN status = 'closed' THEN actual_cash ELSE 0 END), 0) as total_actual_cash,
                COALESCE(SUM(CASE WHEN status = 'closed' THEN expected_cash ELSE 0 END), 0) as total_expected_cash
            ")
            ->first();

        // Expenses
        $expenses = DB::table('expenses')
            ->where('store_id', $storeId)
            ->whereDate('expense_date', $date)
            ->selectRaw("
                category,
                COUNT(*) as count,
                COALESCE(SUM(amount), 0) as total
            ")
            ->groupBy('category')
            ->get()
            ->map(fn ($row) => [
                'category' => $row->category,
                'count' => (int) $row->count,
                'total' => round((float) $row->total, 2),
            ])
            ->toArray();

        $totalExpenses = collect($expenses)->sum('total');

        // Hourly breakdown
        $hourly = DB::table('payments')
            ->join('transactions', 'payments.transaction_id', '=', 'transactions.id')
            ->where('transactions.store_id', $storeId)
            ->whereDate('payments.created_at', $date)
            ->selectRaw("
                EXTRACT(HOUR FROM payments.created_at) as hour,
                COUNT(*) as count,
                COALESCE(SUM(payments.amount), 0) as total
            ")
            ->groupByRaw('EXTRACT(HOUR FROM payments.created_at)')
            ->orderByRaw('EXTRACT(HOUR FROM payments.created_at)')
            ->get()
            ->map(fn ($row) => [
                'hour' => (int) $row->hour,
                'count' => (int) $row->count,
                'total' => round((float) $row->total, 2),
            ])
            ->toArray();

        return [
            'date' => $date,
            'store_id' => $storeId,
            'revenue' => [
                'gross' => round($totalRevenue, 2),
                'refunds' => round((float) ($refunds->total ?? 0), 2),
                'expenses' => round($totalExpenses, 2),
                'net' => round($totalRevenue - (float) ($refunds->total ?? 0) - $totalExpenses, 2),
            ],
            'transactions' => [
                'count' => $totalTransactions,
                'refund_count' => (int) ($refunds->count ?? 0),
                'average' => $totalTransactions > 0 ? round($totalRevenue / $totalTransactions, 2) : 0,
            ],
            'payment_breakdown' => $paymentBreakdown,
            'cash_sessions' => [
                'count' => (int) ($cashSessions->count ?? 0),
                'total_variance' => round((float) ($cashSessions->total_variance ?? 0), 2),
                'total_actual_cash' => round((float) ($cashSessions->total_actual_cash ?? 0), 2),
                'total_expected_cash' => round((float) ($cashSessions->total_expected_cash ?? 0), 2),
            ],
            'expenses' => [
                'total' => round($totalExpenses, 2),
                'breakdown' => $expenses,
            ],
            'hourly_activity' => $hourly,
        ];
    }

    /**
     * Get reconciliation data: expected vs actual for a date range.
     */
    public function reconciliation(string $storeId, string $startDate, string $endDate): array
    {
        $sessions = DB::table('cash_sessions')
            ->where('store_id', $storeId)
            ->where('status', 'closed')
            ->whereDate('closed_at', '>=', $startDate)
            ->whereDate('closed_at', '<=', $endDate)
            ->select([
                'id',
                'terminal_id',
                'opening_float',
                'expected_cash',
                'actual_cash',
                'variance',
                'opened_at',
                'closed_at',
                'close_notes',
            ])
            ->orderBy('closed_at')
            ->get()
            ->toArray();

        $totalExpected = collect($sessions)->sum('expected_cash');
        $totalActual = collect($sessions)->sum('actual_cash');

        return [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'summary' => [
                'session_count' => count($sessions),
                'total_expected' => round((float) $totalExpected, 2),
                'total_actual' => round((float) $totalActual, 2),
                'total_variance' => round((float) ($totalActual - $totalExpected), 2),
                'within_tolerance' => abs($totalActual - $totalExpected) <= 5,
            ],
            'sessions' => array_map(fn ($s) => [
                'id' => $s->id,
                'terminal_id' => $s->terminal_id,
                'opening_float' => round((float) $s->opening_float, 2),
                'expected_cash' => round((float) $s->expected_cash, 2),
                'actual_cash' => round((float) $s->actual_cash, 2),
                'variance' => round((float) $s->variance, 2),
                'opened_at' => $s->opened_at,
                'closed_at' => $s->closed_at,
            ], $sessions),
        ];
    }
}
