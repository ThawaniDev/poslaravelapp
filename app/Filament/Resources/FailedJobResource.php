<?php

namespace App\Filament\Resources;

use App\Domain\AdminPanel\Models\AdminActivityLog;
use App\Domain\Core\Models\FailedJob;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Artisan;

class FailedJobResource extends Resource
{
    protected static ?string $model = FailedJob::class;

    protected static ?string $navigationIcon = 'heroicon-o-exclamation-triangle';

    protected static ?string $navigationGroup = null;

    public static function getNavigationGroup(): ?string
    {
        return __('nav.group_infrastructure');
    }

    protected static ?string $navigationLabel = null;

    protected static ?int $navigationSort = 1;

    public static function getNavigationLabel(): string
    {
        return __('infrastructure.failed_jobs');
    }

    public static function getModelLabel(): string
    {
        return __('infrastructure.failed_job');
    }

    public static function getPluralModelLabel(): string
    {
        return __('infrastructure.failed_jobs');
    }

    public static function canAccess(): bool
    {
        $user = auth('admin')->user();
        return $user && $user->hasAnyPermission(['infrastructure.view', 'infrastructure.manage']);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),
                Tables\Columns\TextColumn::make('uuid')
                    ->label(__('infrastructure.uuid'))
                    ->searchable()
                    ->limit(12)
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('queue')
                    ->label(__('infrastructure.queue'))
                    ->badge()
                    ->color('gray')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('connection')
                    ->label(__('infrastructure.connection'))
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('payload')
                    ->label(__('infrastructure.job_name'))
                    ->formatStateUsing(function ($state) {
                        $data = json_decode($state, true);
                        $class = $data['displayName'] ?? ($data['data']['commandName'] ?? 'Unknown');
                        return class_basename($class);
                    })
                    ->searchable()
                    ->limit(30),
                Tables\Columns\TextColumn::make('exception')
                    ->label(__('infrastructure.exception'))
                    ->limit(60)
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('failed_at')
                    ->label(__('infrastructure.failed_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('queue')
                    ->label(__('infrastructure.queue'))
                    ->options(fn () => FailedJob::query()->select('queue')->distinct()->pluck('queue', 'queue')->toArray()),
            ])
            ->actions([
                Tables\Actions\Action::make('retry')
                    ->label(__('infrastructure.retry'))
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        Artisan::call('queue:retry', ['id' => [$record->uuid]]);
                        AdminActivityLog::record(
                            adminUserId: auth('admin')->id(),
                            action: 'retry_failed_job',
                            entityType: 'failed_job',
                            entityId: (string) $record->id,
                            details: ['uuid' => $record->uuid, 'queue' => $record->queue],
                        );
                    })
                    ->successNotificationTitle(__('infrastructure.job_retried')),
                Tables\Actions\Action::make('view_exception')
                    ->label(__('infrastructure.view_exception'))
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->modalContent(fn ($record) => view('filament.pages.failed-job-exception', ['exception' => $record->exception]))
                    ->modalSubmitAction(false),
                Tables\Actions\DeleteAction::make()
                    ->before(function ($record) {
                        AdminActivityLog::record(
                            adminUserId: auth('admin')->id(),
                            action: 'delete_failed_job',
                            entityType: 'failed_job',
                            entityId: (string) $record->id,
                            details: ['uuid' => $record->uuid],
                        );
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('retry_selected')
                        ->label(__('infrastructure.retry_selected'))
                        ->icon('heroicon-o-arrow-path')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->action(function ($records) {
                            foreach ($records as $record) {
                                Artisan::call('queue:retry', ['id' => [$record->uuid]]);
                            }
                        })
                        ->deselectRecordsAfterCompletion(),
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('failed_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => FailedJobResource\Pages\ListFailedJobs::route('/'),
        ];
    }
}
