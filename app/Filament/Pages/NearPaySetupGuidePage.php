<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class NearPaySetupGuidePage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static string $view = 'filament.pages.nearpay-setup-guide';

    protected static ?int $navigationSort = 6;

    protected static ?string $slug = 'registers/nearpay-setup-guide';

    public static function getNavigationGroup(): ?string
    {
        return __('nav.group_core');
    }

    public static function getNavigationLabel(): string
    {
        return __('nav.nearpay_setup_guide');
    }

    public function getTitle(): string
    {
        return __('nav.nearpay_setup_guide');
    }

    public static function canAccess(): bool
    {
        $user = auth('admin')->user();

        return $user && $user->hasAnyPermission(['terminals.view', 'terminals.edit', 'terminals.create']);
    }
}
