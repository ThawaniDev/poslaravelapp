<?php

namespace App\Filament\Resources;

use App\Domain\ContentOnboarding\Models\MarketplaceCategory;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class MarketplaceCategoryResource extends Resource
{
    protected static ?string $model = MarketplaceCategory::class;

    protected static ?string $navigationIcon = 'heroicon-o-tag';

    protected static ?string $navigationGroup = 'UI Management';

    protected static ?string $navigationLabel = null;

    protected static ?int $navigationSort = 9;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationLabel(): string
    {
        return __('ui.nav_marketplace_categories');
    }

    public static function canAccess(): bool
    {
        $user = auth('admin')->user();

        return $user && $user->hasAnyPermission(['ui.manage']);
    }

    // ─── Form ────────────────────────────────────────────────

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make(__('ui.category_info'))
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label(__('ui.name_en'))
                        ->required()
                        ->maxLength(100)
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn (Forms\Set $set, ?string $state) => $set('slug', Str::slug($state))),
                    Forms\Components\TextInput::make('name_ar')
                        ->label(__('ui.name_ar'))
                        ->required()
                        ->maxLength(100),
                    Forms\Components\TextInput::make('slug')
                        ->label(__('ui.slug'))
                        ->required()
                        ->maxLength(50)
                        ->unique(ignoreRecord: true),
                    Forms\Components\TextInput::make('icon')
                        ->label(__('ui.icon'))
                        ->maxLength(50),
                    Forms\Components\Textarea::make('description')
                        ->label(__('ui.description_en'))
                        ->maxLength(500),
                    Forms\Components\Textarea::make('description_ar')
                        ->label(__('ui.description_ar'))
                        ->maxLength(500),
                    Forms\Components\Select::make('parent_id')
                        ->label(__('ui.parent_category'))
                        ->options(MarketplaceCategory::whereNull('parent_id')->pluck('name', 'id'))
                        ->searchable()
                        ->nullable(),
                    Forms\Components\TextInput::make('sort_order')
                        ->label(__('ui.sort_order'))
                        ->numeric()
                        ->default(0),
                    Forms\Components\Toggle::make('is_active')
                        ->label(__('ui.is_active'))
                        ->default(true),
                ])
                ->columns(2),
        ]);
    }

    // ─── Table ───────────────────────────────────────────────

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label(__('ui.name_en'))
                    ->searchable()
                    ->sortable()
                    ->description(fn (MarketplaceCategory $r) => $r->name_ar),
                Tables\Columns\TextColumn::make('slug')
                    ->label(__('ui.slug'))
                    ->badge()
                    ->color('gray'),
                Tables\Columns\TextColumn::make('parent.name')
                    ->label(__('ui.parent_category'))
                    ->badge()
                    ->color('info')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('listings_count')
                    ->label(__('ui.listings_count'))
                    ->counts('listings')
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label(__('ui.is_active'))
                    ->boolean()
                    ->sortable(),
                Tables\Columns\TextColumn::make('sort_order')
                    ->label(__('ui.sort_order'))
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')->label(__('ui.is_active')),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('sort_order');
    }

    // ─── Pages ───────────────────────────────────────────────

    public static function getPages(): array
    {
        return [
            'index' => MarketplaceCategoryResource\Pages\ListMarketplaceCategories::route('/'),
            'create' => MarketplaceCategoryResource\Pages\CreateMarketplaceCategory::route('/create'),
            'edit' => MarketplaceCategoryResource\Pages\EditMarketplaceCategory::route('/{record}/edit'),
        ];
    }
}
