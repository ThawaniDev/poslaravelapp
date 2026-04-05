<?php

namespace App\Filament\Resources\PredefinedCategoryResource\Pages;

use App\Exports\PredefinedCategoriesExport;
use App\Exports\Templates\PredefinedCategoryTemplateExport;
use App\Filament\Resources\PredefinedCategoryResource;
use App\Imports\PredefinedCategoriesImport;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Maatwebsite\Excel\Facades\Excel;

class ListPredefinedCategories extends ListRecords
{
    protected static string $resource = PredefinedCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),

            Actions\Action::make('export')
                ->label(__('Export Excel'))
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->action(fn () => Excel::download(new PredefinedCategoriesExport, 'predefined_categories.xlsx')),

            Actions\Action::make('import')
                ->label(__('Import Excel'))
                ->icon('heroicon-o-arrow-up-tray')
                ->color('warning')
                ->form([
                    \Filament\Forms\Components\FileUpload::make('file')
                        ->label(__('Excel File'))
                        ->acceptedFileTypes([
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            'application/vnd.ms-excel',
                            'text/csv',
                        ])
                        ->required(),
                ])
                ->action(function (array $data) {
                    $filePath = storage_path('app/public/' . $data['file']);

                    $import = new PredefinedCategoriesImport;
                    Excel::import($import, $filePath);

                    @unlink($filePath);

                    $message = __(':imported categories imported.', ['imported' => $import->imported]);
                    if ($import->skipped > 0) {
                        $message .= ' ' . __(':skipped skipped.', ['skipped' => $import->skipped]);
                    }

                    $notification = Notification::make()->title($message);

                    if (! empty($import->errors)) {
                        $notification->body(implode("\n", array_slice($import->errors, 0, 5)))
                            ->warning();
                    } else {
                        $notification->success();
                    }

                    $notification->send();
                }),

            Actions\Action::make('download_template')
                ->label(__('Download Template'))
                ->icon('heroicon-o-document-arrow-down')
                ->color('gray')
                ->action(fn () => Excel::download(new PredefinedCategoryTemplateExport, 'categories_template.xlsx')),
        ];
    }
}
