<?php

namespace App\Domain\Customer\Controllers\Api;

use App\Domain\Customer\Services\NiceToHaveService;
use App\Http\Controllers\Api\BaseApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NiceToHaveController extends BaseApiController
{
    public function __construct(private NiceToHaveService $service) {}

    // ═══════════════ Wishlist ═══════════════

    public function wishlist(Request $request): JsonResponse
    {
        $items = $this->service->getWishlist(
            $request->user()->store_id,
            $request->query('customer_id'),
        );
        return $this->success($items, __('nice_to_have.wishlist_loaded'));
    }

    public function addToWishlist(Request $request): JsonResponse
    {
        $request->validate([
            'customer_id' => 'required|uuid',
            'product_id' => 'required|uuid',
        ]);
        $item = $this->service->addToWishlist(
            $request->user()->store_id,
            $request->input('customer_id'),
            $request->input('product_id'),
        );
        return $this->created($item->toArray(), __('nice_to_have.wishlist_added'));
    }

    public function removeFromWishlist(Request $request): JsonResponse
    {
        $request->validate([
            'customer_id' => 'required|uuid',
            'product_id' => 'required|uuid',
        ]);
        $this->service->removeFromWishlist(
            $request->user()->store_id,
            $request->input('customer_id'),
            $request->input('product_id'),
        );
        return $this->success(null, __('nice_to_have.wishlist_removed'));
    }

    // ═══════════════ Appointments ═══════════════

    public function appointments(Request $request): JsonResponse
    {
        $items = $this->service->getAppointments(
            $request->user()->store_id,
            $request->query('date'),
        );
        return $this->success($items, __('nice_to_have.appointments_loaded'));
    }

    public function createAppointment(Request $request): JsonResponse
    {
        $request->validate([
            'customer_id' => 'required|uuid',
            'appointment_date' => 'required|date',
            'start_time' => 'required|string',
            'end_time' => 'required|string',
            'status' => 'sometimes|string|in:scheduled,confirmed,completed,no_show,cancelled',
            'notes' => 'nullable|string|max:500',
        ]);
        $data = $request->only(['customer_id', 'appointment_date', 'start_time', 'end_time', 'status', 'notes']);
        $data['store_id'] = $request->user()->store_id;
        $data['status'] = $data['status'] ?? 'scheduled';

        $appt = $this->service->createAppointment($data);
        return $this->created($appt->toArray(), __('nice_to_have.appointment_created'));
    }

    public function updateAppointment(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'status' => 'sometimes|string|in:scheduled,confirmed,completed,no_show,cancelled',
            'notes' => 'nullable|string|max:500',
            'appointment_date' => 'sometimes|date',
            'start_time' => 'sometimes|string',
            'end_time' => 'sometimes|string',
        ]);
        $appt = $this->service->updateAppointment($id, $request->only([
            'status', 'notes', 'appointment_date', 'start_time', 'end_time',
        ]));
        return $this->success($appt->toArray(), __('nice_to_have.appointment_updated'));
    }

    public function cancelAppointment(string $id): JsonResponse
    {
        $appt = $this->service->cancelAppointment($id);
        return $this->success($appt->toArray(), __('nice_to_have.appointment_cancelled'));
    }

    // ═══════════════ CFD Configuration ═══════════════

    public function cfdConfig(Request $request): JsonResponse
    {
        $config = $this->service->getCfdConfig($request->user()->store_id);
        return $this->success($config, __('nice_to_have.cfd_loaded'));
    }

    public function updateCfdConfig(Request $request): JsonResponse
    {
        $request->validate([
            'is_enabled' => 'sometimes|boolean',
            'target_monitor' => 'sometimes|string|max:50',
            'theme_config' => 'sometimes|array',
            'idle_content' => 'sometimes|array',
            'idle_rotation_seconds' => 'sometimes|integer|min:3|max:120',
        ]);
        $config = $this->service->updateCfdConfig(
            $request->user()->store_id,
            $request->only(['is_enabled', 'target_monitor', 'theme_config', 'idle_content', 'idle_rotation_seconds']),
        );
        return $this->success($config, __('nice_to_have.cfd_updated'));
    }

    // ═══════════════ Gift Registry ═══════════════

    public function registries(Request $request): JsonResponse
    {
        $items = $this->service->getRegistries(
            $request->user()->store_id,
            $request->query('customer_id'),
        );
        return $this->success($items, __('nice_to_have.registries_loaded'));
    }

    public function createRegistry(Request $request): JsonResponse
    {
        $request->validate([
            'customer_id' => 'required|uuid',
            'name' => 'required|string|max:200',
            'event_type' => 'required|string',
            'event_date' => 'required|date',
        ]);
        $data = $request->only(['customer_id', 'name', 'event_type', 'event_date']);
        $data['store_id'] = $request->user()->store_id;
        $data['is_active'] = true;

        $reg = $this->service->createRegistry($data);
        return $this->created($reg->toArray(), __('nice_to_have.registry_created'));
    }

    public function registryByShareCode(string $code): JsonResponse
    {
        $reg = $this->service->getRegistryByShareCode($code);
        if (! $reg) {
            return $this->notFound(__('nice_to_have.registry_not_found'));
        }
        return $this->success($reg->toArray(), __('nice_to_have.registry_loaded'));
    }

    public function addRegistryItem(Request $request, string $registryId): JsonResponse
    {
        $request->validate([
            'product_id' => 'required|uuid',
            'quantity_desired' => 'sometimes|integer|min:1',
        ]);
        $item = $this->service->addRegistryItem($registryId, $request->only(['product_id', 'quantity_desired']));
        return $this->created($item->toArray(), __('nice_to_have.registry_item_added'));
    }

    public function registryItems(string $registryId): JsonResponse
    {
        $items = $this->service->getRegistryItems($registryId);
        return $this->success($items, __('nice_to_have.registry_items_loaded'));
    }

    // ═══════════════ Digital Signage ═══════════════

    public function playlists(Request $request): JsonResponse
    {
        $items = $this->service->getPlaylists($request->user()->store_id);
        return $this->success($items, __('nice_to_have.playlists_loaded'));
    }

    public function createPlaylist(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:200',
            'slides' => 'required|array',
            'schedule' => 'sometimes|array',
        ]);
        $data = $request->only(['name', 'slides', 'schedule']);
        $data['store_id'] = $request->user()->store_id;
        $data['is_active'] = true;

        $pl = $this->service->createPlaylist($data);
        return $this->created($pl->toArray(), __('nice_to_have.playlist_created'));
    }

    public function updatePlaylist(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'name' => 'sometimes|string|max:200',
            'slides' => 'sometimes|array',
            'schedule' => 'sometimes|array',
            'is_active' => 'sometimes|boolean',
        ]);
        $pl = $this->service->updatePlaylist($id, $request->only(['name', 'slides', 'schedule', 'is_active']));
        return $this->success($pl->toArray(), __('nice_to_have.playlist_updated'));
    }

    public function deletePlaylist(string $id): JsonResponse
    {
        $this->service->deletePlaylist($id);
        return $this->success(null, __('nice_to_have.playlist_deleted'));
    }

    // ═══════════════ Gamification ═══════════════

    public function challenges(Request $request): JsonResponse
    {
        $items = $this->service->getChallenges($request->user()->store_id);
        return $this->success($items, __('nice_to_have.challenges_loaded'));
    }

    public function badges(Request $request): JsonResponse
    {
        $items = $this->service->getBadges($request->user()->store_id);
        return $this->success($items, __('nice_to_have.badges_loaded'));
    }

    public function tiers(Request $request): JsonResponse
    {
        $items = $this->service->getTiers($request->user()->store_id);
        return $this->success($items, __('nice_to_have.tiers_loaded'));
    }

    public function customerProgress(string $customerId): JsonResponse
    {
        $items = $this->service->getCustomerProgress($customerId);
        return $this->success($items, __('nice_to_have.progress_loaded'));
    }

    public function customerBadges(string $customerId): JsonResponse
    {
        $items = $this->service->getCustomerBadges($customerId);
        return $this->success($items, __('nice_to_have.badges_loaded'));
    }
}
