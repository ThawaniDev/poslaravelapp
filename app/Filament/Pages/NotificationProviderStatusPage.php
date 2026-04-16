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
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

class NotificationProviderStatusPage extends Page implements HasForms, HasTable
{
    use InteractsWithForms, InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-signal';

    protected static ?string $navigationGroup = null;

    public static function getNavigationGroup(): ?string
    {
        return __('nav.group_notifications');
    }

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

    public static function getNavigationBadge(): ?string
    {
        $unhealthy = NotificationProviderStatus::where('is_healthy', false)->where('is_enabled', true)->count();
        return $unhealthy > 0 ? (string) $unhealthy : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
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
                        $state instanceof NotificationChannel && $state === NotificationChannel::Email => 'primary',
                        $state instanceof NotificationChannel && $state === NotificationChannel::Sms => 'warning',
                        $state instanceof NotificationChannel && $state === NotificationChannel::Push => 'success',
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

                Tables\Columns\TextColumn::make('rate_limit_per_minute')
                    ->label(__('notifications.rate_limit'))
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('cost_per_message')
                    ->label(__('notifications.cost_per_message'))
                    ->money('SAR')
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('last_test_at')
                    ->label(__('notifications.last_test'))
                    ->dateTime('M d H:i')
                    ->placeholder(__('settings.never'))
                    ->toggleable(isToggledHiddenByDefault: true),

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
                Tables\Filters\TernaryFilter::make('is_enabled')
                    ->label(__('notifications.enabled')),
            ])
            ->actions([
                Tables\Actions\Action::make('test_send')
                    ->label(__('notifications.test_send'))
                    ->icon('heroicon-o-paper-airplane')
                    ->color('info')
                    ->visible(fn () => auth('admin')->user()?->hasPermissionTo('notifications.manage'))
                    ->form([
                        Forms\Components\TextInput::make('test_recipient')
                            ->label(__('notifications.test_recipient'))
                            ->required()
                            ->helperText(__('notifications.test_recipient_helper')),
                    ])
                    ->action(function (NotificationProviderStatus $record, array $data) {
                        $record->update([
                            'last_test_at' => now(),
                            'last_test_result' => 'pending',
                        ]);

                        AdminActivityLog::record(
                            adminUserId: auth('admin')->id(),
                            action: 'test_notification_provider',
                            entityType: 'notification_provider',
                            entityId: $record->id,
                            details: [
                                'provider' => $record->provider->value ?? $record->provider,
                                'channel' => $record->channel->value ?? $record->channel,
                                'recipient' => $data['test_recipient'],
                            ],
                        );

                        // Actually dispatch a test notification via the template service
                        try {
                            $service = app(\App\Domain\Notification\Services\NotificationTemplateService::class);
                            $channelEnum = $record->channel instanceof \App\Domain\Notification\Enums\NotificationChannel
                                ? $record->channel
                                : \App\Domain\Notification\Enums\NotificationChannel::from($record->channel);

                            $service->dispatch(
                                eventKey: 'system.update_available',
                                channel: $channelEnum,
                                recipient: $data['test_recipient'],
                                variables: [
                                    'version' => 'Test v1.0',
                                    'release_notes_summary' => 'This is a test notification from the admin panel.',
                                ],
                            );

                            $record->update(['last_test_result' => 'sent']);
                        } catch (\Throwable $e) {
                            $record->update(['last_test_result' => 'failed: ' . mb_substr($e->getMessage(), 0, 100)]);
                        }

                        Notification::make()
                            ->title(__('notifications.test_queued'))
                            ->body(__('notifications.test_queued_body', [
                                'provider' => $record->provider->value ?? $record->provider,
                                'recipient' => $data['test_recipient'],
                            ]))
                            ->success()
                            ->send();
                    }),

                Tables\Actions\Action::make('toggle_enabled')
                    ->label(fn (NotificationProviderStatus $record) => $record->is_enabled ? __('notifications.disable') : __('notifications.enable'))
                    ->icon(fn (NotificationProviderStatus $record) => $record->is_enabled ? 'heroicon-o-x-circle' : 'heroicon-o-check-circle')
                    ->color(fn (NotificationProviderStatus $record) => $record->is_enabled ? 'danger' : 'success')
                    ->requiresConfirmation()
                    ->action(function (NotificationProviderStatus $record) {
                        $record->update([
                            'is_enabled' => !$record->is_enabled,
                            'disabled_reason' => $record->is_enabled ? __('security.disabled_by_admin') : null,
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
                    ->visible(fn () => auth('admin')->user()?->hasPermissionTo('notifications.manage'))
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

                Tables\Actions\Action::make('configure_limits')
                    ->label(__('notifications.configure_limits'))
                    ->icon('heroicon-o-cog-6-tooth')
                    ->visible(fn () => auth('admin')->user()?->hasPermissionTo('notifications.manage'))
                    ->form([
                        Forms\Components\TextInput::make('rate_limit_per_minute')
                            ->label(__('notifications.rate_limit'))
                            ->numeric()
                            ->minValue(0)
                            ->default(fn (NotificationProviderStatus $record) => $record->rate_limit_per_minute),
                        Forms\Components\TextInput::make('cost_per_message')
                            ->label(__('notifications.cost_per_message'))
                            ->numeric()
                            ->minValue(0)
                            ->step(0.001)
                            ->prefix('SAR')
                            ->default(fn (NotificationProviderStatus $record) => $record->cost_per_message),
                    ])
                    ->action(function (NotificationProviderStatus $record, array $data) {
                        $record->update([
                            'rate_limit_per_minute' => $data['rate_limit_per_minute'],
                            'cost_per_message' => $data['cost_per_message'],
                        ]);

                        Notification::make()
                            ->title(__('notifications.limits_updated'))
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
            ->bulkActions([
                Tables\Actions\BulkAction::make('bulk_enable')
                    ->label(__('notifications.enable_selected'))
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(function (Collection $records) {
                        $records->each(function (NotificationProviderStatus $record) {
                            $record->update(['is_enabled' => true, 'disabled_reason' => null]);
                            Cache::forget("notification_providers:{$record->channel->value}");
                        });

                        AdminActivityLog::record(
                            adminUserId: auth('admin')->id(),
                            action: 'bulk_enable_providers',
                            entityType: 'notification_provider',
                            details: ['count' => $records->count()],
                        );

                        Notification::make()->title(__('notifications.providers_enabled', ['count' => $records->count()]))->success()->send();
                    })
                    ->deselectRecordsAfterCompletion(),
                Tables\Actions\BulkAction::make('bulk_disable')
                    ->label(__('notifications.disable_selected'))
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function (Collection $records) {
                        $records->each(function (NotificationProviderStatus $record) {
                            $record->update(['is_enabled' => false, 'disabled_reason' => __('security.bulk_disabled_by_admin')]);
                            Cache::forget("notification_providers:{$record->channel->value}");
                        });

                        AdminActivityLog::record(
                            adminUserId: auth('admin')->id(),
                            action: 'bulk_disable_providers',
                            entityType: 'notification_provider',
                            details: ['count' => $records->count()],
                        );

                        Notification::make()->title(__('notifications.providers_disabled', ['count' => $records->count()]))->success()->send();
                    })
                    ->deselectRecordsAfterCompletion(),
                Tables\Actions\BulkAction::make('bulk_reset_health')
                    ->label(__('notifications.reset_health'))
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->action(function (Collection $records) {
                        $records->each(function (NotificationProviderStatus $record) {
                            $record->update(['is_healthy' => true, 'failure_count_24h' => 0, 'success_count_24h' => 0, 'disabled_reason' => null]);
                            Cache::forget("notification_providers:{$record->channel->value}");
                        });

                        Notification::make()->title(__('notifications.health_reset'))->success()->send();
                    })
                    ->deselectRecordsAfterCompletion(),
            ])
            ->defaultSort('channel')
            ->defaultSort('priority')
            ->poll('60s');
    }
}
