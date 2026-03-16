<?php

namespace App\Domain\Customer\Services;

use App\Domain\Customer\Models\Wishlist;
use App\Domain\Customer\Models\Appointment;
use App\Domain\Customer\Models\CfdConfiguration;
use App\Domain\Customer\Models\GiftRegistry;
use App\Domain\Customer\Models\GiftRegistryItem;
use App\Domain\Customer\Models\SignagePlaylist;
use App\Domain\Customer\Models\LoyaltyChallenge;
use App\Domain\Customer\Models\LoyaltyBadge;
use App\Domain\Customer\Models\LoyaltyTier;
use App\Domain\Customer\Models\CustomerChallengeProgress;
use App\Domain\Customer\Models\CustomerBadge;

class NiceToHaveService
{
    // ═══════════════ Wishlist ═══════════════

    public function getWishlist(string $storeId, string $customerId): array
    {
        return Wishlist::where('store_id', $storeId)
            ->where('customer_id', $customerId)
            ->orderByDesc('added_at')
            ->get()
            ->toArray();
    }

    public function addToWishlist(string $storeId, string $customerId, string $productId): Wishlist
    {
        return Wishlist::firstOrCreate([
            'store_id' => $storeId,
            'customer_id' => $customerId,
            'product_id' => $productId,
        ], [
            'added_at' => now(),
        ]);
    }

    public function removeFromWishlist(string $storeId, string $customerId, string $productId): bool
    {
        return Wishlist::where('store_id', $storeId)
            ->where('customer_id', $customerId)
            ->where('product_id', $productId)
            ->delete() > 0;
    }

    // ═══════════════ Appointments ═══════════════

    public function getAppointments(string $storeId, ?string $date = null): array
    {
        $query = Appointment::where('store_id', $storeId);
        if ($date) {
            $query->where('appointment_date', $date);
        }
        return $query->orderBy('appointment_date')
            ->orderBy('start_time')
            ->get()
            ->toArray();
    }

    public function createAppointment(array $data): Appointment
    {
        return Appointment::create($data);
    }

    public function updateAppointment(string $id, array $data): Appointment
    {
        $appointment = Appointment::findOrFail($id);
        $appointment->update($data);
        return $appointment->fresh();
    }

    public function cancelAppointment(string $id): Appointment
    {
        $appointment = Appointment::findOrFail($id);
        $appointment->update(['status' => 'cancelled']);
        return $appointment->fresh();
    }

    // ═══════════════ CFD Configuration ═══════════════

    public function getCfdConfig(string $storeId): array
    {
        $config = CfdConfiguration::where('store_id', $storeId)->first();
        if (! $config) {
            return [
                'is_enabled' => false,
                'target_monitor' => 'secondary',
                'theme_config' => [],
                'idle_content' => [],
                'idle_rotation_seconds' => 10,
            ];
        }
        return $config->toArray();
    }

    public function updateCfdConfig(string $storeId, array $data): array
    {
        $config = CfdConfiguration::updateOrCreate(
            ['store_id' => $storeId],
            $data,
        );
        return $config->toArray();
    }

    // ═══════════════ Gift Registry ═══════════════

    public function getRegistries(string $storeId, ?string $customerId = null): array
    {
        $query = GiftRegistry::where('store_id', $storeId);
        if ($customerId) {
            $query->where('customer_id', $customerId);
        }
        return $query->orderByDesc('event_date')->get()->toArray();
    }

    public function createRegistry(array $data): GiftRegistry
    {
        $data['share_code'] = strtoupper(substr(md5(uniqid()), 0, 8));
        return GiftRegistry::create($data);
    }

    public function getRegistryByShareCode(string $code): ?GiftRegistry
    {
        return GiftRegistry::where('share_code', $code)
            ->where('is_active', true)
            ->first();
    }

    public function addRegistryItem(string $registryId, array $data): GiftRegistryItem
    {
        $data['registry_id'] = $registryId;
        return GiftRegistryItem::create($data);
    }

    public function getRegistryItems(string $registryId): array
    {
        return GiftRegistryItem::where('registry_id', $registryId)
            ->get()
            ->toArray();
    }

    // ═══════════════ Digital Signage ═══════════════

    public function getPlaylists(string $storeId): array
    {
        return SignagePlaylist::where('store_id', $storeId)
            ->orderBy('name')
            ->get()
            ->toArray();
    }

    public function createPlaylist(array $data): SignagePlaylist
    {
        return SignagePlaylist::create($data);
    }

    public function updatePlaylist(string $id, array $data): SignagePlaylist
    {
        $playlist = SignagePlaylist::findOrFail($id);
        $playlist->update($data);
        return $playlist->fresh();
    }

    public function deletePlaylist(string $id): bool
    {
        return SignagePlaylist::findOrFail($id)->delete();
    }

    // ═══════════════ Gamification ═══════════════

    public function getChallenges(string $storeId): array
    {
        return LoyaltyChallenge::where('store_id', $storeId)
            ->where('is_active', true)
            ->get()
            ->toArray();
    }

    public function getBadges(string $storeId): array
    {
        return LoyaltyBadge::where('store_id', $storeId)
            ->get()
            ->toArray();
    }

    public function getTiers(string $storeId): array
    {
        return LoyaltyTier::where('store_id', $storeId)
            ->orderBy('min_points')
            ->get()
            ->toArray();
    }

    public function getCustomerProgress(string $customerId): array
    {
        return CustomerChallengeProgress::where('customer_id', $customerId)
            ->get()
            ->toArray();
    }

    public function getCustomerBadges(string $customerId): array
    {
        return CustomerBadge::where('customer_id', $customerId)
            ->orderByDesc('earned_at')
            ->get()
            ->toArray();
    }
}
