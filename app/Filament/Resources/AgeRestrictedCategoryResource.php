<?php

namespace App\Filament\Resources;

use App\Domain\AdminPanel\Models\AdminActivityLog;
use App\Domain\SystemConfig\Models\AgeRestrictedCategory;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class AgeRestrictedCategoryResource extends Resource
{
    protected static ?string $model = AgeRestrictedCategory::class;

    protected static ?string $navigationIcon = 'heroicon-o-shield-exclamation';

    protected static ?string $navigationGroup = null;

    public static function getNavigationGroup(): ?string
    {
        return __('nav.group_settings');
    }

    protected static ?string $navigationLabel = null;

    protected static ?int $navigationSort = 4;

    public static function getNavigationLabel(): string
    {
        return __('settings.age_restrictions');
    }

    public static function getModelLabel(): string
    {
        return __('settings.age_restriction');
    }

    public static function getPluralModelLabel(): string
    {
        return __('settings.age_restrictions');
    }

    public static function canAccess(): bool
    {
        $user = auth('admin')->user();
        return $user && $user->hasAnyPermission(['settings.edit']);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make(__('settings.restriction_details'))
                ->schema([
                    Forms\Components\TextInput::make('category_slug')
                        ->label(__('settings.category_slug'))
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->maxLength(100)
                        ->helperText(__('settings.category_slug_helper')),
                    Forms\Components\TextInput::make('min_age')
                        ->label(__('settings.min_age'))
                        ->required()
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(99)
                        ->suffix(__('settings.years')),
                    Forms\Components\Toggle::make('is_active')
                        ->label(__('settings.is_active'))
                        ->default(true),
                ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('category_slug')
                    ->label(__('settings.category_slug'))
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('warning'),
                Tables\Columns\TextColumn::make('min_age')
                    ->label(__('settings.min_age'))
                    ->suffix(' ' . __('settings.years'))
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label(__('settings.is_active'))
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label(__('settings.is_active')),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->before(function ($record) {
                        AdminActivityLog::record(
                            adminUserId: auth('admin')->id(),
                            action: 'delete_age_restriction',
                            entityType: 'age_restricted_category',
                            entityId: $record->id,
                            details: ['slug' => $record->category_slug, 'min_age' => $record->min_age],
                        );
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('category_slug', 'asc');
    }

    public static function getPages(): array
    {
        return [
            'index' => AgeRestrictedCategoryResource\Pages\ListAgeRestrictedCategories::route('/'),
            'create' => AgeRestrictedCategoryResource\Pages\CreateAgeRestrictedCategory::route('/create'),
            'edit' => AgeRestrictedCategoryResource\Pages\EditAgeRestrictedCategory::route('/{record}/edit'),
        ];
    }
}
