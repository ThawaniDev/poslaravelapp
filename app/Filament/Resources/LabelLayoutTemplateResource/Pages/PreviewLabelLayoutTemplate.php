<?php

namespace App\Filament\Resources\LabelLayoutTemplateResource\Pages;

use App\Filament\Resources\LabelLayoutTemplateResource;
use Filament\Actions;
use Filament\Resources\Pages\Page;

class PreviewLabelLayoutTemplate extends Page
{
    protected static string $resource = LabelLayoutTemplateResource::class;

    protected static string $view = 'filament.resources.label-layout-template.preview';

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
                ->url(fn () => LabelLayoutTemplateResource::getUrl('edit', ['record' => $this->record]))
                ->icon('heroicon-o-pencil-square'),
            Actions\Action::make('back')
                ->label(__('ui.back_to_list'))
                ->url(fn () => LabelLayoutTemplateResource::getUrl())
                ->color('gray')
                ->icon('heroicon-o-arrow-left'),
        ];
    }
}
