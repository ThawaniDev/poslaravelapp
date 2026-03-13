<?php

namespace App\Domain\Payment\Services;

use App\Domain\Auth\Models\User;
use App\Domain\Payment\Enums\GiftCardStatus;
use App\Domain\Payment\Models\GiftCard;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

class GiftCardService
{
    public function list(string $organizationId, int $perPage = 20): LengthAwarePaginator
    {
        return GiftCard::where('organization_id', $organizationId)
            ->orderByDesc('expires_at')
            ->paginate($perPage);
    }

    public function find(string $id): GiftCard
    {
        return GiftCard::findOrFail($id);
    }

    public function findByCode(string $code): ?GiftCard
    {
        return GiftCard::where('code', $code)->first();
    }

    public function issue(array $data, User $actor): GiftCard
    {
        $code = $data['code'] ?? $this->generateCode();

        // Ensure code is unique
        while (GiftCard::where('code', $code)->exists()) {
            $code = $this->generateCode();
        }

        return GiftCard::create([
            'organization_id' => $actor->organization_id,
            'code' => $code,
            'barcode' => $data['barcode'] ?? $code,
            'initial_amount' => $data['amount'],
            'balance' => $data['amount'],
            'recipient_name' => $data['recipient_name'] ?? null,
            'status' => GiftCardStatus::Active,
            'issued_by' => $actor->id,
            'issued_at_store' => $actor->store_id,
            'expires_at' => $data['expires_at'] ?? now()->addMonths(12)->toDateString(),
        ]);
    }

    public function checkBalance(string $code): array
    {
        $card = GiftCard::where('code', $code)->firstOrFail();

        return [
            'code' => $card->code,
            'balance' => $card->balance,
            'initial_amount' => $card->initial_amount,
            'status' => $card->status->value,
            'expires_at' => $card->expires_at?->toDateString(),
            'is_expired' => $card->expires_at && $card->expires_at->isPast(),
        ];
    }

    public function redeem(string $code, float $amount): GiftCard
    {
        $card = GiftCard::where('code', $code)->firstOrFail();

        if ($card->status !== GiftCardStatus::Active) {
            throw new \RuntimeException('Gift card is not active.');
        }

        if ($card->expires_at && $card->expires_at->isPast()) {
            $card->update(['status' => GiftCardStatus::Expired]);
            throw new \RuntimeException('Gift card has expired.');
        }

        $balance = (float) $card->balance;
        if ($amount > $balance) {
            throw new \RuntimeException("Insufficient balance. Available: {$balance}");
        }

        $newBalance = $balance - $amount;
        $updateData = ['balance' => $newBalance];

        if ($newBalance <= 0) {
            $updateData['status'] = GiftCardStatus::Redeemed;
        }

        $card->update($updateData);

        return $card->fresh();
    }

    public function deactivate(string $id): GiftCard
    {
        $card = GiftCard::findOrFail($id);
        $card->update(['status' => GiftCardStatus::Deactivated]);
        return $card->fresh();
    }

    private function generateCode(): string
    {
        return 'GC-' . strtoupper(Str::random(8));
    }
}
