<?php

namespace App\Filament\Resources;

use App\Domain\Payment\Enums\InstallmentProvider;
use App\Domain\Payment\Models\InstallmentProviderConfig;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class InstallmentProviderConfigResource extends Resource
{
    protected static ?string $model = InstallmentProviderConfig::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationGroup = null;

    public static function getNavigationGroup(): ?string
    {
        return __('nav.group_integrations');
    }

    protected static ?string $navigationLabel = null;

    public static function getNavigationLabel(): string
    {
        return __('nav.installment_providers');
    }

    protected static ?int $navigationSort = 10;

    protected static ?string $recordTitleAttribute = 'name';

    public static function canAccess(): bool
    {
        $user = auth('admin')->user();

        return $user && $user->hasAnyPermission(['installments.configure', 'super_admin']);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make(__('Provider Details'))
                ->schema([
                    Forms\Components\Select::make('provider')
                        ->options(InstallmentProvider::class)
                        ->required()
                        ->native(false),

                    Forms\Components\TextInput::make('name')
                        ->required()
                        ->maxLength(100)
                        ->label(__('Name (EN)')),

                    Forms\Components\TextInput::make('name_ar')
                        ->maxLength(100)
                        ->label(__('Name (AR)')),

                    Forms\Components\Textarea::make('description')
                        ->maxLength(500)
                        ->label(__('Description (EN)')),

                    Forms\Components\Textarea::make('description_ar')
                        ->maxLength(500)
                        ->label(__('Description (AR)')),

                    Forms\Components\TextInput::make('logo_url')
                        ->url()
                        ->maxLength(500)
                        ->prefixIcon('heroicon-m-photo')
                        ->label(__('Logo URL')),
                ])
                ->columns(2),

            Forms\Components\Section::make(__('Configuration'))
                ->schema([
                    Forms\Components\TagsInput::make('supported_currencies')
                        ->placeholder('e.g. SAR, OMR, AED')
                        ->label(__('Supported Currencies')),

                    Forms\Components\TextInput::make('min_amount')
                        ->numeric()
                        ->prefix('SAR')
                        ->label(__('Minimum Amount')),

                    Forms\Components\TextInput::make('max_amount')
                        ->numeric()
                        ->prefix('SAR')
                        ->label(__('Maximum Amount')),

                    Forms\Components\TagsInput::make('supported_installment_counts')
                        ->placeholder('e.g. 3, 4, 6')
                        ->label(__('Installment Counts')),

                    Forms\Components\Select::make('environment')
                        ->options([
                            'sandbox' => 'Sandbox',
                            'production' => 'Production',
                        ])
                        ->required()
                        ->native(false)
                        ->default('sandbox'),

                    Forms\Components\TextInput::make('sort_order')
                        ->numeric()
                        ->default(0),
                ])
                ->columns(3),

            Forms\Components\Section::make(__('Status'))
                ->schema([
                    Forms\Components\Toggle::make('is_enabled')
                        ->default(true)
                        ->label(__('Enabled')),

                    Forms\Components\Toggle::make('is_under_maintenance')
                        ->default(false)
                        ->label(__('Under Maintenance')),

                    Forms\Components\TextInput::make('maintenance_message')
                        ->maxLength(500)
                        ->label(__('Maintenance Message (EN)'))
                        ->visible(fn (Forms\Get $get) => $get('is_under_maintenance')),

                    Forms\Components\TextInput::make('maintenance_message_ar')
                        ->maxLength(500)
                        ->label(__('Maintenance Message (AR)'))
                        ->visible(fn (Forms\Get $get) => $get('is_under_maintenance')),
                ])
                ->columns(2),

            Forms\Components\Section::make(__('Platform Config'))
                ->schema([
                    Forms\Components\KeyValue::make('platform_config')
                        ->label(__('Platform Configuration'))
                        ->keyLabel(__('Key'))
                        ->valueLabel(__('Value'))
                        ->addActionLabel(__('Add config field'))
                        ->helperText(__('Provider-specific configuration (API keys, merchant IDs, etc.)'))
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('provider')
                    ->badge()
                    ->color(fn ($state) => match ($state?->value ?? $state) {
                        'tabby' => 'success',
                        'tamara' => 'info',
                        'mispay' => 'warning',
                        'madfu' => 'danger',
                        default => 'gray',
                    })
                    ->searchable(),

                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->label(__('Name')),

                Tables\Columns\TextColumn::make('environment')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'sandbox' => 'warning',
                        'production' => 'success',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('min_amount')
                    ->money('SAR')
                    ->label(__('Min')),

                Tables\Columns\TextColumn::make('max_amount')
                    ->money('SAR')
                    ->label(__('Max')),

                Tables\Columns\IconColumn::make('is_enabled')
                    ->boolean()
                    ->label(__('Enabled')),

                Tables\Columns\IconColumn::make('is_under_maintenance')
                    ->boolean()
                    ->trueColor('danger')
                    ->falseColor('success')
                    ->label(__('Maintenance')),

                Tables\Columns\TextColumn::make('sort_order')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->label(__('Last Updated')),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('provider')
                    ->options(InstallmentProvider::class),

                Tables\Filters\TernaryFilter::make('is_enabled')
                    ->label(__('Enabled')),

                Tables\Filters\TernaryFilter::make('is_under_maintenance')
                    ->label(__('Maintenance Mode')),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('toggle_enabled')
                    ->icon(fn (InstallmentProviderConfig $record) => $record->is_enabled ? 'heroicon-m-x-circle' : 'heroicon-m-check-circle')
                    ->color(fn (InstallmentProviderConfig $record) => $record->is_enabled ? 'danger' : 'success')
                    ->label(fn (InstallmentProviderConfig $record) => $record->is_enabled ? __('Disable') : __('Enable'))
                    ->requiresConfirmation()
                    ->action(function (InstallmentProviderConfig $record) {
                        $record->update(['is_enabled' => ! $record->is_enabled]);

                        Notification::make()
                            ->title($record->is_enabled ? __('Provider enabled') : __('Provider disabled'))
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('sort_order');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make(__('Provider Details'))
                ->schema([
                    Infolists\Components\TextEntry::make('provider')->badge(),
                    Infolists\Components\TextEntry::make('name'),
                    Infolists\Components\TextEntry::make('name_ar'),
                    Infolists\Components\TextEntry::make('environment')->badge(),
                    Infolists\Components\IconEntry::make('is_enabled')->boolean()->label(__('Enabled')),
                    Infolists\Components\IconEntry::make('is_under_maintenance')->boolean()->label(__('Maintenance')),
                ])
                ->columns(3),

            Infolists\Components\Section::make(__('Amounts & Currencies'))
                ->schema([
                    Infolists\Components\TextEntry::make('min_amount')->money('SAR'),
                    Infolists\Components\TextEntry::make('max_amount')->money('SAR'),
                    Infolists\Components\TextEntry::make('supported_currencies')
                        ->badge()
                        ->separator(','),
                    Infolists\Components\TextEntry::make('supported_installment_counts')
                        ->badge()
                        ->separator(','),
                ])
                ->columns(4),

            Infolists\Components\Section::make(__('Timestamps'))
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
            'index' => InstallmentProviderConfigResource\Pages\ListInstallmentProviderConfigs::route('/'),
            'create' => InstallmentProviderConfigResource\Pages\CreateInstallmentProviderConfig::route('/create'),
            'view' => InstallmentProviderConfigResource\Pages\ViewInstallmentProviderConfig::route('/{record}'),
            'edit' => InstallmentProviderConfigResource\Pages\EditInstallmentProviderConfig::route('/{record}/edit'),
        ];
    }
}
