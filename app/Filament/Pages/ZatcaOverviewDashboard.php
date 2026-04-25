<?php

namespace App\Filament\Pages;

use App\Domain\ZatcaCompliance\Services\ZatcaComplianceService;
use Filament\Pages\Page;

class ZatcaOverviewDashboard extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $navigationGroup = null;

    /**
     * Make this page available at /admin/zatca/overview
     * (otherwise Filament derives the slug from the class name).
     */
    protected static ?string $slug = 'zatca/overview';

    protected static ?int $navigationSort = 12;

    protected static string $view = 'filament.pages.zatca-overview-dashboard';

    public static function getNavigationGroup(): ?string
    {
        return __('nav.group_analytics');
    }

    public static function getNavigationLabel(): string
    {
        return __('zatca.admin_overview') ?: 'ZATCA Overview';
    }

    public function getTitle(): string
    {
        return __('zatca.admin_overview') ?: 'ZATCA Cross-Tenant Overview';
    }

    public static function canAccess(): bool
    {
        $user = auth('admin')->user();
        return $user && $user->hasAnyPermission(['analytics.view', 'settings.credentials']);
    }

    /**
     * @return array{totals: array<string,mixed>, stores: array<int,array<string,mixed>>}
     */
    public function getViewData(): array
    {
        /** @var ZatcaComplianceService $service */
        $service = app(ZatcaComplianceService::class);

        // null = include every store across every tenant (super-admin scope).
        $overview = $service->adminOverview(null);

        return [
            'totals' => $overview['totals'],
            'stores' => $overview['stores'],
        ];
    }
}
