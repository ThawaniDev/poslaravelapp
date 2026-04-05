<?php

namespace App\Filament\Resources;

use App\Domain\ContentOnboarding\Models\BusinessType;
use App\Domain\PredefinedCatalog\Models\PredefinedCategory;
use App\Services\SupabaseStorageService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PredefinedCategoryResource extends Resource
{
    protected static ?string $model = PredefinedCategory::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationGroup = null;

    public static function getNavigationGroup(): ?string
    {
        return __('nav.group_content');
    }

    protected static ?string $navigationLabel = null;

    public static function getNavigationLabel(): string
    {
        return __('nav.predefined_categories');
    }

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getModelLabel(): string
    {
        return __('Predefined Category');
    }

    public static function getPluralModelLabel(): string
    {
        return __('nav.predefined_categories');
    }

    public static function canAccess(): bool
    {
        $user = auth('admin')->user();

        return $user && $user->hasAnyPermission(['content.view', 'content.manage']);
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'name_ar'];
    }

    // ─── Form ────────────────────────────────────────────────────

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make(__('Category Info'))
                ->description(__('Define a predefined product category for store onboarding'))
                ->schema([
                    Forms\Components\Select::make('business_type_id')
                        ->label(__('Business Type'))
                        ->relationship('businessType', 'name')
                        ->searchable()
                        ->preload()
                        ->required(),
                    Forms\Components\Select::make('parent_id')
                        ->label(__('Parent Category'))
                        ->relationship('parent', 'name', fn (Builder $query) => $query->where('is_active', true))
                        ->searchable()
                        ->preload()
                        ->nullable()
                        ->helperText(__('Leave empty for a top-level category')),
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
                    Forms\Components\FileUpload::make('image_url')
                        ->label(__('Category Image'))
                        ->image()
                        ->imageEditor()
                        ->maxSize(5120)
                        ->columnSpanFull()
                        ->saveUploadedFileUsing(function ($file) {
                            return app(SupabaseStorageService::class)->upload($file, 'CategoriesImages');
                        })
                        ->deleteUploadedFileUsing(function ($file) {
                            app(SupabaseStorageService::class)->delete($file);
                        }),
                    Forms\Components\TextInput::make('sort_order')
                        ->numeric()
                        ->default(0),
                    Forms\Components\Toggle::make('is_active')
                        ->default(true),
                ])
                ->columns(2),
        ]);
    }

    // ─── Table ───────────────────────────────────────────────────

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('image_url')
                    ->label(__('Image'))
                    ->getStateUsing(fn (PredefinedCategory $record) => SupabaseStorageService::resolveUrl($record->image_url))
                    ->circular()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->description(fn (PredefinedCategory $record) => $record->name_ar),
                Tables\Columns\TextColumn::make('businessType.name')
                    ->label(__('Business Type'))
                    ->sortable()
                    ->searchable()
                    ->badge()
                    ->color('info'),
                Tables\Columns\TextColumn::make('parent.name')
                    ->label(__('Parent'))
                    ->placeholder(__('Top Level'))
                    ->toggleable(),
                Tables\Columns\TextColumn::make('products_count')
                    ->counts('products')
                    ->label(__('Products'))
                    ->badge()
                    ->color('success')
                    ->sortable(),
                Tables\Columns\TextColumn::make('children_count')
                    ->counts('children')
                    ->label(__('Sub-categories'))
                    ->badge()
                    ->color('info')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean()
                    ->label(__('Active'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('sort_order')
                    ->label(__('Sort'))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime('M j, Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')->label(__('Active')),
                Tables\Filters\SelectFilter::make('business_type_id')
                    ->relationship('businessType', 'name')
                    ->searchable()
                    ->preload()
                    ->label(__('Business Type')),
                Tables\Filters\Filter::make('top_level')
                    ->label(__('Top Level Only'))
                    ->query(fn (Builder $query) => $query->whereNull('parent_id')),
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
                    ->action(function (PredefinedCategory $record) {
                        $newCat = $record->replicate();
                        $newCat->name = $record->name . ' (Copy)';
                        $newCat->save();
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
            ->defaultSort('sort_order', 'asc')
            ->reorderable('sort_order');
    }

    // ─── Infolist (View Page) ────────────────────────────────────

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make(__('Category Info'))
                ->schema([
                    Infolists\Components\TextEntry::make('name')->weight('bold'),
                    Infolists\Components\TextEntry::make('name_ar')->label(__('Name (AR)')),
                    Infolists\Components\TextEntry::make('businessType.name')->label(__('Business Type'))->badge()->color('info'),
                    Infolists\Components\TextEntry::make('parent.name')->label(__('Parent'))->placeholder(__('Top Level')),
                    Infolists\Components\TextEntry::make('description')->placeholder(__('N/A'))->columnSpanFull(),
                    Infolists\Components\TextEntry::make('description_ar')->label(__('Description (AR)'))->placeholder(__('N/A'))->columnSpanFull(),
                    Infolists\Components\ImageEntry::make('image_url')
                        ->label(__('Image'))
                        ->getStateUsing(fn (PredefinedCategory $record) => SupabaseStorageService::resolveUrl($record->image_url))
                        ->columnSpanFull(),
                    Infolists\Components\IconEntry::make('is_active')->boolean()->label(__('Active')),
                    Infolists\Components\TextEntry::make('sort_order'),
                    Infolists\Components\TextEntry::make('created_at')->dateTime(),
                    Infolists\Components\TextEntry::make('updated_at')->dateTime(),
                ])
                ->columns(3),
        ]);
    }

    // ─── Eloquent Query ──────────────────────────────────────────

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['businessType', 'parent']);
    }

    // ─── Pages ───────────────────────────────────────────────────

    public static function getPages(): array
    {
        return [
            'index' => PredefinedCategoryResource\Pages\ListPredefinedCategories::route('/'),
            'create' => PredefinedCategoryResource\Pages\CreatePredefinedCategory::route('/create'),
            'view' => PredefinedCategoryResource\Pages\ViewPredefinedCategory::route('/{record}'),
            'edit' => PredefinedCategoryResource\Pages\EditPredefinedCategory::route('/{record}/edit'),
        ];
    }
}
