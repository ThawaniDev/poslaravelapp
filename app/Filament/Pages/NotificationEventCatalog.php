<?php

namespace App\Filament\Pages;

use App\Domain\Notification\Services\NotificationTemplateService;
use Filament\Pages\Page;

class NotificationEventCatalog extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-book-open';

    protected static ?string $navigationGroup = 'Notifications';

    protected static ?int $navigationSort = 3;

    protected static string $view = 'filament.pages.notification-event-catalog';

    public static function getNavigationLabel(): string
    {
        return __('notifications.event_catalog');
    }

    public function getTitle(): string
    {
        return __('notifications.event_catalog_title');
    }

    public static function canAccess(): bool
    {
        $user = auth('admin')->user();
        return $user && $user->hasAnyPermission(['notifications.manage']);
    }

    public function getViewData(): array
    {
        return [
            'catalog' => NotificationTemplateService::eventCatalog(),
        ];
    }
}
