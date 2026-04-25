<?php

namespace App\Filament\Resources;

use App\Domain\AdminPanel\Models\AdminActivityLog;
use App\Domain\ZatcaCompliance\Enums\ZatcaDeviceStatus;
use App\Domain\ZatcaCompliance\Models\ZatcaDevice;
use App\Domain\ZatcaCompliance\Services\DeviceService;
use App\Domain\ZatcaCompliance\Services\HashChainService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ZatcaDeviceResource extends Resource
{
    protected static ?string $model = ZatcaDevice::class;

    protected static ?string $navigationIcon = 'heroicon-o-cpu-chip';

    protected static ?string $navigationGroup = null;

    protected static ?int $navigationSort = 14;

    public static function getNavigationGroup(): ?string
    {
        return __('nav.group_zatca');
    }

    public static function getNavigationLabel(): string
    {
        return __('zatca.devices');
    }

    public static function getModelLabel(): string
    {
        return __('zatca.device');
    }

    public static function getPluralModelLabel(): string
    {
        return __('zatca.devices');
    }

    public static function canAccess(): bool
    {
        $user = auth('admin')->user();
        return $user && $user->hasAnyPermission(['settings.credentials']);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make(__('zatca.device_info'))->schema([
                Forms\Components\Select::make('store_id')
                    ->label(__('zatca.store'))
                    ->relationship('store', 'name')
                    ->searchable()
                    ->required()
                    ->disabledOn('edit'),
                Forms\Components\TextInput::make('hardware_serial')
                    ->label(__('zatca.hardware_serial'))
                    ->required()
                    ->maxLength(64),
                Forms\Components\Select::make('environment')
                    ->label(__('zatca.environment'))
                    ->options([
                        'sandbox' => __('zatca.env_sandbox'),
                        'production' => __('zatca.env_production'),
                    ])
                    ->default('sandbox')
                    ->required(),
                Forms\Components\Select::make('status')
                    ->label(__('zatca.status'))
                    ->options(collect(ZatcaDeviceStatus::cases())->mapWithKeys(fn ($c) => [$c->value => __('zatca.device_status_' . $c->value)]))
                    ->required(),
                Forms\Components\TextInput::make('device_uuid')->label('UUID')->disabled(),
                Forms\Components\TextInput::make('activation_code')->label(__('zatca.activation_code'))->disabled(),
                Forms\Components\TextInput::make('current_icv')->label('ICV')->disabled()->numeric(),
                Forms\Components\TextInput::make('current_pih')->label('PIH')->disabled(),
                Forms\Components\Toggle::make('is_tampered')->label(__('zatca.is_tampered'))->disabled(),
                Forms\Components\Textarea::make('tamper_reason')->label(__('zatca.tamper_reason'))->rows(2)->disabled(),
                Forms\Components\DateTimePicker::make('activated_at')->label(__('zatca.activated_at'))->disabled(),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('store.name')->label(__('zatca.store'))->searchable()->sortable(),
                Tables\Columns\TextColumn::make('hardware_serial')->label(__('zatca.hardware_serial'))->copyable()->limit(20),
                Tables\Columns\TextColumn::make('environment')
                    ->label(__('zatca.environment'))
                    ->formatStateUsing(fn ($state) => __('zatca.env_' . $state))
                    ->badge(),
                Tables\Columns\TextColumn::make('status')
                    ->label(__('zatca.status'))
                    ->badge()
                    ->color(fn ($state) => match ($state?->value ?? $state) {
                        'active' => 'success',
                        'pending' => 'warning',
                        'suspended', 'revoked' => 'gray',
                        'tampered' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn ($state) => __('zatca.device_status_' . ($state?->value ?? $state))),
                Tables\Columns\IconColumn::make('is_tampered')
                    ->label(__('zatca.is_tampered'))
                    ->boolean()
                    ->trueColor('danger')
                    ->falseColor('success'),
                Tables\Columns\TextColumn::make('current_icv')->label('ICV')->numeric()->sortable(),
                Tables\Columns\TextColumn::make('activated_at')->label(__('zatca.activated_at'))->dateTime('Y-m-d')->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label(__('zatca.status'))
                    ->options(collect(ZatcaDeviceStatus::cases())->mapWithKeys(fn ($c) => [$c->value => __('zatca.device_status_' . $c->value)])),
                Tables\Filters\SelectFilter::make('environment')
                    ->label(__('zatca.environment'))
                    ->options([
                        'sandbox' => __('zatca.env_sandbox'),
                        'production' => __('zatca.env_production'),
                    ]),
                Tables\Filters\TernaryFilter::make('is_tampered')->label(__('zatca.is_tampered')),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('reset_tamper')
                    ->label(__('zatca.reset_tamper'))
                    ->icon('heroicon-o-shield-exclamation')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->visible(fn (ZatcaDevice $record) => $record->is_tampered)
                    ->action(function (ZatcaDevice $record) {
                        try {
                            app(DeviceService::class)->resetTamper($record);
                            AdminActivityLog::record(
                                adminUserId: auth('admin')->id(),
                                action: 'reset_zatca_device_tamper',
                                entityType: 'zatca_device',
                                entityId: $record->id,
                            );
                            Notification::make()->title(__('zatca.device_tamper_reset'))->success()->send();
                        } catch (\Throwable $e) {
                            Notification::make()->title(__('zatca.action_failed'))->body($e->getMessage())->danger()->send();
                        }
                    }),
                Tables\Actions\Action::make('verify_chain')
                    ->label(__('zatca.verify_chain'))
                    ->icon('heroicon-o-link')
                    ->color('info')
                    ->action(function (ZatcaDevice $record) {
                        try {
                            $offending = app(HashChainService::class)->verifyChain($record->id);
                            $valid = $offending === null;
                            Notification::make()
                                ->title($valid ? __('zatca.chain_valid') : __('zatca.chain_invalid'))
                                ->body($valid ? '' : (__('zatca.first_offending') . ': ' . $offending->invoice_number))
                                ->{$valid ? 'success' : 'danger'}()
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()->title(__('zatca.action_failed'))->body($e->getMessage())->danger()->send();
                        }
                    }),
            ])
            ->defaultSort('activated_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ZatcaDeviceResource\Pages\ListZatcaDevices::route('/'),
            'edit' => ZatcaDeviceResource\Pages\EditZatcaDevice::route('/{record}/edit'),
        ];
    }
}
