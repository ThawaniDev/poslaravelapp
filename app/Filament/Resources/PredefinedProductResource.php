<?php

namespace App\Filament\Resources;

use App\Domain\Catalog\Enums\ProductUnit;
use App\Domain\PredefinedCatalog\Models\PredefinedProduct;
use App\Services\SupabaseStorageService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PredefinedProductResource extends Resource
{
    protected static ?string $model = PredefinedProduct::class;

    protected static ?string $navigationIcon = 'heroicon-o-cube';

    protected static ?string $navigationGroup = null;

    public static function getNavigationGroup(): ?string
    {
        return __('nav.group_content');
    }

    protected static ?string $navigationLabel = null;

    public static function getNavigationLabel(): string
    {
        return __('nav.predefined_products');
    }

    protected static ?int $navigationSort = 4;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getModelLabel(): string
    {
        return __('Predefined Product');
    }

    public static function getPluralModelLabel(): string
    {
        return __('nav.predefined_products');
    }

    public static function canAccess(): bool
    {
        $user = auth('admin')->user();

        return $user && $user->hasAnyPermission(['content.view', 'content.manage']);
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'name_ar', 'sku', 'barcode'];
    }

    // ─── Form ────────────────────────────────────────────────────

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Tabs::make('ProductTabs')
                ->tabs([
                    // ── Tab 1: Basic Info ─────────────────────────
                    Forms\Components\Tabs\Tab::make(__('Basic Info'))
                        ->icon('heroicon-o-cube')
                        ->schema([
                            Forms\Components\Section::make(__('Product Identity'))
                                ->schema([
                                    Forms\Components\Select::make('business_type_id')
                                        ->label(__('Business Type'))
                                        ->relationship('businessType', 'name')
                                        ->searchable()
                                        ->preload()
                                        ->required(),
                                    Forms\Components\Select::make('predefined_category_id')
                                        ->label(__('Category'))
                                        ->relationship('predefinedCategory', 'name')
                                        ->searchable()
                                        ->preload()
                                        ->required(),
                                    Forms\Components\TextInput::make('name')
                                        ->label(__('Name (EN)'))
                                        ->required()
                                        ->maxLength(255),
                                    Forms\Components\TextInput::make('name_ar')
                                        ->label(__('Name (AR)'))
                                        ->required()
                                        ->maxLength(255),
                                    Forms\Components\Textarea::make('description')
                                        ->label(__('Description (EN)'))
                                        ->maxLength(1000)
                                        ->columnSpanFull(),
                                    Forms\Components\Textarea::make('description_ar')
                                        ->label(__('Description (AR)'))
                                        ->maxLength(1000)
                                        ->columnSpanFull(),
                                ])
                                ->columns(2),
                        ]),

                    // ── Tab 2: Pricing & Inventory ────────────────
                    Forms\Components\Tabs\Tab::make(__('Pricing & Inventory'))
                        ->icon('heroicon-o-currency-dollar')
                        ->schema([
                            Forms\Components\Section::make(__('Identifiers'))
                                ->schema([
                                    Forms\Components\TextInput::make('sku')
                                        ->label(__('SKU'))
                                        ->maxLength(100),
                                    Forms\Components\TextInput::make('barcode')
                                        ->label(__('Barcode'))
                                        ->maxLength(100),
                                ])
                                ->columns(2),

                            Forms\Components\Section::make(__('Pricing'))
                                ->schema([
                                    Forms\Components\TextInput::make('sell_price')
                                        ->label(__('Sell Price'))
                                        ->numeric()
                                        ->step(0.01)
                                        ->prefix('SAR')
                                        ->required(),
                                    Forms\Components\TextInput::make('cost_price')
                                        ->label(__('Cost Price'))
                                        ->numeric()
                                        ->step(0.01)
                                        ->prefix('SAR'),
                                    Forms\Components\TextInput::make('tax_rate')
                                        ->label(__('Tax Rate %'))
                                        ->numeric()
                                        ->step(0.01)
                                        ->default(15.00)
                                        ->suffix('%'),
                                ])
                                ->columns(3),

                            Forms\Components\Section::make(__('Unit & Weight'))
                                ->schema([
                                    Forms\Components\Select::make('unit')
                                        ->options(ProductUnit::class)
                                        ->default(ProductUnit::Piece)
                                        ->required()
                                        ->native(false),
                                    Forms\Components\Toggle::make('is_weighable')
                                        ->label(__('Weighable'))
                                        ->default(false)
                                        ->live(),
                                    Forms\Components\TextInput::make('tare_weight')
                                        ->label(__('Tare Weight'))
                                        ->numeric()
                                        ->step(0.01)
                                        ->default(0)
                                        ->suffix('kg')
                                        ->visible(fn (Forms\Get $get) => $get('is_weighable')),
                                ])
                                ->columns(3),
                        ]),

                    // ── Tab 3: Settings & Media ───────────────────
                    Forms\Components\Tabs\Tab::make(__('Settings & Media'))
                        ->icon('heroicon-o-cog-6-tooth')
                        ->schema([
                            Forms\Components\Section::make(__('Flags'))
                                ->schema([
                                    Forms\Components\Toggle::make('is_active')
                                        ->label(__('Active'))
                                        ->default(true),
                                    Forms\Components\Toggle::make('age_restricted')
                                        ->label(__('Age Restricted'))
                                        ->default(false)
                                        ->helperText(__('Requires age verification at checkout')),
                                ])
                                ->columns(2),

                            Forms\Components\Section::make(__('Image'))
                                ->schema([
                                    Forms\Components\FileUpload::make('image_url')
                                        ->label(__('Product Image'))
                                        ->image()
                                        ->imageEditor()
                                        ->maxSize(5120)
                                        ->columnSpanFull()
                                        ->saveUploadedFileUsing(function ($file) {
                                            return app(SupabaseStorageService::class)->upload($file, 'ProductsImages');
                                        })
                                        ->deleteUploadedFileUsing(function ($file) {
                                            app(SupabaseStorageService::class)->delete($file);
                                        }),
                                ]),
                        ]),
                ])
                ->columnSpanFull()
                ->persistTabInQueryString(),
        ]);
    }

    // ─── Table ───────────────────────────────────────────────────

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('image_url')
                    ->label(__('Image'))
                    ->getStateUsing(fn (PredefinedProduct $record) => SupabaseStorageService::resolveUrl($record->image_url))
                    ->circular()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->description(fn (PredefinedProduct $record) => $record->name_ar)
                    ->wrap(),
                Tables\Columns\TextColumn::make('predefinedCategory.name')
                    ->label(__('Category'))
                    ->sortable()
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('businessType.name')
                    ->label(__('Business Type'))
                    ->badge()
                    ->color('info')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('sku')
                    ->label(__('SKU'))
                    ->searchable()
                    ->badge()
                    ->color('gray')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('barcode')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('sell_price')
                    ->label(__('Sell Price'))
                    ->money('SAR')
                    ->sortable(),
                Tables\Columns\TextColumn::make('cost_price')
                    ->label(__('Cost Price'))
                    ->money('SAR')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('unit')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        ProductUnit::Piece => 'primary',
                        ProductUnit::Kg => 'success',
                        ProductUnit::Litre => 'info',
                        ProductUnit::Custom => 'gray',
                        default => 'gray',
                    })
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('tax_rate')
                    ->label(__('Tax %'))
                    ->suffix('%')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean()
                    ->label(__('Active'))
                    ->sortable(),
                Tables\Columns\IconColumn::make('age_restricted')
                    ->boolean()
                    ->label(__('Age'))
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('images_count')
                    ->counts('images')
                    ->label(__('Images'))
                    ->badge()
                    ->color('info')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime('M j, Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')->label(__('Active')),
                Tables\Filters\TernaryFilter::make('age_restricted')->label(__('Age Restricted')),
                Tables\Filters\TernaryFilter::make('is_weighable')->label(__('Weighable')),
                Tables\Filters\SelectFilter::make('business_type_id')
                    ->relationship('businessType', 'name')
                    ->searchable()
                    ->preload()
                    ->label(__('Business Type')),
                Tables\Filters\SelectFilter::make('predefined_category_id')
                    ->relationship('predefinedCategory', 'name')
                    ->searchable()
                    ->preload()
                    ->label(__('Category')),
                Tables\Filters\SelectFilter::make('unit')
                    ->options(ProductUnit::class)
                    ->label(__('Unit')),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('duplicate')
                    ->label(__('Duplicate'))
                    ->icon('heroicon-o-document-duplicate')
                    ->color('gray')
                    ->visible(fn () => auth('admin')->user()?->hasPermission('content.manage'))
                    ->requiresConfirmation()
                    ->action(function (PredefinedProduct $record) {
                        $newProduct = $record->replicate();
                        $newProduct->name = $record->name . ' (Copy)';
                        $newProduct->sku = $record->sku ? $record->sku . '-copy' : null;
                        $newProduct->save();

                        foreach ($record->images as $image) {
                            $newProduct->images()->create([
                                'image_url' => $image->image_url,
                                'sort_order' => $image->sort_order,
                            ]);
                        }
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn () => auth('admin')->user()?->hasPermission('content.manage')),
                    Tables\Actions\BulkAction::make('bulk_activate')
                        ->label(__('Activate Selected'))
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->deselectRecordsAfterCompletion()
                        ->visible(fn () => auth('admin')->user()?->hasPermission('content.manage'))
                        ->action(fn ($records) => $records->each(fn ($r) => $r->update(['is_active' => true]))),
                    Tables\Actions\BulkAction::make('bulk_deactivate')
                        ->label(__('Deactivate Selected'))
                        ->icon('heroicon-o-no-symbol')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->deselectRecordsAfterCompletion()
                        ->visible(fn () => auth('admin')->user()?->hasPermission('content.manage'))
                        ->action(fn ($records) => $records->each(fn ($r) => $r->update(['is_active' => false]))),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    // ─── Infolist (View Page) ────────────────────────────────────

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Tabs::make('ProductTabs')
                ->tabs([
                    Infolists\Components\Tabs\Tab::make(__('Overview'))
                        ->icon('heroicon-o-cube')
                        ->schema([
                            Infolists\Components\Section::make(__('Product Identity'))
                                ->schema([
                                    Infolists\Components\TextEntry::make('name')->weight('bold'),
                                    Infolists\Components\TextEntry::make('name_ar')->label(__('Name (AR)')),
                                    Infolists\Components\TextEntry::make('businessType.name')->label(__('Business Type'))->badge()->color('info'),
                                    Infolists\Components\TextEntry::make('predefinedCategory.name')->label(__('Category')),
                                    Infolists\Components\TextEntry::make('description')->placeholder(__('N/A'))->columnSpanFull(),
                                    Infolists\Components\TextEntry::make('description_ar')->label(__('Description (AR)'))->placeholder(__('N/A'))->columnSpanFull(),
                                ])
                                ->columns(2),
                        ]),

                    Infolists\Components\Tabs\Tab::make(__('Pricing & Inventory'))
                        ->icon('heroicon-o-currency-dollar')
                        ->schema([
                            Infolists\Components\Section::make(__('Identifiers'))
                                ->schema([
                                    Infolists\Components\TextEntry::make('sku')->label(__('SKU'))->copyable()->placeholder(__('N/A')),
                                    Infolists\Components\TextEntry::make('barcode')->copyable()->placeholder(__('N/A')),
                                ])
                                ->columns(2),
                            Infolists\Components\Section::make(__('Pricing'))
                                ->schema([
                                    Infolists\Components\TextEntry::make('sell_price')->money('SAR'),
                                    Infolists\Components\TextEntry::make('cost_price')->money('SAR')->placeholder(__('N/A')),
                                    Infolists\Components\TextEntry::make('tax_rate')->suffix('%'),
                                ])
                                ->columns(3),
                            Infolists\Components\Section::make(__('Unit & Weight'))
                                ->schema([
                                    Infolists\Components\TextEntry::make('unit')->badge(),
                                    Infolists\Components\IconEntry::make('is_weighable')->boolean()->label(__('Weighable')),
                                    Infolists\Components\TextEntry::make('tare_weight')->suffix(' kg')->placeholder('0'),
                                ])
                                ->columns(3),
                        ]),

                    Infolists\Components\Tabs\Tab::make(__('Settings'))
                        ->icon('heroicon-o-cog-6-tooth')
                        ->schema([
                            Infolists\Components\Section::make(__('Flags'))
                                ->schema([
                                    Infolists\Components\IconEntry::make('is_active')->boolean()->label(__('Active')),
                                    Infolists\Components\IconEntry::make('age_restricted')->boolean()->label(__('Age Restricted')),
                                ])
                                ->columns(2),
                            Infolists\Components\Section::make(__('Media'))
                                ->schema([
                                    Infolists\Components\ImageEntry::make('image_url')
                                        ->label(__('Image'))
                                        ->getStateUsing(fn (PredefinedProduct $record) => SupabaseStorageService::resolveUrl($record->image_url))
                                        ->columnSpanFull(),
                                ]),
                            Infolists\Components\Section::make(__('Timestamps'))
                                ->schema([
                                    Infolists\Components\TextEntry::make('created_at')->dateTime(),
                                    Infolists\Components\TextEntry::make('updated_at')->dateTime(),
                                ])
                                ->columns(2),
                        ]),
                ])
                ->columnSpanFull(),
        ]);
    }

    // ─── Relations ───────────────────────────────────────────────

    public static function getRelations(): array
    {
        return [
            PredefinedProductResource\RelationManagers\ImagesRelationManager::class,
        ];
    }

    // ─── Eloquent Query ──────────────────────────────────────────

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['businessType', 'predefinedCategory']);
    }

    // ─── Pages ───────────────────────────────────────────────────

    public static function getPages(): array
    {
        return [
            'index' => PredefinedProductResource\Pages\ListPredefinedProducts::route('/'),
            'create' => PredefinedProductResource\Pages\CreatePredefinedProduct::route('/create'),
            'view' => PredefinedProductResource\Pages\ViewPredefinedProduct::route('/{record}'),
            'edit' => PredefinedProductResource\Pages\EditPredefinedProduct::route('/{record}/edit'),
        ];
    }
}
