<?php

namespace App\Filament\Pages;

use App\Domain\ThawaniIntegration\Models\ThawaniStoreConfig;
use App\Domain\ThawaniIntegration\Models\ThawaniSyncLog;
use Filament\Pages\Page;
use Livewire\WithPagination;

class ThawaniSyncLogs extends Page
{
    use WithPagination;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?int $navigationSort = 5;
    protected static string $view = 'filament.pages.thawani-sync-logs';

    public static function getNavigationGroup(): ?string
    {
        return __('nav.group_thawani');
    }

    public static function getNavigationLabel(): string
    {
        return __('thawani.sync_logs');
    }

    public function getTitle(): string
    {
        return __('thawani.sync_logs');
    }

    public static function canAccess(): bool
    {
        $user = auth('admin')->user();
        return $user && $user->hasAnyPermission(['thawani.view_sync_logs', 'thawani.view_dashboard', 'integrations.view', 'integrations.manage']);
    }

    public ?string $selectedStore = null;
    public string $filterEntityType = '';
    public string $filterStatus = '';
    public string $filterDirection = '';
    public string $filterDateFrom = '';
    public string $filterDateTo = '';

    public function getViewData(): array
    {
        $query = ThawaniSyncLog::query()->latest();

        if ($this->selectedStore) {
            $query->where('store_id', $this->selectedStore);
        }
        if ($this->filterEntityType) {
            $query->where('entity_type', $this->filterEntityType);
        }
        if ($this->filterStatus) {
            $query->where('status', $this->filterStatus);
        }
        if ($this->filterDirection) {
            $query->where('direction', $this->filterDirection);
        }
        if ($this->filterDateFrom) {
            $query->whereDate('created_at', '>=', $this->filterDateFrom);
        }
        if ($this->filterDateTo) {
            $query->whereDate('created_at', '<=', $this->filterDateTo);
        }

        $stores = ThawaniStoreConfig::where('is_connected', true)->with('store')->get();

        return [
            'logs' => $query->paginate(25),
            'stores' => $stores,
            'stats' => [
                'total' => ThawaniSyncLog::count(),
                'success' => ThawaniSyncLog::where('status', 'success')->count(),
                'failed' => ThawaniSyncLog::where('status', 'failed')->count(),
                'today' => ThawaniSyncLog::whereDate('created_at', today())->count(),
            ],
        ];
    }

    public function clearFilters(): void
    {
        $this->selectedStore = null;
        $this->filterEntityType = '';
        $this->filterStatus = '';
        $this->filterDirection = '';
        $this->filterDateFrom = '';
        $this->filterDateTo = '';
        $this->resetPage();
    }

    public function updatedSelectedStore(): void
    {
        $this->resetPage();
    }

    public function updatedFilterEntityType(): void
    {
        $this->resetPage();
    }

    public function updatedFilterStatus(): void
    {
        $this->resetPage();
    }

    public function updatedFilterDirection(): void
    {
        $this->resetPage();
    }
}
