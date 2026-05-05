<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class EdfaPaySetupGuidePage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static string $view = 'filament.pages.edfapay-setup-guide';

    protected static ?int $navigationSort = 7;

    protected static ?string $slug = 'registers/edfapay-setup-guide';

    public static function getNavigationGroup(): ?string
    {
        return __('nav.group_core');
    }

    public static function getNavigationLabel(): string
    {
        return __('nav.edfapay_setup_guide');
    }

    public function getTitle(): string
    {
        return __('nav.edfapay_setup_guide');
    }

    public static function canAccess(): bool
    {
        $user = auth('admin')->user();

        return $user && $user->hasAnyPermission(['terminals.view', 'terminals.edit', 'terminals.create']);
    }
}
