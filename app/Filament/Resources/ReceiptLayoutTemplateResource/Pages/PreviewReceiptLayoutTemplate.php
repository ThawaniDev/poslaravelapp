<?php

namespace App\Filament\Resources\ReceiptLayoutTemplateResource\Pages;

use App\Filament\Resources\ReceiptLayoutTemplateResource;
use Filament\Actions;
use Filament\Resources\Pages\Page;

class PreviewReceiptLayoutTemplate extends Page
{
    protected static string $resource = ReceiptLayoutTemplateResource::class;

    protected static string $view = 'filament.resources.receipt-layout-template.preview';

    protected static ?string $title = null;

    public $record;

    public function getTitle(): string
    {
        return __('ui.preview') . ': ' . $this->record->name;
    }

    public function mount(int|string $record): void
    {
        $this->record = static::getResource()::resolveRecordRouteBinding($record);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('edit')
                ->label(__('ui.edit'))
                ->url(fn () => ReceiptLayoutTemplateResource::getUrl('edit', ['record' => $this->record]))
                ->icon('heroicon-o-pencil-square'),
            Actions\Action::make('back')
                ->label(__('ui.back_to_list'))
                ->url(fn () => ReceiptLayoutTemplateResource::getUrl())
                ->color('gray')
                ->icon('heroicon-o-arrow-left'),
        ];
    }
}
