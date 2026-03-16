<?php

namespace App\Domain\IndustryRestaurant\Controllers\Api;

use App\Domain\IndustryRestaurant\Services\RestaurantService;
use App\Http\Controllers\Api\BaseApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RestaurantController extends BaseApiController
{
    public function __construct(private RestaurantService $service) {}

    // ── Tables ───────────────────────────────────────────

    public function listTables(Request $request): JsonResponse
    {
        $storeId = $request->user()->store_id;
        $data = $this->service->listTables($storeId, $request->only(['status', 'zone', 'is_active', 'per_page']));
        return $this->success($data, __('industry.tables_retrieved'));
    }

    public function createTable(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'table_number' => 'required|string|max:20',
            'display_name' => 'nullable|string|max:100',
            'seats' => 'required|integer|min:1',
            'zone' => 'nullable|string|max:50',
            'position_x' => 'nullable|numeric',
            'position_y' => 'nullable|numeric',
            'is_active' => 'nullable|boolean',
        ]);

        $validated['status'] = 'available';
        $validated['is_active'] = $validated['is_active'] ?? true;

        $storeId = $request->user()->store_id;
        $table = $this->service->createTable($storeId, $validated);
        return $this->created($table, __('industry.table_created'));
    }

    public function updateTable(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'display_name' => 'nullable|string|max:100',
            'seats' => 'nullable|integer|min:1',
            'zone' => 'nullable|string|max:50',
            'position_x' => 'nullable|numeric',
            'position_y' => 'nullable|numeric',
            'is_active' => 'nullable|boolean',
        ]);

        $table = $this->service->updateTable($id, $validated);
        return $this->success($table, __('industry.table_updated'));
    }

    public function updateTableStatus(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|string|in:available,occupied,reserved,cleaning',
        ]);

        $table = $this->service->updateTableStatus($id, $validated['status']);
        return $this->success($table, __('industry.table_status_updated'));
    }

    // ── Kitchen Tickets ──────────────────────────────────

    public function listKitchenTickets(Request $request): JsonResponse
    {
        $storeId = $request->user()->store_id;
        $data = $this->service->listKitchenTickets($storeId, $request->only(['status', 'station', 'table_id', 'per_page']));
        return $this->success($data, __('industry.kitchen_tickets_retrieved'));
    }

    public function createKitchenTicket(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'order_id' => 'nullable|string',
            'table_id' => 'nullable|string',
            'ticket_number' => 'required|string|max:50',
            'items_json' => 'required|array',
            'station' => 'nullable|string|max:50',
            'course_number' => 'nullable|integer|min:1',
            'fire_at' => 'nullable|date',
        ]);

        $validated['status'] = 'pending';

        $storeId = $request->user()->store_id;
        $ticket = $this->service->createKitchenTicket($storeId, $validated);
        return $this->created($ticket, __('industry.kitchen_ticket_created'));
    }

    public function updateKitchenTicketStatus(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|string|in:pending,preparing,ready,served',
        ]);

        $ticket = $this->service->updateKitchenTicketStatus($id, $validated['status']);
        return $this->success($ticket, __('industry.kitchen_ticket_status_updated'));
    }

    // ── Reservations ─────────────────────────────────────

    public function listReservations(Request $request): JsonResponse
    {
        $storeId = $request->user()->store_id;
        $data = $this->service->listReservations($storeId, $request->only(['status', 'reservation_date', 'search', 'per_page']));
        return $this->success($data, __('industry.reservations_retrieved'));
    }

    public function createReservation(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'table_id' => 'nullable|string',
            'customer_name' => 'required|string|max:255',
            'customer_phone' => 'nullable|string|max:20',
            'party_size' => 'required|integer|min:1',
            'reservation_date' => 'required|date',
            'reservation_time' => 'required|string|max:10',
            'duration_minutes' => 'nullable|integer|min:15',
            'notes' => 'nullable|string',
        ]);

        $validated['status'] = 'confirmed';

        $storeId = $request->user()->store_id;
        $reservation = $this->service->createReservation($storeId, $validated);
        return $this->created($reservation, __('industry.reservation_created'));
    }

    public function updateReservation(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'table_id' => 'nullable|string',
            'customer_name' => 'nullable|string|max:255',
            'customer_phone' => 'nullable|string|max:20',
            'party_size' => 'nullable|integer|min:1',
            'reservation_date' => 'nullable|date',
            'reservation_time' => 'nullable|string|max:10',
            'duration_minutes' => 'nullable|integer|min:15',
            'notes' => 'nullable|string',
        ]);

        $reservation = $this->service->updateReservation($id, $validated);
        return $this->success($reservation, __('industry.reservation_updated'));
    }

    public function updateReservationStatus(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|string|in:confirmed,seated,completed,cancelled,no_show',
        ]);

        $reservation = $this->service->updateReservationStatus($id, $validated['status']);
        return $this->success($reservation, __('industry.reservation_status_updated'));
    }

    // ── Open Tabs ────────────────────────────────────────

    public function listOpenTabs(Request $request): JsonResponse
    {
        $storeId = $request->user()->store_id;
        $data = $this->service->listOpenTabs($storeId, $request->only(['status', 'table_id', 'per_page']));
        return $this->success($data, __('industry.tabs_retrieved'));
    }

    public function openTab(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'order_id' => 'nullable|string',
            'customer_name' => 'nullable|string|max:255',
            'table_id' => 'nullable|string',
        ]);

        $storeId = $request->user()->store_id;
        $tab = $this->service->openTab($storeId, $validated);
        return $this->created($tab, __('industry.tab_opened'));
    }

    public function closeTab(string $id): JsonResponse
    {
        $tab = $this->service->closeTab($id);
        return $this->success($tab, __('industry.tab_closed'));
    }
}
