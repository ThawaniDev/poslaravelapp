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
            ->with(['store:id,name', 'register:id,name', 'cashier:id,name'])
            ->orderByDesc('opened_at')
            ->paginate($perPage);
    }

    /**
     * Open sessions currently owned by the given cashier, across ANY register
     * or store. Used to enforce that a user cannot be linked to more than one
     * register at a time, and to surface existing shifts they must close first.
     */
    public function myOpenSessions(User $actor): \Illuminate\Support\Collection
    {
        return PosSession::with('register')
            ->where('cashier_id', $actor->id)
            ->where('status', CashSessionStatus::Open)
            ->orderByDesc('opened_at')
            ->get();
    }

    public function find(string $sessionId, string $storeId): PosSession
    {
        return PosSession::where('store_id', $storeId)
            ->with(['store:id,name', 'register:id,name', 'cashier:id,name', 'transactions'])
            ->findOrFail($sessionId);
    }

    public function open(array $data, User $actor): PosSession
    {
        // Enforce: one user can only own ONE open session at a time (on any
        // register, any store). This prevents a cashier from being linked to
        // multiple registers simultaneously, which would split their sales
        // across shifts and break close-of-day reconciliation.
        $existingForUser = PosSession::with('register')
            ->where('cashier_id', $actor->id)
            ->where('status', CashSessionStatus::Open)
            ->first();

        if ($existingForUser) {
            if (!empty($data['register_id']) && $existingForUser->register_id === $data['register_id']) {
                throw new \RuntimeException(__('pos.session_already_open'));
            }
            $registerName = $existingForUser->register?->name ?? $existingForUser->register_id ?? '';
            throw new \RuntimeException(__('pos.session_user_has_other_open', ['register' => $registerName]));
        }

        // Belt-and-suspenders: also block a second session on the same register
        // opened by a different cashier.
        if (!empty($data['register_id'])) {
            $existing = PosSession::where('store_id', $actor->store_id)
                ->where('register_id', $data['register_id'])
                ->where('status', CashSessionStatus::Open)
                ->first();

            if ($existing) {
                throw new \RuntimeException(__('pos.session_already_open'));
            }
        }

        return PosSession::create(array_filter([
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
        ], fn ($v) => $v !== null))->load(['store:id,name', 'register:id,name', 'cashier:id,name']);
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

        return $session->fresh(['store:id,name', 'register:id,name', 'cashier:id,name']);
    }

    /**
     * Reopen a previously closed session for corrections (manager-only).
     * Clears closing_cash / cash_difference so a fresh close-out is required.
     */
    public function reopen(PosSession $session): PosSession
    {
        if ($session->status === CashSessionStatus::Open) {
            throw new \RuntimeException(__('pos.session_already_open'));
        }

        $session->update([
            'status' => CashSessionStatus::Open,
            'closing_cash' => null,
            'cash_difference' => null,
            'closed_at' => null,
            'z_report_printed' => false,
        ]);

        return $session->fresh(['store:id,name', 'register:id,name', 'cashier:id,name']);
    }

    /**
     * Close every open session for the given store(s) using each session's
     * own expected_cash as the closing_cash (zero-difference end-of-day).
     * Returns an array of closed session summaries.
     */
    public function batchClose(array $storeIds): array
    {
        $sessions = PosSession::whereIn('store_id', $storeIds)
            ->where('status', CashSessionStatus::Open)
            ->get();

        $closed = [];
        foreach ($sessions as $session) {
            $expectedCash = ($session->opening_cash ?? 0)
                + ($session->total_cash_sales ?? 0);
            $session->update([
                'status' => CashSessionStatus::Closed,
                'closing_cash' => $expectedCash,
                'expected_cash' => $expectedCash,
                'cash_difference' => 0,
                'closed_at' => now(),
            ]);
            $closed[] = [
                'id' => $session->id,
                'register_id' => $session->register_id,
                'cashier_id' => $session->cashier_id,
                'expected_cash' => (float) $expectedCash,
            ];
        }

        return [
            'closed_count' => count($closed),
            'sessions' => $closed,
        ];
    }

    /**
     * Daily summary stats grouped by cashier and register, optionally
     * scoped to a date range.
     */
    public function summary(array $storeIds, ?string $from = null, ?string $to = null): array
    {
        $query = PosSession::whereIn('store_id', $storeIds);

        if ($from) {
            $query->whereDate('opened_at', '>=', $from);
        }
        if ($to) {
            $query->whereDate('opened_at', '<=', $to);
        }

        $sessions = $query->with(['register:id,name', 'cashier:id,name'])->get();

        $byCashier = $sessions->groupBy('cashier_id')->map(function ($group) {
            $first = $group->first();
            return [
                'cashier_id' => $first->cashier_id,
                'cashier_name' => $first->cashier?->name,
                'session_count' => $group->count(),
                'total_cash_sales' => (float) $group->sum('total_cash_sales'),
                'total_card_sales' => (float) $group->sum('total_card_sales'),
                'total_other_sales' => (float) $group->sum('total_other_sales'),
                'total_refunds' => (float) $group->sum('total_refunds'),
                'transaction_count' => (int) $group->sum('transaction_count'),
                'cash_difference_total' => (float) $group->sum('cash_difference'),
            ];
        })->values();

        $byRegister = $sessions->groupBy('register_id')->map(function ($group) {
            $first = $group->first();
            return [
                'register_id' => $first->register_id,
                'register_name' => $first->register?->name,
                'session_count' => $group->count(),
                'total_cash_sales' => (float) $group->sum('total_cash_sales'),
                'total_card_sales' => (float) $group->sum('total_card_sales'),
                'transaction_count' => (int) $group->sum('transaction_count'),
            ];
        })->values();

        return [
            'session_count' => $sessions->count(),
            'open_count' => $sessions->where('status', CashSessionStatus::Open)->count(),
            'closed_count' => $sessions->where('status', CashSessionStatus::Closed)->count(),
            'totals' => [
                'cash_sales' => (float) $sessions->sum('total_cash_sales'),
                'card_sales' => (float) $sessions->sum('total_card_sales'),
                'other_sales' => (float) $sessions->sum('total_other_sales'),
                'refunds' => (float) $sessions->sum('total_refunds'),
                'voids' => (float) $sessions->sum('total_voids'),
                'transaction_count' => (int) $sessions->sum('transaction_count'),
            ],
            'by_cashier' => $byCashier,
            'by_register' => $byRegister,
        ];
    }

    /**
     * Record a cash drop (cash_out) or paid-in (cash_in) against an open
     * POS session. Writes a row in `cash_events` and adjusts the session's
     * `total_cash_sales` running balance so the close-shift expected_cash
     * stays accurate.
     *
     * Writes to the dedicated `cash_events.pos_session_id` column
     * (see migration 2026_04_21_100000). `cash_session_id` is left NULL
     * because POS-originated events are anchored to the till session,
     * not the legacy `cash_sessions` workflow.
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
                'pos_session_id' => $session->id,
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
        return CashEvent::where('pos_session_id', $session->id)
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
