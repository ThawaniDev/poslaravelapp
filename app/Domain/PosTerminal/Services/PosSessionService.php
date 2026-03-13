<?php

namespace App\Domain\PosTerminal\Services;

use App\Domain\Auth\Models\User;
use App\Domain\PosTerminal\Models\PosSession;
use App\Domain\Security\Enums\SessionStatus;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class PosSessionService
{
    public function list(string $storeId, int $perPage = 20): LengthAwarePaginator
    {
        return PosSession::where('store_id', $storeId)
            ->orderByDesc('opened_at')
            ->paginate($perPage);
    }

    public function find(string $sessionId): PosSession
    {
        return PosSession::with('transactions')->findOrFail($sessionId);
    }

    public function open(array $data, User $actor): PosSession
    {
        // Check for existing open session on same register
        if (!empty($data['register_id'])) {
            $existing = PosSession::where('store_id', $actor->store_id)
                ->where('register_id', $data['register_id'])
                ->where('status', SessionStatus::Open)
                ->first();

            if ($existing) {
                throw new \RuntimeException('There is already an open session on this register.');
            }
        }

        return PosSession::create([
            'store_id' => $actor->store_id,
            'register_id' => $data['register_id'] ?? null,
            'cashier_id' => $actor->id,
            'status' => SessionStatus::Open,
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
        if ($session->status !== SessionStatus::Open) {
            throw new \RuntimeException('This session is already closed.');
        }

        $expectedCash = ($session->opening_cash ?? 0)
            + ($session->total_cash_sales ?? 0)
            - ($session->total_refunds ?? 0);

        $closingCash = $data['closing_cash'] ?? 0;
        $difference = $closingCash - $expectedCash;

        $session->update([
            'status' => SessionStatus::Closed,
            'closing_cash' => $closingCash,
            'expected_cash' => $expectedCash,
            'cash_difference' => $difference,
            'closed_at' => now(),
        ]);

        return $session->fresh();
    }
}
