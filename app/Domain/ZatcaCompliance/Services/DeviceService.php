<?php

namespace App\Domain\ZatcaCompliance\Services;

use App\Domain\ZatcaCompliance\Enums\ZatcaDeviceStatus;
use App\Domain\ZatcaCompliance\Models\ZatcaDevice;
use Illuminate\Support\Str;

/**
 * EGS device lifecycle: provision (admin issues an activation PIN),
 * activate (POS terminal redeems the PIN with its hardware serial),
 * tamper detection (Spec Sec 17 — POS locks until admin reset).
 */
class DeviceService
{
    public function __construct(private readonly HashChainService $chain) {}

    /**
     * Provision a new device. Returns the activation PIN that must be
     * entered on the terminal during first boot. The device starts in
     * Pending state and only becomes Active once the PIN is redeemed.
     */
    public function provision(string $storeId, string $environment = 'sandbox'): ZatcaDevice
    {
        return ZatcaDevice::create([
            'store_id' => $storeId,
            'device_uuid' => (string) Str::uuid(),
            'activation_code' => strtoupper(Str::random(12)),
            'environment' => $environment,
            'status' => ZatcaDeviceStatus::Pending,
            'current_icv' => 0,
            'current_pih' => $this->chain->seedHash(),
        ]);
    }

    /**
     * Redeem an activation PIN. Binds the hardware serial and marks the
     * device as Active. Re-activation with a different serial is rejected
     * to prevent silent device hijacking.
     */
    public function activate(string $storeId, string $activationCode, ?string $hardwareSerial): ZatcaDevice
    {
        $device = ZatcaDevice::where('store_id', $storeId)
            ->where('activation_code', $activationCode)
            ->first();
        if (! $device) {
            throw new \RuntimeException('zatca_device_invalid_activation_code');
        }
        if ($device->hardware_serial && $hardwareSerial && $device->hardware_serial !== $hardwareSerial) {
            throw new \RuntimeException('zatca_device_serial_mismatch');
        }
        $device->update([
            'hardware_serial' => $hardwareSerial ?? $device->hardware_serial,
            'activated_at' => $device->activated_at ?? now(),
            'status' => ZatcaDeviceStatus::Active,
        ]);
        return $device->refresh();
    }

    public function flagTamper(ZatcaDevice $device, string $reason): void
    {
        $device->update([
            'is_tampered' => true,
            'tamper_reason' => $reason,
            'status' => ZatcaDeviceStatus::Tampered,
        ]);
    }

    /**
     * Admin reset clears the tamper flag and re-seeds the hash chain so
     * the device can resume operation.
     */
    public function resetTamper(ZatcaDevice $device): ZatcaDevice
    {
        $device->update([
            'is_tampered' => false,
            'tamper_reason' => null,
            'status' => ZatcaDeviceStatus::Active,
            'current_pih' => $this->chain->seedHash(),
        ]);
        return $device->refresh();
    }

    /**
     * Resolve the device that should be charged with a given submission.
     * Falls back to the store's main device, auto-provisioning one if no
     * device has been registered yet (so single-terminal tenants get a
     * sensible default without an explicit activation flow).
     */
    public function resolveForStore(string $storeId): ZatcaDevice
    {
        // Tampered devices must short-circuit further submissions until
        // an admin resets them — they take precedence over freshly
        // provisioning a new device behind their back.
        $tampered = ZatcaDevice::where('store_id', $storeId)
            ->where('is_tampered', true)
            ->orderBy('created_at')
            ->first();
        if ($tampered) {
            return $tampered;
        }
        $device = ZatcaDevice::where('store_id', $storeId)
            ->where('status', ZatcaDeviceStatus::Active)
            ->orderBy('created_at')
            ->first();
        if ($device) {
            return $device;
        }
        $device = $this->provision($storeId);
        $device->update([
            'status' => ZatcaDeviceStatus::Active,
            'activated_at' => now(),
            'hardware_serial' => 'auto-provisioned',
        ]);
        return $device->refresh();
    }
}
