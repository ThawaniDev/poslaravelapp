<?php

namespace App\Filament\Resources;

use App\Domain\DeliveryPlatformRegistry\Models\DeliveryPlatform;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class DeliveryPlatformResource extends Resource
{
    protected static ?string $model = DeliveryPlatform::class;

    protected static ?string $navigationIcon = 'heroicon-o-truck';

    protected static ?string $navigationGroup = null;

    public static function getNavigationGroup(): ?string
    {
        return __('nav.group_integrations');
    }

    protected static ?string $navigationLabel = null;

    public static function getNavigationLabel(): string
    {
        return __('nav.delivery_platforms');
    }

    protected static ?int $navigationSort = 1;

    public static function canAccess(): bool
    {
        $user = auth('admin')->user();

        return $user && $user->hasAnyPermission(['integrations.view', 'integrations.manage']);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make(__('delivery.platform_info'))
                ->description(__('delivery.platform_info_desc'))
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label(__('delivery.platform_name'))
                        ->required()
                        ->maxLength(255),
                    Forms\Components\TextInput::make('name_ar')
                        ->label(__('delivery.platform_name_ar'))
                        ->maxLength(255),
                    Forms\Components\TextInput::make('slug')
                        ->label(__('delivery.slug'))
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->maxLength(255),
                    Forms\Components\Textarea::make('description')
                        ->label(__('delivery.description'))
                        ->maxLength(1000)
                        ->columnSpanFull(),
                    Forms\Components\Textarea::make('description_ar')
                        ->label(__('delivery.description_ar'))
                        ->maxLength(1000)
                        ->columnSpanFull(),
                    Forms\Components\TextInput::make('logo_url')
                        ->label(__('delivery.logo_url'))
                        ->url()
                        ->maxLength(500),
                ])->columns(2),

            Forms\Components\Section::make(__('delivery.api_configuration'))
                ->description(__('delivery.api_configuration_desc'))
                ->schema([
                    Forms\Components\Select::make('api_type')
                        ->label(__('delivery.api_type'))
                        ->options([
                            'rest' => 'REST API',
                            'webhook' => 'Webhook-based',
                            'polling' => 'Polling',
                        ])
                        ->required(),
                    Forms\Components\TextInput::make('base_url')
                        ->label(__('delivery.base_url'))
                        ->url()
                        ->maxLength(500),
                    Forms\Components\TextInput::make('documentation_url')
                        ->label(__('delivery.documentation_url'))
                        ->url()
                        ->maxLength(500),
                    Forms\Components\TextInput::make('default_commission_percent')
                        ->label(__('delivery.default_commission'))
                        ->numeric()
                        ->suffix('%')
                        ->minValue(0)
                        ->maxValue(100),
                    Forms\Components\TagsInput::make('supported_countries')
                        ->label(__('delivery.supported_countries'))
                        ->placeholder('SA, AE, BH...')
                        ->columnSpanFull(),
                    Forms\Components\Toggle::make('is_active')
                        ->label(__('delivery.is_active'))
                        ->default(true),
                ])->columns(2),

            Forms\Components\Section::make(__('delivery.credential_fields'))
                ->description(__('delivery.credential_fields_desc'))
                ->schema([
                    Forms\Components\Repeater::make('fields')
                        ->relationship('fields')
                        ->schema([
                            Forms\Components\TextInput::make('field_key')
                                ->label(__('delivery.field_key'))
                                ->required()
                                ->maxLength(100),
                            Forms\Components\TextInput::make('field_label')
                                ->label(__('delivery.field_label'))
                                ->required()
                                ->maxLength(255),
                            Forms\Components\Select::make('field_type')
                                ->label(__('delivery.field_type'))
                                ->options([
                                    'text' => 'Text',
                                    'password' => 'Password',
                                    'url' => 'URL',
                                    'select' => 'Select',
                                    'toggle' => 'Toggle',
                                ])
                                ->required(),
                            Forms\Components\Toggle::make('is_required')
                                ->label(__('delivery.is_required'))
                                ->default(false),
                            Forms\Components\TextInput::make('placeholder')
                                ->label(__('delivery.placeholder'))
                                ->maxLength(255),
                            Forms\Components\TextInput::make('sort_order')
                                ->label(__('delivery.sort_order'))
                                ->numeric()
                                ->default(0),
                        ])
                        ->columns(3)
                        ->defaultItems(0)
                        ->addActionLabel(__('delivery.add_credential_field'))
                        ->reorderableWithButtons()
                        ->collapsible()
                        ->columnSpanFull(),
                ]),

            Forms\Components\Section::make(__('delivery.api_endpoints'))
                ->description(__('delivery.api_endpoints_desc'))
                ->schema([
                    Forms\Components\Repeater::make('endpoints')
                        ->relationship('endpoints')
                        ->schema([
                            Forms\Components\TextInput::make('endpoint_key')
                                ->label(__('delivery.endpoint_key'))
                                ->required()
                                ->maxLength(100),
                            Forms\Components\TextInput::make('endpoint_label')
                                ->label(__('delivery.endpoint_label'))
                                ->required()
                                ->maxLength(255),
                            Forms\Components\Select::make('http_method')
                                ->label(__('delivery.http_method'))
                                ->options([
                                    'GET' => 'GET',
                                    'POST' => 'POST',
                                    'PUT' => 'PUT',
                                    'PATCH' => 'PATCH',
                                    'DELETE' => 'DELETE',
                                ])
                                ->required(),
                            Forms\Components\TextInput::make('url_template')
                                ->label(__('delivery.url_template'))
                                ->required()
                                ->maxLength(500)
                                ->placeholder('/api/v1/orders/{order_id}/status'),
                            Forms\Components\Textarea::make('description')
                                ->label(__('delivery.endpoint_description'))
                                ->maxLength(500),
                        ])
                        ->columns(2)
                        ->defaultItems(0)
                        ->addActionLabel(__('delivery.add_endpoint'))
                        ->reorderableWithButtons()
                        ->collapsible()
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label(__('delivery.platform_name'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('slug')
                    ->label(__('delivery.slug'))
                    ->badge()
                    ->color('gray'),
                Tables\Columns\TextColumn::make('api_type')
                    ->label(__('delivery.api_type'))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'rest' => 'success',
                        'webhook' => 'info',
                        'polling' => 'warning',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('supported_countries')
                    ->label(__('delivery.supported_countries'))
                    ->badge()
                    ->separator(','),
                Tables\Columns\TextColumn::make('default_commission_percent')
                    ->label(__('delivery.commission'))
                    ->suffix('%')
                    ->sortable(),
                Tables\Columns\TextColumn::make('fields_count')
                    ->label(__('delivery.fields'))
                    ->counts('fields'),
                Tables\Columns\TextColumn::make('endpoints_count')
                    ->label(__('delivery.endpoints'))
                    ->counts('endpoints'),
                Tables\Columns\IconColumn::make('is_active')
                    ->label(__('delivery.is_active'))
                    ->boolean(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('delivery.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('api_type')
                    ->label(__('delivery.api_type'))
                    ->options([
                        'rest' => 'REST API',
                        'webhook' => 'Webhook',
                        'polling' => 'Polling',
                    ]),
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label(__('delivery.is_active')),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
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
            'index' => DeliveryPlatformResource\Pages\ListDeliveryPlatforms::route('/'),
            'create' => DeliveryPlatformResource\Pages\CreateDeliveryPlatform::route('/create'),
            'edit' => DeliveryPlatformResource\Pages\EditDeliveryPlatform::route('/{record}/edit'),
        ];
    }

}
