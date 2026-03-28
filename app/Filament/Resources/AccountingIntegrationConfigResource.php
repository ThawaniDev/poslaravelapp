<?php

namespace App\Filament\Resources;

use App\Domain\AccountingIntegration\Enums\AccountingProvider;
use App\Domain\AdminPanel\Models\AdminActivityLog;
use App\Domain\SystemConfig\Models\AccountingIntegrationConfig;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class AccountingIntegrationConfigResource extends Resource
{
    protected static ?string $model = AccountingIntegrationConfig::class;

    protected static ?string $navigationIcon = 'heroicon-o-calculator';

    protected static ?string $navigationGroup = null;

    public static function getNavigationGroup(): ?string
    {
        return __('nav.group_settings');
    }

    protected static ?string $navigationLabel = null;

    protected static ?int $navigationSort = 8;

    public static function getNavigationLabel(): string
    {
        return __('settings.accounting_configs');
    }

    public static function getModelLabel(): string
    {
        return __('settings.accounting_config');
    }

    public static function getPluralModelLabel(): string
    {
        return __('settings.accounting_configs');
    }

    public static function canAccess(): bool
    {
        $user = auth('admin')->user();
        return $user && $user->hasAnyPermission(['settings.credentials']);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make(__('settings.provider_info'))
                ->schema([
                    Forms\Components\Select::make('provider_name')
                        ->label(__('settings.provider'))
                        ->options(collect(AccountingProvider::cases())->mapWithKeys(fn ($c) => [$c->value => ucfirst($c->value)]))
                        ->required()
                        ->native(false),
                    Forms\Components\Toggle::make('is_active')
                        ->label(__('settings.is_active'))
                        ->default(true),
                ])->columns(2),

            Forms\Components\Section::make(__('settings.credentials'))
                ->schema([
                    Forms\Components\TextInput::make('client_id_encrypted')
                        ->label(__('settings.client_id'))
                        ->password()
                        ->revealable()
                        ->maxLength(500),
                    Forms\Components\TextInput::make('client_secret_encrypted')
                        ->label(__('settings.client_secret'))
                        ->password()
                        ->revealable()
                        ->maxLength(500),
                    Forms\Components\TextInput::make('redirect_url')
                        ->label(__('settings.redirect_url'))
                        ->url()
                        ->maxLength(500)
                        ->columnSpanFull(),
                ])->columns(2)
                ->description(__('settings.credentials_warning')),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('provider_name')
                    ->label(__('settings.provider'))
                    ->formatStateUsing(fn ($state) => ucfirst($state->value))
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        AccountingProvider::Quickbooks => 'success',
                        AccountingProvider::Xero => 'info',
                        AccountingProvider::Qoyod => 'warning',
                    })
                    ->searchable()
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label(__('settings.is_active'))
                    ->boolean(),
                Tables\Columns\TextColumn::make('redirect_url')
                    ->label(__('settings.redirect_url'))
                    ->limit(40)
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label(__('settings.updated_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label(__('settings.is_active')),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('test_connection')
                    ->label(__('settings.test_connection'))
                    ->icon('heroicon-o-signal')
                    ->color('info')
                    ->requiresConfirmation()
                    ->action(function ($record, Tables\Actions\Action $action) {
                        AdminActivityLog::record(
                            adminUserId: auth('admin')->id(),
                            action: 'test_accounting_connection',
                            entityType: 'accounting_config',
                            entityId: $record->id,
                            details: ['provider' => $record->provider_name->value],
                        );
                        $action->success();
                    })
                    ->successNotificationTitle(__('settings.connection_test_sent')),
                Tables\Actions\DeleteAction::make()
                    ->before(function ($record) {
                        AdminActivityLog::record(
                            adminUserId: auth('admin')->id(),
                            action: 'delete_accounting_config',
                            entityType: 'accounting_config',
                            entityId: $record->id,
                            details: ['provider' => $record->provider_name->value],
                        );
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => AccountingIntegrationConfigResource\Pages\ListAccountingIntegrationConfigs::route('/'),
            'create' => AccountingIntegrationConfigResource\Pages\CreateAccountingIntegrationConfig::route('/create'),
            'edit' => AccountingIntegrationConfigResource\Pages\EditAccountingIntegrationConfig::route('/{record}/edit'),
        ];
    }
}
