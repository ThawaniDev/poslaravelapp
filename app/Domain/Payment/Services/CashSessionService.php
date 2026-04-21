<?php

namespace App\Domain\Payment\Services;

use App\Domain\Auth\Models\User;
use App\Domain\Payment\Enums\CashEventType;
use App\Domain\Payment\Models\CashEvent;
use App\Domain\Payment\Models\CashSession;
use App\Domain\Payment\Models\Expense;
use App\Domain\Payment\Enums\CashSessionStatus;
use App\Domain\Shared\Traits\ScopesStoreQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class CashSessionService
{
    use ScopesStoreQuery;

    public function list(string|array $storeId, int $perPage = 20): LengthAwarePaginator
    {
        return $this->scopeByStore(CashSession::query(), $storeId)
            ->orderByDesc('opened_at')
            ->paginate($perPage);
    }

    public function find(string $sessionId): CashSession
    {
        return CashSession::with(['cashEvents', 'expenses'])->findOrFail($sessionId);
    }

    public function open(array $data, User $actor): CashSession
    {
        // Check for existing open session on same terminal
        if (!empty($data['terminal_id'])) {
            $existing = CashSession::where('store_id', $actor->store_id)
                ->where('terminal_id', $data['terminal_id'])
                ->where('status', CashSessionStatus::Open)
                ->first();

            if ($existing) {
                throw new \RuntimeException('There is already an open cash session on this terminal.');
            }
        }

        return CashSession::create([
            'store_id' => $actor->store_id,
            'terminal_id' => $data['terminal_id'] ?? null,
            'opened_by' => $actor->id,
            'opening_float' => $data['opening_float'] ?? 0,
            'expected_cash' => $data['opening_float'] ?? 0,
            'status' => CashSessionStatus::Open,
            'opened_at' => now(),
        ]);
    }

    public function close(CashSession $session, array $data, User $actor): CashSession
    {
        if ($session->status !== CashSessionStatus::Open) {
            throw new \RuntimeException('This cash session is already closed.');
        }

        $actualCash = $data['actual_cash'] ?? 0;
        $expectedCash = (float) $session->expected_cash;
        $variance = $actualCash - $expectedCash;

        $session->update([
            'status' => CashSessionStatus::Closed,
            'closed_by' => $actor->id,
            'actual_cash' => $actualCash,
            'variance' => $variance,
            'closed_at' => now(),
            'close_notes' => $data['close_notes'] ?? null,
        ]);

        return $session->fresh();
    }

    public function addCashEvent(CashSession $session, array $data, User $actor): CashEvent
    {
        if ($session->status !== CashSessionStatus::Open) {
            throw new \RuntimeException('Cannot add cash events to a closed session.');
        }

        $event = CashEvent::create([
            'cash_session_id' => $session->id,
            'type' => $data['type'],
            'amount' => $data['amount'],
            'reason' => $data['reason'] ?? null,
            'notes' => $data['notes'] ?? null,
            'performed_by' => $actor->id,
        ]);

        // Update expected cash
        $amount = (float) $data['amount'];
        if ($data['type'] === CashEventType::CashIn->value || $data['type'] === 'cash_in') {
            $session->increment('expected_cash', $amount);
        } else {
            $session->decrement('expected_cash', $amount);
        }

        return $event;
    }

    public function addExpense(array $data, User $actor): Expense
    {
        return Expense::create([
            'store_id' => $actor->store_id,
            'cash_session_id' => $data['cash_session_id'] ?? null,
            'amount' => $data['amount'],
            'category' => $data['category'],
            'description' => $data['description'] ?? null,
            'receipt_image_url' => $data['receipt_image_url'] ?? null,
            'recorded_by' => $actor->id,
            'expense_date' => $data['expense_date'] ?? now()->toDateString(),
        ]);
    }

    public function listExpenses(string|array $storeId, int $perPage = 20): LengthAwarePaginator
    {
        return $this->scopeByStore(Expense::query(), $storeId)
            ->orderByDesc('expense_date')
            ->paginate($perPage);
    }
}
