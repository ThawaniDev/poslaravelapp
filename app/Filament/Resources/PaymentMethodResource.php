<?php

namespace App\Filament\Resources;

use App\Domain\AdminPanel\Models\AdminActivityLog;
use App\Domain\Payment\Enums\PaymentMethodCategory;
use App\Domain\Payment\Enums\PaymentMethodKey;
use App\Domain\SystemConfig\Models\PaymentMethod;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PaymentMethodResource extends Resource
{
    protected static ?string $model = PaymentMethod::class;

    protected static ?string $navigationIcon = 'heroicon-o-credit-card';

    protected static ?string $navigationGroup = null;

    public static function getNavigationGroup(): ?string
    {
        return __('nav.group_settings');
    }

    protected static ?string $navigationLabel = null;

    protected static ?int $navigationSort = 5;

    public static function getNavigationLabel(): string
    {
        return __('settings.payment_methods');
    }

    public static function getModelLabel(): string
    {
        return __('settings.payment_method');
    }

    public static function getPluralModelLabel(): string
    {
        return __('settings.payment_methods');
    }

    public static function canAccess(): bool
    {
        $user = auth('admin')->user();
        return $user && $user->hasAnyPermission(['settings.payment_methods']);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make(__('settings.method_details'))
                ->schema([
                    Forms\Components\Select::make('method_key')
                        ->label(__('settings.method_key'))
                        ->options(collect(PaymentMethodKey::cases())->mapWithKeys(fn ($c) => [$c->value => __('settings.pm_' . $c->value)]))
                        ->required()
                        ->native(false)
                        ->searchable(),
                    Forms\Components\Select::make('category')
                        ->label(__('settings.category'))
                        ->options(collect(PaymentMethodCategory::cases())->mapWithKeys(fn ($c) => [$c->value => __('settings.pmc_' . $c->value)]))
                        ->required()
                        ->native(false),
                    Forms\Components\TextInput::make('name')
                        ->label(__('settings.name_en'))
                        ->required()
                        ->maxLength(100),
                    Forms\Components\TextInput::make('name_ar')
                        ->label(__('settings.name_ar'))
                        ->required()
                        ->maxLength(100),
                    Forms\Components\Textarea::make('description')
                        ->label(__('settings.description_en'))
                        ->maxLength(500)
                        ->rows(2)
                        ->columnSpanFull(),
                    Forms\Components\Textarea::make('description_ar')
                        ->label(__('settings.description_ar'))
                        ->maxLength(500)
                        ->rows(2)
                        ->columnSpanFull(),
                    Forms\Components\TextInput::make('icon')
                        ->label(__('settings.icon'))
                        ->maxLength(100)
                        ->helperText(__('settings.icon_helper')),
                    Forms\Components\TextInput::make('sort_order')
                        ->label(__('settings.sort_order'))
                        ->numeric()
                        ->default(0),
                ])->columns(2),

            Forms\Components\Section::make(__('settings.capabilities'))
                ->schema([
                    Forms\Components\Toggle::make('requires_terminal')
                        ->label(__('settings.requires_terminal'))
                        ->helperText(__('settings.requires_terminal_helper')),
                    Forms\Components\Toggle::make('requires_customer_profile')
                        ->label(__('settings.requires_customer_profile'))
                        ->helperText(__('settings.requires_customer_profile_helper')),
                    Forms\Components\Toggle::make('is_active')
                        ->label(__('settings.is_active'))
                        ->default(true),
                ])->columns(3),

            Forms\Components\Section::make(__('settings.fee_configuration'))
                ->schema([
                    Forms\Components\TextInput::make('processing_fee_percent')
                        ->label(__('settings.processing_fee_percent'))
                        ->numeric()
                        ->suffix('%')
                        ->minValue(0)
                        ->maxValue(100)
                        ->step(0.01)
                        ->helperText(__('settings.processing_fee_percent_helper')),
                    Forms\Components\TextInput::make('processing_fee_fixed')
                        ->label(__('settings.processing_fee_fixed'))
                        ->numeric()
                        ->prefix('SAR')
                        ->minValue(0)
                        ->step(0.01),
                    Forms\Components\TextInput::make('min_amount')
                        ->label(__('settings.min_amount'))
                        ->numeric()
                        ->prefix('SAR')
                        ->minValue(0),
                    Forms\Components\TextInput::make('max_amount')
                        ->label(__('settings.max_amount'))
                        ->numeric()
                        ->prefix('SAR')
                        ->minValue(0),
                ])->columns(2)
                ->collapsible(),

            Forms\Components\Section::make(__('settings.provider_config'))
                ->schema([
                    Forms\Components\TagsInput::make('supported_currencies')
                        ->label(__('settings.supported_currencies'))
                        ->placeholder(__('settings.add_currency'))
                        ->helperText(__('settings.supported_currencies_helper'))
                        ->columnSpanFull(),
                    Forms\Components\Textarea::make('provider_config_schema')
                        ->label(__('settings.provider_config_schema'))
                        ->rows(6)
                        ->helperText(__('settings.json_schema_helper'))
                        ->columnSpanFull(),
                ])
                ->collapsible()
                ->collapsed(),
        ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make(__('settings.method_details'))
                ->schema([
                    Infolists\Components\TextEntry::make('method_key')
                        ->label(__('settings.method_key'))
                        ->formatStateUsing(fn ($state) => __('settings.pm_' . ($state->value ?? $state)))
                        ->badge(),
                    Infolists\Components\TextEntry::make('category')
                        ->label(__('settings.category'))
                        ->formatStateUsing(fn ($state) => __('settings.pmc_' . ($state->value ?? $state)))
                        ->badge()
                        ->color(fn ($state) => match ($state) {
                            PaymentMethodCategory::Cash => 'success',
                            PaymentMethodCategory::Card => 'primary',
                            PaymentMethodCategory::Digital => 'info',
                            PaymentMethodCategory::Credit => 'warning',
                            default => 'gray',
                        }),
                    Infolists\Components\TextEntry::make('name')
                        ->label(__('settings.name_en')),
                    Infolists\Components\TextEntry::make('name_ar')
                        ->label(__('settings.name_ar')),
                    Infolists\Components\TextEntry::make('description')
                        ->label(__('settings.description_en'))
                        ->default('-')
                        ->columnSpanFull(),
                    Infolists\Components\TextEntry::make('description_ar')
                        ->label(__('settings.description_ar'))
                        ->default('-')
                        ->columnSpanFull(),
                ])->columns(2),

            Infolists\Components\Section::make(__('settings.capabilities'))
                ->schema([
                    Infolists\Components\IconEntry::make('requires_terminal')
                        ->label(__('settings.requires_terminal'))
                        ->boolean(),
                    Infolists\Components\IconEntry::make('requires_customer_profile')
                        ->label(__('settings.requires_customer_profile'))
                        ->boolean(),
                    Infolists\Components\IconEntry::make('is_active')
                        ->label(__('settings.is_active'))
                        ->boolean(),
                ])->columns(3),

            Infolists\Components\Section::make(__('settings.fee_configuration'))
                ->schema([
                    Infolists\Components\TextEntry::make('processing_fee_percent')
                        ->label(__('settings.processing_fee_percent'))
                        ->suffix('%')
                        ->placeholder('-'),
                    Infolists\Components\TextEntry::make('processing_fee_fixed')
                        ->label(__('settings.processing_fee_fixed'))
                        ->prefix('SAR ')
                        ->placeholder('-'),
                    Infolists\Components\TextEntry::make('min_amount')
                        ->label(__('settings.min_amount'))
                        ->prefix('SAR ')
                        ->placeholder('-'),
                    Infolists\Components\TextEntry::make('max_amount')
                        ->label(__('settings.max_amount'))
                        ->prefix('SAR ')
                        ->placeholder('-'),
                ])->columns(4),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('method_key')
                    ->label(__('settings.method_key'))
                    ->formatStateUsing(fn ($state) => __('settings.pm_' . ($state->value ?? $state)))
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('name')
                    ->label(__('settings.name_en'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('name_ar')
                    ->label(__('settings.name_ar'))
                    ->toggleable(),
                Tables\Columns\TextColumn::make('category')
                    ->label(__('settings.category'))
                    ->formatStateUsing(fn ($state) => __('settings.pmc_' . ($state->value ?? $state)))
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        PaymentMethodCategory::Cash => 'success',
                        PaymentMethodCategory::Card => 'primary',
                        PaymentMethodCategory::Digital => 'info',
                        PaymentMethodCategory::Credit => 'warning',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('processing_fee_percent')
                    ->label(__('settings.processing_fee_percent'))
                    ->suffix('%')
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\IconColumn::make('requires_terminal')
                    ->label(__('settings.requires_terminal'))
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\IconColumn::make('is_active')
                    ->label(__('settings.is_active'))
                    ->boolean(),
                Tables\Columns\TextColumn::make('sort_order')
                    ->label(__('settings.sort_order'))
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('category')
                    ->label(__('settings.category'))
                    ->options(collect(PaymentMethodCategory::cases())->mapWithKeys(fn ($c) => [$c->value => __('settings.pmc_' . $c->value)])),
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label(__('settings.is_active')),
                Tables\Filters\TernaryFilter::make('requires_terminal')
                    ->label(__('settings.requires_terminal')),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('toggle_active')
                    ->label(fn (PaymentMethod $record) => $record->is_active ? __('settings.deactivate') : __('settings.activate'))
                    ->icon(fn (PaymentMethod $record) => $record->is_active ? 'heroicon-o-x-circle' : 'heroicon-o-check-circle')
                    ->color(fn (PaymentMethod $record) => $record->is_active ? 'danger' : 'success')
                    ->requiresConfirmation()
                    ->action(function (PaymentMethod $record) {
                        $record->update(['is_active' => ! $record->is_active]);
                        AdminActivityLog::record(
                            adminUserId: auth('admin')->id(),
                            action: $record->is_active ? 'activate_payment_method' : 'deactivate_payment_method',
                            entityType: 'payment_method',
                            entityId: $record->id,
                            details: ['method_key' => $record->method_key->value ?? $record->method_key, 'name' => $record->name],
                        );
                    }),
                Tables\Actions\DeleteAction::make()
                    ->before(function ($record) {
                        AdminActivityLog::record(
                            adminUserId: auth('admin')->id(),
                            action: 'delete_payment_method',
                            entityType: 'payment_method',
                            entityId: $record->id,
                            details: ['method_key' => $record->method_key->value ?? $record->method_key, 'name' => $record->name],
                        );
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->reorderable('sort_order')
            ->defaultSort('sort_order', 'asc');
    }

    public static function getPages(): array
    {
        return [
            'index' => PaymentMethodResource\Pages\ListPaymentMethods::route('/'),
            'create' => PaymentMethodResource\Pages\CreatePaymentMethod::route('/create'),
            'view' => PaymentMethodResource\Pages\ViewPaymentMethod::route('/{record}'),
            'edit' => PaymentMethodResource\Pages\EditPaymentMethod::route('/{record}/edit'),
        ];
    }
}
