<?php

namespace App\Domain\ZatcaCompliance\Services;

use App\Domain\ZatcaCompliance\Models\ZatcaDevice;
use App\Domain\ZatcaCompliance\Models\ZatcaInvoice;
use Illuminate\Support\Facades\DB;

/**
 * Maintains the per-device invoice hash chain (PIH — Previous Invoice Hash)
 * and the strictly-sequential ICV counter.
 *
 *   CurrentInvoice.PIH   = SHA-256(PreviousInvoiceXML)
 *   CurrentInvoice.ICV   = previous ICV + 1
 *
 * The very first invoice on a device uses the ZATCA seed PIH
 * (base64 SHA-256 of "0").
 *
 * The service is the single source of truth for both values. Detecting a
 * mismatch between the in-memory chain and persisted state flips the
 * device into the tampered/locked state per Spec Sec 17.
 */
class HashChainService
{
    public const SEED_PIH = '0';

    public function seedHash(): string
    {
        return base64_encode(hash('sha256', self::SEED_PIH, true));
    }

    /**
     * Atomically reserve the next (icv, pih) pair for a device.
     *
     * @return array{icv:int, pih:string}
     */
    public function reserveNext(ZatcaDevice $device): array
    {
        return DB::transaction(function () use ($device) {
            $locked = ZatcaDevice::query()
                ->whereKey($device->id)
                ->lockForUpdate()
                ->first();

            $pih = $locked->current_pih ?: $this->seedHash();
            $nextIcv = (int) $locked->current_icv + 1;

            return ['icv' => $nextIcv, 'pih' => $pih];
        });
    }

    /**
     * Persist the resulting invoice hash on the device after successful
     * signing, so the next invoice picks it up as PIH.
     */
    public function commit(ZatcaDevice $device, int $icv, string $invoiceHash): void
    {
        DB::transaction(function () use ($device, $icv, $invoiceHash) {
            $locked = ZatcaDevice::query()
                ->whereKey($device->id)
                ->lockForUpdate()
                ->first();

            // Detect concurrent / out-of-order ICV writes — a strong signal
            // of tampering or duplicated submission. Lock the device.
            if ($icv !== ((int) $locked->current_icv) + 1) {
                $locked->update([
                    'is_tampered' => true,
                    'tamper_reason' => 'icv_out_of_sequence: expected '
                        . (((int) $locked->current_icv) + 1) . ', got ' . $icv,
                ]);
                throw new \RuntimeException('ZATCA hash chain compromised: ICV out of sequence');
            }

            $locked->update([
                'current_icv' => $icv,
                'current_pih' => $invoiceHash,
            ]);
        });
    }

    /**
     * Walk the persisted chain for a device and verify each PIH matches
     * the previous invoice's hash. Returns the first broken invoice, or
     * null if the chain is intact.
     */
    public function verifyChain(string $deviceId): ?ZatcaInvoice
    {
        $invoices = ZatcaInvoice::where('device_id', $deviceId)
            ->orderBy('icv')
            ->get(['id', 'icv', 'invoice_hash', 'previous_invoice_hash']);

        if ($invoices->isEmpty()) {
            return null;
        }

        $expectedPih = $this->seedHash();
        foreach ($invoices as $inv) {
            if ($inv->previous_invoice_hash !== $expectedPih) {
                return $inv;
            }
            $expectedPih = $inv->invoice_hash;
        }
        return null;
    }
}
