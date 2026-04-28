<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class ZatcaConfigurationPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-shield-check';
    protected static ?string $navigationGroup = null;

    public static function getNavigationGroup(): ?string
    {
        return __('nav.group_zatca');
    }

    protected static ?int $navigationSort = 11;
    protected static string $view = 'filament.pages.zatca-configuration';

    public static function getNavigationLabel(): string
    {
        return __('settings.zatca_configuration');
    }

    public function getTitle(): string
    {
        return __('settings.zatca_configuration');
    }

    public static function canAccess(): bool
    {
        $user = auth('admin')->user();
        return $user && $user->hasAnyPermission(['settings.credentials']);
    }
}
