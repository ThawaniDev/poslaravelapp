<?php

namespace App\Filament\Resources\DeliveryPlatformResource\Pages;

use App\Filament\Resources\DeliveryPlatformResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListDeliveryPlatforms extends ListRecords
{
    protected static string $resource = DeliveryPlatformResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->visible(fn () => auth('admin')->user()?->hasPermissionTo('integrations.manage')),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            DeliveryPlatformHealthWidget::class,
        ];
    }
}
