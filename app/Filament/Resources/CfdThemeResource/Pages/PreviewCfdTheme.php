<?php

namespace App\Filament\Resources\CfdThemeResource\Pages;

use App\Filament\Resources\CfdThemeResource;
use Filament\Actions;
use Filament\Resources\Pages\Page;

class PreviewCfdTheme extends Page
{
    protected static string $resource = CfdThemeResource::class;

    protected static string $view = 'filament.resources.cfd-theme.preview';

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
                ->url(fn () => CfdThemeResource::getUrl('edit', ['record' => $this->record]))
                ->icon('heroicon-o-pencil-square'),
            Actions\Action::make('back')
                ->label(__('ui.back_to_list'))
                ->url(fn () => CfdThemeResource::getUrl())
                ->color('gray')
                ->icon('heroicon-o-arrow-left'),
        ];
    }
}
