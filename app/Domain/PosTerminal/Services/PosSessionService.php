<?php

namespace App\Domain\PosTerminal\Services;

use App\Domain\Auth\Models\User;
use App\Domain\Payment\Enums\CashEventType;
use App\Domain\Payment\Enums\CashSessionStatus;
use App\Domain\Payment\Models\CashEvent;
use App\Domain\PosTerminal\Models\PosSession;
use App\Domain\PosTerminal\Models\Transaction;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class PosSessionService
{
    public function list(string $storeId, int $perPage = 20): LengthAwarePaginator
    {
        return PosSession::where('store_id', $storeId)
            ->orderByDesc('opened_at')
            ->paginate($perPage);
    }

    public function find(string $sessionId, string $storeId): PosSession
    {
        return PosSession::where('store_id', $storeId)->with('transactions')->findOrFail($sessionId);
    }

    public function open(array $data, User $actor): PosSession
    {
        // Check for existing open session on same register
        if (!empty($data['register_id'])) {
            $existing = PosSession::where('store_id', $actor->store_id)
                ->where('register_id', $data['register_id'])
                ->where('status', CashSessionStatus::Open)
                ->first();

            if ($existing) {
                throw new \RuntimeException('There is already an open session on this register.');
            }
        }

        return PosSession::create([
            'store_id' => $actor->store_id,
            'register_id' => $data['register_id'] ?? null,
            'cashier_id' => $actor->id,
            'status' => CashSessionStatus::Open,
            'opening_cash' => $data['opening_cash'] ?? 0,
            'total_cash_sales' => 0,
            'total_card_sales' => 0,
            'total_other_sales' => 0,
            'total_refunds' => 0,
            'total_voids' => 0,
            'transaction_count' => 0,
            'opened_at' => now(),
            'z_report_printed' => false,
        ]);
    }

    public function close(PosSession $session, array $data): PosSession
    {
        if ($session->status !== CashSessionStatus::Open) {
            throw new \RuntimeException('This session is already closed.');
        }

        // total_cash_sales is already net of cash refunds (see
        // TransactionService::updateSessionRefunds), so the expected cash in
        // the drawer is simply opening float + net cash movement.
        $expectedCash = ($session->opening_cash ?? 0)
            + ($session->total_cash_sales ?? 0);

        $closingCash = $data['closing_cash'] ?? 0;
        $difference = $closingCash - $expectedCash;

        $session->update([
            'status' => CashSessionStatus::Closed,
            'closing_cash' => $closingCash,
            'expected_cash' => $expectedCash,
            'cash_difference' => $difference,
            'closed_at' => now(),
        ]);

        return $session->fresh();
    }

    /**
     * Record a cash drop (cash_out) or paid-in (cash_in) against an open
     * POS session. Writes a row in `cash_events` and adjusts the session's
     * `total_cash_sales` running balance so the close-shift expected_cash
     * stays accurate.
     *
     * `cash_events.cash_session_id` is reused here to point at the
     * `pos_sessions.id` — both are UUIDs and there is no FK constraint.
     */
    public function recordCashEvent(PosSession $session, array $data, User $actor): CashEvent
    {
        if ($session->status !== CashSessionStatus::Open) {
            throw new \RuntimeException('Cannot record cash events on a closed session.');
        }

        $type = CashEventType::from($data['type']);
        $amount = (float) $data['amount'];
        if ($amount <= 0) {
            throw new \RuntimeException('Cash event amount must be positive.');
        }

        return DB::transaction(function () use ($session, $type, $amount, $data, $actor) {
            $event = CashEvent::create([
                'cash_session_id' => $session->id,
                'type' => $type->value,
                'amount' => $amount,
                'reason' => $data['reason'] ?? null,
                'notes' => $data['notes'] ?? null,
                'performed_by' => $actor->id,
            ]);

            // Move the cash drawer running balance so the close-shift
            // reconciliation reports the right expected_cash.
            if ($type === CashEventType::CashIn) {
                $session->increment('total_cash_sales', $amount);
            } else {
                $session->decrement('total_cash_sales', $amount);
            }

            return $event;
        });
    }

    public function listCashEvents(PosSession $session): \Illuminate\Database\Eloquent\Collection
    {
        return CashEvent::where('cash_session_id', $session->id)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Build an X-report (mid-shift snapshot) for the given session. Pulls the
     * session counters plus a payment-method breakdown computed from
     * transactions, so the cashier can reconcile cash without closing.
     */
    public function xReport(PosSession $session): array
    {
        return $this->buildReport($session, false);
    }

    /**
     * Build a Z-report (end-of-shift) and mark `z_report_printed=true`.
     * Z-reports should only be generated against a closed session, but we
     * allow it on open sessions too so that long shifts can produce one if
     * the cashier prints before closing the drawer count.
     */
    public function zReport(PosSession $session): array
    {
        $report = $this->buildReport($session, true);
        $session->update(['z_report_printed' => true]);
        return $report;
    }

    private function buildReport(PosSession $session, bool $includeClose): array
    {
        $txQuery = Transaction::where('pos_session_id', $session->id)
            ->where('status', '!=', 'voided');

        $sales = (clone $txQuery)->where('type', 'sale')->get();
        $returns = (clone $txQuery)->where('type', 'return')->get();
        $voids = Transaction::where('pos_session_id', $session->id)
            ->where('status', 'voided')->get();

        $paymentBreakdown = DB::table('payments')
            ->join('transactions', 'payments.transaction_id', '=', 'transactions.id')
            ->where('transactions.pos_session_id', $session->id)
            ->where('transactions.status', '!=', 'voided')
            ->selectRaw('payments.method, '
                . 'SUM(CASE WHEN transactions.type = ? THEN payments.amount ELSE 0 END) as sales_total, '
                . 'SUM(CASE WHEN transactions.type = ? THEN payments.amount ELSE 0 END) as refund_total',
                ['sale', 'return'])
            ->groupBy('payments.method')
            ->get();

        $cashEvents = $this->listCashEvents($session);
        $cashIn = (float) $cashEvents->where('type', CashEventType::CashIn)->sum('amount');
        $cashOut = (float) $cashEvents->where('type', CashEventType::CashOut)->sum('amount');

        return [
            'session_id' => $session->id,
            'cashier_id' => $session->cashier_id,
            'register_id' => $session->register_id,
            'opened_at' => $session->opened_at,
            'closed_at' => $session->closed_at,
            'opening_cash' => (float) $session->opening_cash,
            'expected_cash' => (float) $session->opening_cash + (float) $session->total_cash_sales,
            'closing_cash' => $includeClose ? $session->closing_cash : null,
            'cash_difference' => $includeClose ? $session->cash_difference : null,
            'totals' => [
                'sale_count' => $sales->count(),
                'return_count' => $returns->count(),
                'void_count' => $voids->count(),
                'gross_sales' => (float) $sales->sum('total_amount'),
                'total_refunds' => (float) $returns->sum('total_amount'),
                'total_voids' => (float) $voids->sum('total_amount'),
                'total_discounts' => (float) $sales->sum('discount_amount'),
                'total_tax' => (float) $sales->sum('tax_amount'),
                'total_tips' => (float) $sales->sum('tip_amount'),
                'net_sales' => (float) $sales->sum('total_amount') - (float) $returns->sum('total_amount'),
            ],
            'payment_breakdown' => $paymentBreakdown,
            'cash_drawer' => [
                'opening_cash' => (float) $session->opening_cash,
                'cash_sales_net' => (float) $session->total_cash_sales,
                'cash_in_total' => $cashIn,
                'cash_out_total' => $cashOut,
                'expected_cash' => (float) $session->opening_cash + (float) $session->total_cash_sales,
            ],
            'cash_events' => $cashEvents,
        ];
    }
}
