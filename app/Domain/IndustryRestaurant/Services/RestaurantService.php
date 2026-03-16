<?php

namespace App\Domain\IndustryRestaurant\Services;

use App\Domain\IndustryRestaurant\Models\RestaurantTable;
use App\Domain\IndustryRestaurant\Models\KitchenTicket;
use App\Domain\IndustryRestaurant\Models\TableReservation;
use App\Domain\IndustryRestaurant\Models\OpenTab;

class RestaurantService
{
    // ── Tables ───────────────────────────────────────────

    public function listTables(string $storeId, array $filters = [])
    {
        $query = RestaurantTable::where('store_id', $storeId);

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['zone'])) {
            $query->where('zone', $filters['zone']);
        }
        if (isset($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }

        return $query->orderBy('table_number')->paginate($filters['per_page'] ?? 50);
    }

    public function createTable(string $storeId, array $data): RestaurantTable
    {
        return RestaurantTable::create(array_merge($data, ['store_id' => $storeId]));
    }

    public function updateTable(string $id, array $data): RestaurantTable
    {
        $table = RestaurantTable::findOrFail($id);
        $table->update($data);
        return $table->fresh();
    }

    public function updateTableStatus(string $id, string $status): RestaurantTable
    {
        $table = RestaurantTable::findOrFail($id);
        $table->update(['status' => $status]);
        return $table->fresh();
    }

    // ── Kitchen Tickets ──────────────────────────────────

    public function listKitchenTickets(string $storeId, array $filters = [])
    {
        $query = KitchenTicket::where('store_id', $storeId);

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['station'])) {
            $query->where('station', $filters['station']);
        }
        if (! empty($filters['table_id'])) {
            $query->where('table_id', $filters['table_id']);
        }

        return $query->orderByDesc('created_at')->paginate($filters['per_page'] ?? 15);
    }

    public function createKitchenTicket(string $storeId, array $data): KitchenTicket
    {
        return KitchenTicket::create(array_merge($data, ['store_id' => $storeId]));
    }

    public function updateKitchenTicketStatus(string $id, string $status): KitchenTicket
    {
        $ticket = KitchenTicket::findOrFail($id);
        $updateData = ['status' => $status];
        if ($status === 'ready' || $status === 'served') {
            $updateData['completed_at'] = now();
        }
        $ticket->update($updateData);
        return $ticket->fresh();
    }

    // ── Reservations ─────────────────────────────────────

    public function listReservations(string $storeId, array $filters = [])
    {
        $query = TableReservation::where('store_id', $storeId);

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['reservation_date'])) {
            $query->where('reservation_date', $filters['reservation_date']);
        }
        if (! empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('customer_name', 'like', '%' . $filters['search'] . '%')
                  ->orWhere('customer_phone', 'like', '%' . $filters['search'] . '%');
            });
        }

        return $query->orderBy('reservation_date')->orderBy('reservation_time')->paginate($filters['per_page'] ?? 15);
    }

    public function createReservation(string $storeId, array $data): TableReservation
    {
        return TableReservation::create(array_merge($data, ['store_id' => $storeId]));
    }

    public function updateReservation(string $id, array $data): TableReservation
    {
        $reservation = TableReservation::findOrFail($id);
        $reservation->update($data);
        return $reservation->fresh();
    }

    public function updateReservationStatus(string $id, string $status): TableReservation
    {
        $reservation = TableReservation::findOrFail($id);
        $reservation->update(['status' => $status]);
        return $reservation->fresh();
    }

    // ── Open Tabs ────────────────────────────────────────

    public function listOpenTabs(string $storeId, array $filters = [])
    {
        $query = OpenTab::where('store_id', $storeId);

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['table_id'])) {
            $query->where('table_id', $filters['table_id']);
        }

        return $query->orderByDesc('opened_at')->paginate($filters['per_page'] ?? 15);
    }

    public function openTab(string $storeId, array $data): OpenTab
    {
        return OpenTab::create(array_merge($data, [
            'store_id' => $storeId,
            'status' => 'open',
            'opened_at' => now(),
        ]));
    }

    public function closeTab(string $id): OpenTab
    {
        $tab = OpenTab::findOrFail($id);
        $tab->update([
            'status' => 'closed',
            'closed_at' => now(),
        ]);
        return $tab->fresh();
    }
}
