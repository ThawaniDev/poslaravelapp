<?php

namespace App\Filament\Resources;

use App\Domain\DeliveryPlatformRegistry\Enums\DeliveryAuthMethod;
use App\Domain\DeliveryPlatformRegistry\Enums\DeliveryEndpointOperation;
use App\Domain\DeliveryPlatformRegistry\Enums\DeliveryFieldType;
use App\Domain\DeliveryPlatformRegistry\Enums\HttpMethod;
use App\Domain\DeliveryPlatformRegistry\Models\DeliveryPlatform;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

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

    public static function canCreate(): bool
    {
        $user = auth('admin')->user();

        return $user && $user->hasPermissionTo('integrations.manage');
    }

    public static function canEdit($record): bool
    {
        $user = auth('admin')->user();

        return $user && $user->hasPermissionTo('integrations.manage');
    }

    public static function canDelete($record): bool
    {
        $user = auth('admin')->user();
        if (! $user || ! $user->hasPermissionTo('integrations.manage')) {
            return false;
        }

        // Prevent deletion if any store configs reference this platform
        return \App\Domain\DeliveryIntegration\Models\DeliveryPlatformConfig::where('platform', $record->slug)->doesntExist();
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            // ── Basic Info ──────────────────────────────────────
            Forms\Components\Section::make(__('delivery.platform_info'))
                ->description(__('delivery.platform_info_desc'))
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label(__('delivery.platform_name'))
                        ->required()
                        ->maxLength(100)
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn ($state, Forms\Set $set) => $set('slug', Str::slug($state))),

                    Forms\Components\TextInput::make('name_ar')
                        ->label(__('delivery.platform_name_ar'))
                        ->maxLength(100),

                    Forms\Components\TextInput::make('slug')
                        ->label(__('delivery.slug'))
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->maxLength(50)
                        ->helperText(__('delivery.slug_hint')),

                    Forms\Components\TextInput::make('logo_url')
                        ->label(__('delivery.logo_url'))
                        ->url()
                        ->maxLength(500)
                        ->suffixAction(
                            Forms\Components\Actions\Action::make('previewLogo')
                                ->icon('heroicon-o-eye')
                                ->url(fn ($state) => $state, shouldOpenInNewTab: true)
                                ->visible(fn ($state) => filled($state)),
                        ),

                    Forms\Components\Textarea::make('description')
                        ->label(__('delivery.description'))
                        ->maxLength(1000)
                        ->columnSpanFull(),

                    Forms\Components\Textarea::make('description_ar')
                        ->label(__('delivery.description_ar'))
                        ->maxLength(1000)
                        ->columnSpanFull(),
                ])->columns(2),

            // ── API Configuration ───────────────────────────────
            Forms\Components\Section::make(__('delivery.api_configuration'))
                ->description(__('delivery.api_configuration_desc'))
                ->schema([
                    Forms\Components\Select::make('auth_method')
                        ->label(__('delivery.auth_method'))
                        ->options([
                            'bearer' => 'Bearer Token',
                            'api_key' => 'API Key Header',
                            'basic' => 'Basic Auth',
                            'oauth2' => 'OAuth2',
                        ])
                        ->required(),

                    Forms\Components\Select::make('api_type')
                        ->label(__('delivery.api_type'))
                        ->options([
                            'rest' => 'REST API',
                            'webhook' => 'Webhook-based',
                            'polling' => 'Polling',
                        ])
                        ->required()
                        ->default('rest'),

                    Forms\Components\TextInput::make('base_url')
                        ->label(__('delivery.base_url'))
                        ->url()
                        ->maxLength(500)
                        ->placeholder('https://api.platform.com')
                        ->columnSpanFull(),

                    Forms\Components\TextInput::make('documentation_url')
                        ->label(__('delivery.documentation_url'))
                        ->url()
                        ->maxLength(500)
                        ->suffixAction(
                            Forms\Components\Actions\Action::make('openDocs')
                                ->icon('heroicon-o-arrow-top-right-on-square')
                                ->url(fn ($state) => $state, shouldOpenInNewTab: true)
                                ->visible(fn ($state) => filled($state)),
                        ),

                    Forms\Components\TextInput::make('default_commission_percent')
                        ->label(__('delivery.default_commission'))
                        ->numeric()
                        ->suffix('%')
                        ->minValue(0)
                        ->maxValue(100)
                        ->step(0.01),

                    Forms\Components\TagsInput::make('supported_countries')
                        ->label(__('delivery.supported_countries'))
                        ->placeholder('SA, AE, BH...')
                        ->default(['SA']),

                    Forms\Components\Toggle::make('is_active')
                        ->label(__('delivery.is_active'))
                        ->default(true)
                        ->helperText(__('delivery.is_active_hint')),

                    Forms\Components\TextInput::make('sort_order')
                        ->label(__('delivery.sort_order'))
                        ->numeric()
                        ->default(0),
                ])->columns(2),

            // ── Custom Credential Fields ────────────────────────
            Forms\Components\Section::make(__('delivery.credential_fields'))
                ->description(__('delivery.credential_fields_desc'))
                ->schema([
                    Forms\Components\Repeater::make('fields')
                        ->relationship('fields')
                        ->schema([
                            Forms\Components\TextInput::make('field_key')
                                ->label(__('delivery.field_key'))
                                ->required()
                                ->maxLength(50)
                                ->alphaNum()
                                ->placeholder('restaurant_id')
                                ->helperText(__('delivery.field_key_hint')),

                            Forms\Components\TextInput::make('field_label')
                                ->label(__('delivery.field_label'))
                                ->required()
                                ->maxLength(100)
                            ->placeholder(__('delivery.placeholder_restaurant_id')),

                            Forms\Components\Select::make('field_type')
                                ->label(__('delivery.field_type'))
                                ->options([
                                    'text' => 'Text',
                                    'password' => 'Password (masked)',
                                    'url' => 'URL',
                                ])
                                ->required()
                                ->default('text'),

                            Forms\Components\Toggle::make('is_required')
                                ->label(__('delivery.is_required'))
                                ->default(true),

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
                        ->itemLabel(fn (array $state): ?string => $state['field_label'] ?? null)
                        ->columnSpanFull(),
                ]),

            // ── Operation Endpoints ─────────────────────────────
            Forms\Components\Section::make(__('delivery.api_endpoints'))
                ->description(__('delivery.api_endpoints_desc'))
                ->schema([
                    Forms\Components\Repeater::make('endpoints')
                        ->relationship('endpoints')
                        ->schema([
                            Forms\Components\Select::make('operation')
                                ->label(__('delivery.operation'))
                                ->options([
                                    'product_create' => 'Product Create',
                                    'product_update' => 'Product Update',
                                    'product_delete' => 'Product Delete',
                                    'category_sync' => 'Category Sync',
                                    'bulk_menu_push' => 'Bulk Menu Push',
                                    'order_status_update' => 'Order Status Update',
                                    'custom' => 'Custom',
                                ])
                                ->required(),

                            Forms\Components\Select::make('http_method')
                                ->label(__('delivery.http_method'))
                                ->options([
                                    'GET' => 'GET',
                                    'POST' => 'POST',
                                    'PUT' => 'PUT',
                                    'PATCH' => 'PATCH',
                                    'DELETE' => 'DELETE',
                                ])
                                ->required()
                                ->default('POST'),

                            Forms\Components\TextInput::make('url_template')
                                ->label(__('delivery.url_template'))
                                ->required()
                                ->maxLength(500)
                                ->placeholder('/api/v1/restaurants/{restaurant_id}/menu')
                                ->columnSpanFull(),

                            Forms\Components\KeyValue::make('request_mapping')
                                ->label(__('delivery.request_mapping'))
                                ->keyLabel(__('delivery.mapping_target_field'))
                                ->valueLabel(__('delivery.mapping_source_field'))
                                ->reorderable()
                                ->columnSpanFull(),
                        ])
                        ->columns(2)
                        ->defaultItems(0)
                        ->addActionLabel(__('delivery.add_endpoint'))
                        ->reorderableWithButtons()
                        ->collapsible()
                        ->itemLabel(fn (array $state): ?string => isset($state['operation'])
                            ? strtoupper((string) $state['http_method']) . ' · ' . ($state['operation'])
                            : null)
                        ->columnSpanFull(),
                ]),

            // ── Inbound Webhook ─────────────────────────────────
            Forms\Components\Section::make(__('delivery.inbound_webhook'))
                ->description(__('delivery.inbound_webhook_desc'))
                ->schema([
                    Forms\Components\Repeater::make('webhookTemplates')
                        ->relationship('deliveryPlatformWebhookTemplates')
                        ->schema([
                            Forms\Components\TextInput::make('path_template')
                                ->label(__('delivery.webhook_path_template'))
                                ->required()
                                ->maxLength(500)
                                ->placeholder('/webhooks/{platform_slug}/{store_id}')
                                ->columnSpanFull(),
                        ])
                        ->maxItems(1)
                        ->defaultItems(0)
                        ->addActionLabel(__('delivery.add_webhook_template'))
                        ->columnSpanFull(),
                ])->collapsed(fn ($record) => $record !== null),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('logo_url')
                    ->label('')
                    ->circular()
                    ->size(36)
                    ->defaultImageUrl(fn ($record) => 'https://ui-avatars.com/api/?name=' . urlencode($record->name) . '&size=36&background=FD8209&color=fff'),

                Tables\Columns\TextColumn::make('name')
                    ->label(__('delivery.platform_name'))
                    ->description(fn ($record) => $record->name_ar)
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('slug')
                    ->label(__('delivery.slug'))
                    ->badge()
                    ->color('gray')
                    ->fontFamily('mono'),

                Tables\Columns\TextColumn::make('auth_method')
                    ->label(__('delivery.auth_method'))
                    ->badge()
                    ->color(fn ($state) => match ((string) $state) {
                        'bearer' => 'success',
                        'api_key' => 'info',
                        'basic' => 'warning',
                        'oauth2' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn ($state) => strtoupper((string) $state)),

                Tables\Columns\TextColumn::make('api_type')
                    ->label(__('delivery.api_type'))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'rest' => 'success',
                        'webhook' => 'info',
                        'polling' => 'warning',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('fields_count')
                    ->label(__('delivery.fields'))
                    ->counts('fields')
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('endpoints_count')
                    ->label(__('delivery.endpoints'))
                    ->counts('endpoints')
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('default_commission_percent')
                    ->label(__('delivery.commission'))
                    ->suffix('%')
                    ->sortable()
                    ->alignEnd(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label(__('delivery.is_active'))
                    ->boolean()
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('delivery.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('auth_method')
                    ->label(__('delivery.auth_method'))
                    ->options([
                        'bearer' => 'Bearer Token',
                        'api_key' => 'API Key',
                        'basic' => 'Basic Auth',
                        'oauth2' => 'OAuth2',
                    ]),
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
                Tables\Actions\Action::make('testConnectivity')
                    ->label(__('delivery.test_connectivity'))
                    ->icon('heroicon-o-signal')
                    ->color('info')
                    ->requiresConfirmation()
                    ->modalHeading(__('delivery.test_connectivity'))
                    ->modalDescription(__('delivery.test_connectivity_desc'))
                    ->action(function (DeliveryPlatform $record) {
                        if (empty($record->base_url)) {
                            Notification::make()
                                ->title(__('delivery.test_no_base_url'))
                                ->warning()
                                ->send();
                            return;
                        }
                        try {
                            $response = Http::timeout(5)->get($record->base_url);
                            Notification::make()
                                ->title(__('delivery.test_connectivity_result', ['status' => $response->status()]))
                                ->body($response->successful() ? __('delivery.test_connectivity_ok') : __('delivery.test_connectivity_error'))
                                ->color($response->successful() ? 'success' : 'danger')
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title(__('delivery.test_connectivity_failed'))
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    })
                    ->visible(fn (DeliveryPlatform $record) => filled($record->base_url)),

                Tables\Actions\Action::make('toggleActive')
                    ->label(fn (DeliveryPlatform $record) => $record->is_active ? __('delivery.deactivate') : __('delivery.activate'))
                    ->icon(fn (DeliveryPlatform $record) => $record->is_active ? 'heroicon-o-x-circle' : 'heroicon-o-check-circle')
                    ->color(fn (DeliveryPlatform $record) => $record->is_active ? 'warning' : 'success')
                    ->requiresConfirmation()
                    ->action(function (DeliveryPlatform $record) {
                        $record->update(['is_active' => ! $record->is_active]);
                        Notification::make()
                            ->title($record->is_active ? __('delivery.platform_activated') : __('delivery.platform_deactivated'))
                            ->success()
                            ->send();
                    })
                    ->visible(fn () => auth('admin')->user()?->hasPermissionTo('integrations.manage')),

                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn (DeliveryPlatform $record) => static::canDelete($record)),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn () => auth('admin')->user()?->hasPermissionTo('integrations.manage')),
                ]),
            ])
            ->defaultSort('sort_order');
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

