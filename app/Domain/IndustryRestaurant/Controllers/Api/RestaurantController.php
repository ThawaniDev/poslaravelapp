<?php

namespace App\Domain\IndustryRestaurant\Controllers\Api;

use App\Domain\IndustryRestaurant\Requests\CreateRestaurantTableRequest;
use App\Domain\IndustryRestaurant\Requests\CreateTableReservationRequest;
use App\Domain\IndustryRestaurant\Requests\UpdateRestaurantTableRequest;
use App\Domain\IndustryRestaurant\Requests\UpdateTableReservationRequest;
use App\Domain\IndustryRestaurant\Requests\CreateKitchenTicketRequest;
use App\Domain\IndustryRestaurant\Requests\CreateOpenTabRequest;
use App\Domain\IndustryRestaurant\Resources\KitchenTicketResource;
use App\Domain\IndustryRestaurant\Resources\OpenTabResource;
use App\Domain\IndustryRestaurant\Resources\RestaurantTableResource;
use App\Domain\IndustryRestaurant\Resources\TableReservationResource;
use App\Domain\IndustryRestaurant\Services\RestaurantService;
use App\Http\Controllers\Api\BaseApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RestaurantController extends BaseApiController
{
    public function __construct(private readonly RestaurantService $service) {}

    // ── Tables ───────────────────────────────────────────

    public function listTables(Request $request): JsonResponse
    {
        $storeId = $this->resolvedStoreId($request) ?? $request->user()->store_id;
        $paginator = $this->service->listTables($storeId, $request->only(['status', 'zone', 'is_active', 'per_page']));

        $data = $paginator->toArray();
        $data['data'] = RestaurantTableResource::collection($paginator->items())->resolve();

        return $this->success($data, __('industry.tables_retrieved'));
    }

    public function createTable(CreateRestaurantTableRequest $request): JsonResponse
    {
        $storeId = $this->resolvedStoreId($request) ?? $request->user()->store_id;
        $table = $this->service->createTable($storeId, $request->validated());
        return $this->created(new RestaurantTableResource($table), __('industry.table_created'));
    }

    public function updateTable(UpdateRestaurantTableRequest $request, string $id): JsonResponse
    {
        $table = $this->service->updateTable($id, $this->resolvedStoreId($request) ?? $request->user()->store_id, $request->validated());
        return $this->success(new RestaurantTableResource($table), __('industry.table_updated'));
    }

    public function updateTableStatus(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|string|in:available,occupied,reserved,cleaning',
        ]);

        $table = $this->service->updateTableStatus($id, $this->resolvedStoreId($request) ?? $request->user()->store_id, $validated['status']);
        return $this->success(new RestaurantTableResource($table), __('industry.table_status_updated'));
    }

    // ── Kitchen Tickets ──────────────────────────────────

    public function listKitchenTickets(Request $request): JsonResponse
    {
        $storeId = $this->resolvedStoreId($request) ?? $request->user()->store_id;
        $paginator = $this->service->listKitchenTickets($storeId, $request->only(['status', 'station', 'table_id', 'per_page']));

        $data = $paginator->toArray();
        $data['data'] = KitchenTicketResource::collection($paginator->items())->resolve();

        return $this->success($data, __('industry.kitchen_tickets_retrieved'));
    }

    public function createKitchenTicket(CreateKitchenTicketRequest $request): JsonResponse
    {
        $storeId = $this->resolvedStoreId($request) ?? $request->user()->store_id;
        $ticket = $this->service->createKitchenTicket($storeId, $request->validated());
        return $this->created(new KitchenTicketResource($ticket), __('industry.kitchen_ticket_created'));
    }

    public function updateKitchenTicketStatus(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|string|in:pending,preparing,ready,served',
        ]);

        $ticket = $this->service->updateKitchenTicketStatus($id, $this->resolvedStoreId($request) ?? $request->user()->store_id, $validated['status']);
        return $this->success(new KitchenTicketResource($ticket), __('industry.kitchen_ticket_status_updated'));
    }

    // ── Reservations ─────────────────────────────────────

    public function listReservations(Request $request): JsonResponse
    {
        $storeId = $this->resolvedStoreId($request) ?? $request->user()->store_id;
        $paginator = $this->service->listReservations($storeId, $request->only(['status', 'reservation_date', 'search', 'per_page']));

        $data = $paginator->toArray();
        $data['data'] = TableReservationResource::collection($paginator->items())->resolve();

        return $this->success($data, __('industry.reservations_retrieved'));
    }

    public function createReservation(CreateTableReservationRequest $request): JsonResponse
    {
        $storeId = $this->resolvedStoreId($request) ?? $request->user()->store_id;
        $reservation = $this->service->createReservation($storeId, $request->validated());
        return $this->created(new TableReservationResource($reservation), __('industry.reservation_created'));
    }

    public function updateReservation(UpdateTableReservationRequest $request, string $id): JsonResponse
    {
        $reservation = $this->service->updateReservation($id, $this->resolvedStoreId($request) ?? $request->user()->store_id, $request->validated());
        return $this->success(new TableReservationResource($reservation), __('industry.reservation_updated'));
    }

    public function updateReservationStatus(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|string|in:confirmed,seated,completed,cancelled,no_show',
        ]);

        $reservation = $this->service->updateReservationStatus($id, $this->resolvedStoreId($request) ?? $request->user()->store_id, $validated['status']);
        return $this->success(new TableReservationResource($reservation), __('industry.reservation_status_updated'));
    }

    // ── Open Tabs ────────────────────────────────────────

    public function listOpenTabs(Request $request): JsonResponse
    {
        $storeId = $this->resolvedStoreId($request) ?? $request->user()->store_id;
        $paginator = $this->service->listOpenTabs($storeId, $request->only(['status', 'table_id', 'per_page']));

        $data = $paginator->toArray();
        $data['data'] = OpenTabResource::collection($paginator->items())->resolve();

        return $this->success($data, __('industry.tabs_retrieved'));
    }

    public function openTab(CreateOpenTabRequest $request): JsonResponse
    {
        $storeId = $this->resolvedStoreId($request) ?? $request->user()->store_id;
        $tab = $this->service->openTab($storeId, $request->validated());
        return $this->created(new OpenTabResource($tab), __('industry.tab_opened'));
    }

    public function closeTab(Request $request, string $id): JsonResponse
    {
        $tab = $this->service->closeTab($id, $this->resolvedStoreId($request) ?? $request->user()->store_id);
        return $this->success(new OpenTabResource($tab), __('industry.tab_closed'));
    }
}
