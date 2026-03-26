<?php

namespace App\Filament\Pages;

use App\Domain\AdminPanel\Models\AdminActivityLog;
use App\Domain\Notification\Enums\NotificationChannel;
use App\Domain\Notification\Enums\NotificationProvider;
use App\Domain\Notification\Models\NotificationProviderStatus;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;

class NotificationProviderStatusPage extends Page implements HasForms, HasTable
{
    use InteractsWithForms, InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-signal';

    protected static ?string $navigationGroup = 'Notifications';

    protected static ?int $navigationSort = 4;

    protected static string $view = 'filament.pages.notification-provider-status';

    public static function getNavigationLabel(): string
    {
        return __('notifications.provider_status');
    }

    public function getTitle(): string
    {
        return __('notifications.provider_status_title');
    }

    public static function canAccess(): bool
    {
        $user = auth('admin')->user();
        return $user && $user->hasAnyPermission(['notifications.manage']);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(NotificationProviderStatus::query())
            ->columns([
                Tables\Columns\TextColumn::make('provider')
                    ->label(__('notifications.provider'))
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state instanceof NotificationProvider ? $state->value : $state)
                    ->sortable(),

                Tables\Columns\TextColumn::make('channel')
                    ->label(__('notifications.channel'))
                    ->badge()
                    ->color(fn ($state) => match (true) {
                        $state === NotificationChannel::Email || ($state instanceof NotificationChannel && $state === NotificationChannel::Email) => 'primary',
                        $state === NotificationChannel::Sms || ($state instanceof NotificationChannel && $state === NotificationChannel::Sms) => 'warning',
                        $state === NotificationChannel::Push || ($state instanceof NotificationChannel && $state === NotificationChannel::Push) => 'success',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn ($state) => $state instanceof NotificationChannel ? __("notifications.channel_{$state->value}") : $state)
                    ->sortable(),

                Tables\Columns\TextColumn::make('priority')
                    ->label(__('notifications.priority'))
                    ->sortable()
                    ->alignCenter(),

                Tables\Columns\IconColumn::make('is_enabled')
                    ->label(__('notifications.enabled'))
                    ->boolean()
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_healthy')
                    ->label(__('notifications.healthy'))
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger'),

                Tables\Columns\TextColumn::make('success_count_24h')
                    ->label(__('notifications.success_24h'))
                    ->numeric()
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('failure_count_24h')
                    ->label(__('notifications.failure_24h'))
                    ->numeric()
                    ->color(fn ($state) => $state > 0 ? 'danger' : 'gray')
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('avg_latency_ms')
                    ->label(__('notifications.avg_latency'))
                    ->numeric()
                    ->suffix(' ms')
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('last_success_at')
                    ->label(__('notifications.last_success'))
                    ->dateTime('M d H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('last_failure_at')
                    ->label(__('notifications.last_failure'))
                    ->dateTime('M d H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('disabled_reason')
                    ->label(__('notifications.disabled_reason'))
                    ->limit(30)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('channel')
                    ->options(
                        collect(NotificationChannel::cases())
                            ->mapWithKeys(fn (NotificationChannel $c) => [$c->value => __("notifications.channel_{$c->value}")])
                            ->toArray()
                    ),
                Tables\Filters\TernaryFilter::make('is_healthy')
                    ->label(__('notifications.healthy')),
            ])
            ->actions([
                Tables\Actions\Action::make('toggle_enabled')
                    ->label(fn (NotificationProviderStatus $record) => $record->is_enabled ? __('notifications.disable') : __('notifications.enable'))
                    ->icon(fn (NotificationProviderStatus $record) => $record->is_enabled ? 'heroicon-o-x-circle' : 'heroicon-o-check-circle')
                    ->color(fn (NotificationProviderStatus $record) => $record->is_enabled ? 'danger' : 'success')
                    ->requiresConfirmation()
                    ->action(function (NotificationProviderStatus $record) {
                        $record->update([
                            'is_enabled' => !$record->is_enabled,
                            'disabled_reason' => $record->is_enabled ? 'Manually disabled by admin' : null,
                        ]);

                        Cache::forget("notification_providers:{$record->channel->value}");

                        AdminActivityLog::record(
                            adminUserId: auth('admin')->id(),
                            action: $record->is_enabled ? 'provider_enabled' : 'provider_disabled',
                            entityType: 'notification_provider',
                            entityId: $record->id,
                            details: ['provider' => $record->provider->value ?? $record->provider, 'channel' => $record->channel->value ?? $record->channel],
                        );

                        Notification::make()
                            ->title($record->is_enabled ? __('notifications.provider_enabled') : __('notifications.provider_disabled'))
                            ->success()
                            ->send();
                    }),

                Tables\Actions\Action::make('update_priority')
                    ->label(__('notifications.update_priority'))
                    ->icon('heroicon-o-arrows-up-down')
                    ->form([
                        Forms\Components\TextInput::make('priority')
                            ->label(__('notifications.priority'))
                            ->numeric()
                            ->required()
                            ->minValue(1)
                            ->maxValue(99)
                            ->default(fn (NotificationProviderStatus $record) => $record->priority),
                    ])
                    ->action(function (NotificationProviderStatus $record, array $data) {
                        $record->update(['priority' => $data['priority']]);
                        Cache::forget("notification_providers:{$record->channel->value}");

                        Notification::make()
                            ->title(__('notifications.priority_updated'))
                            ->success()
                            ->send();
                    }),

                Tables\Actions\Action::make('reset_health')
                    ->label(__('notifications.reset_health'))
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->action(function (NotificationProviderStatus $record) {
                        $record->update([
                            'is_healthy' => true,
                            'failure_count_24h' => 0,
                            'success_count_24h' => 0,
                            'disabled_reason' => null,
                        ]);
                        Cache::forget("notification_providers:{$record->channel->value}");

                        Notification::make()
                            ->title(__('notifications.health_reset'))
                            ->success()
                            ->send();
                    }),
            ])
            ->defaultSort('channel')
            ->defaultSort('priority');
    }
}
