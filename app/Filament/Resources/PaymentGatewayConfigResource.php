<?php

namespace App\Filament\Resources;

use App\Domain\Subscription\Enums\GatewayEnvironment;
use App\Domain\Subscription\Enums\GatewayName;
use App\Domain\Subscription\Models\PaymentGatewayConfig;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PaymentGatewayConfigResource extends Resource
{
    protected static ?string $model = PaymentGatewayConfig::class;

    protected static ?string $navigationIcon = 'heroicon-o-credit-card';

    protected static ?string $navigationGroup = 'Subscription & Billing';

    protected static ?string $navigationLabel = 'Payment Gateways';

    protected static ?int $navigationSort = 6;

    public static function canAccess(): bool
    {
        $user = auth('admin')->user();

        return $user && $user->hasAnyPermission(['billing.edit']);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Gateway Configuration')
                ->schema([
                    Forms\Components\Select::make('gateway_name')
                        ->options(GatewayName::class)
                        ->required()
                        ->native(false),

                    Forms\Components\Select::make('environment')
                        ->options(GatewayEnvironment::class)
                        ->required()
                        ->native(false)
                        ->default('sandbox'),

                    Forms\Components\Toggle::make('is_active')
                        ->default(true)
                        ->helperText('Only one gateway per environment should be active'),
                ])
                ->columns(3),

            Forms\Components\Section::make('Credentials')
                ->schema([
                    Forms\Components\KeyValue::make('credentials_encrypted')
                        ->label('Credentials')
                        ->keyLabel('Key')
                        ->valueLabel('Value')
                        ->addActionLabel('Add credential field')
                        ->helperText('Stored encrypted at rest. Common keys: api_key, secret_key, merchant_id, publishable_key')
                        ->columnSpanFull(),
                ]),

            Forms\Components\Section::make('Webhook')
                ->schema([
                    Forms\Components\TextInput::make('webhook_url')
                        ->label('Webhook URL')
                        ->url()
                        ->maxLength(500)
                        ->prefixIcon('heroicon-m-link')
                        ->helperText('The URL the gateway will call for payment notifications')
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('gateway_name')
                    ->badge()
                    ->color(fn ($state) => match ($state?->value ?? $state) {
                        'thawani_pay' => 'success',
                        'stripe' => 'info',
                        'moyasar' => 'warning',
                        default => 'gray',
                    })
                    ->searchable(),

                Tables\Columns\TextColumn::make('environment')
                    ->badge()
                    ->color(fn ($state) => match ($state?->value ?? $state) {
                        'sandbox' => 'warning',
                        'production' => 'success',
                        default => 'gray',
                    }),

                Tables\Columns\IconColumn::make('is_active')
                    ->boolean()
                    ->label('Active'),

                Tables\Columns\TextColumn::make('webhook_url')
                    ->limit(40)
                    ->toggleable(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->label('Last Updated'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('gateway_name')
                    ->options(GatewayName::class),

                Tables\Filters\SelectFilter::make('environment')
                    ->options(GatewayEnvironment::class),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active Status'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('toggle_active')
                    ->icon(fn (PaymentGatewayConfig $record) => $record->is_active ? 'heroicon-m-x-circle' : 'heroicon-m-check-circle')
                    ->color(fn (PaymentGatewayConfig $record) => $record->is_active ? 'danger' : 'success')
                    ->label(fn (PaymentGatewayConfig $record) => $record->is_active ? 'Deactivate' : 'Activate')
                    ->requiresConfirmation()
                    ->action(function (PaymentGatewayConfig $record) {
                        $record->update(['is_active' => ! $record->is_active]);

                        Notification::make()
                            ->title($record->is_active ? 'Gateway activated' : 'Gateway deactivated')
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('gateway_name');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make('Gateway Details')
                ->schema([
                    Infolists\Components\TextEntry::make('gateway_name')->badge(),
                    Infolists\Components\TextEntry::make('environment')->badge(),
                    Infolists\Components\IconEntry::make('is_active')->boolean()->label('Active'),
                    Infolists\Components\TextEntry::make('webhook_url')->copyable()->placeholder('Not configured'),
                ])
                ->columns(4),

            Infolists\Components\Section::make('Timestamps')
                ->schema([
                    Infolists\Components\TextEntry::make('created_at')->dateTime(),
                    Infolists\Components\TextEntry::make('updated_at')->dateTime(),
                ])
                ->columns(2),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => PaymentGatewayConfigResource\Pages\ListPaymentGatewayConfigs::route('/'),
            'create' => PaymentGatewayConfigResource\Pages\CreatePaymentGatewayConfig::route('/create'),
            'view' => PaymentGatewayConfigResource\Pages\ViewPaymentGatewayConfig::route('/{record}'),
            'edit' => PaymentGatewayConfigResource\Pages\EditPaymentGatewayConfig::route('/{record}/edit'),
        ];
    }
}
