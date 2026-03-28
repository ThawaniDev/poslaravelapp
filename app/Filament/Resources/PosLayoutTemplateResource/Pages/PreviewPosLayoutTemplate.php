<?php

namespace App\Filament\Resources\PosLayoutTemplateResource\Pages;

use App\Filament\Resources\PosLayoutTemplateResource;
use Filament\Actions;
use Filament\Resources\Pages\Page;

class PreviewPosLayoutTemplate extends Page
{
    protected static string $resource = PosLayoutTemplateResource::class;

    protected static string $view = 'filament.resources.pos-layout-template.preview';

    protected static ?string $title = null;

    public $record;

    public function getTitle(): string
    {
        return __('ui.preview') . ': ' . $this->record->name;
    }

    public function mount(int|string $record): void
    {
        $this->record = static::getResource()::resolveRecordRouteBinding($record);
        $this->record->load(['widgetPlacements.widget', 'businessType']);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('edit')
                ->label(__('ui.edit'))
                ->url(fn () => PosLayoutTemplateResource::getUrl('edit', ['record' => $this->record]))
                ->icon('heroicon-o-pencil-square'),
            Actions\Action::make('back')
                ->label(__('ui.back_to_list'))
                ->url(fn () => PosLayoutTemplateResource::getUrl())
                ->color('gray')
                ->icon('heroicon-o-arrow-left'),
        ];
    }
}
