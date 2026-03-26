<?php

namespace App\Filament\Resources;

use App\Domain\AdminPanel\Models\AdminActivityLog;
use App\Domain\SystemConfig\Enums\TranslationCategory;
use App\Domain\SystemConfig\Models\MasterTranslationString;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class MasterTranslationStringResource extends Resource
{
    protected static ?string $model = MasterTranslationString::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = null;

    protected static ?int $navigationSort = 9;

    public static function getNavigationLabel(): string
    {
        return __('settings.translations');
    }

    public static function getModelLabel(): string
    {
        return __('settings.translation_string');
    }

    public static function getPluralModelLabel(): string
    {
        return __('settings.translations');
    }

    public static function canAccess(): bool
    {
        $user = auth('admin')->user();
        return $user && $user->hasAnyPermission(['settings.translations']);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make(__('settings.translation_details'))
                ->schema([
                    Forms\Components\TextInput::make('string_key')
                        ->label(__('settings.string_key'))
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->maxLength(255)
                        ->helperText(__('settings.string_key_helper')),
                    Forms\Components\Select::make('category')
                        ->label(__('settings.category'))
                        ->options(collect(TranslationCategory::cases())->mapWithKeys(fn ($c) => [$c->value => __('settings.trans_cat_' . $c->value)]))
                        ->required()
                        ->native(false),
                    Forms\Components\Textarea::make('description')
                        ->label(__('settings.description'))
                        ->rows(2)
                        ->columnSpanFull(),
                ])->columns(2),

            Forms\Components\Section::make(__('settings.translation_values'))
                ->schema([
                    Forms\Components\Textarea::make('value_en')
                        ->label(__('settings.value_en'))
                        ->required()
                        ->rows(3),
                    Forms\Components\Textarea::make('value_ar')
                        ->label(__('settings.value_ar'))
                        ->required()
                        ->rows(3),
                ])->columns(2),

            Forms\Components\Section::make(__('settings.options'))
                ->schema([
                    Forms\Components\Toggle::make('is_overridable')
                        ->label(__('settings.is_overridable'))
                        ->helperText(__('settings.overridable_helper'))
                        ->default(true),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('string_key')
                    ->label(__('settings.string_key'))
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->limit(40),
                Tables\Columns\TextColumn::make('category')
                    ->label(__('settings.category'))
                    ->formatStateUsing(fn ($state) => __('settings.trans_cat_' . $state->value))
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        TranslationCategory::Ui => 'primary',
                        TranslationCategory::Receipt => 'success',
                        TranslationCategory::Notification => 'warning',
                        TranslationCategory::Report => 'info',
                        TranslationCategory::Common => 'gray',
                        TranslationCategory::Pos => 'danger',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('value_en')
                    ->label(__('settings.value_en'))
                    ->limit(40)
                    ->searchable(),
                Tables\Columns\TextColumn::make('value_ar')
                    ->label(__('settings.value_ar'))
                    ->limit(40)
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\IconColumn::make('is_overridable')
                    ->label(__('settings.is_overridable'))
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('category')
                    ->label(__('settings.category'))
                    ->options(collect(TranslationCategory::cases())->mapWithKeys(fn ($c) => [$c->value => __('settings.trans_cat_' . $c->value)])),
                Tables\Filters\TernaryFilter::make('is_overridable')
                    ->label(__('settings.is_overridable')),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->before(function ($record) {
                        AdminActivityLog::record(
                            adminUserId: auth('admin')->id(),
                            action: 'delete_translation',
                            entityType: 'master_translation_string',
                            entityId: $record->id,
                            details: ['string_key' => $record->string_key],
                        );
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('string_key', 'asc');
    }

    public static function getPages(): array
    {
        return [
            'index' => MasterTranslationStringResource\Pages\ListMasterTranslationStrings::route('/'),
            'create' => MasterTranslationStringResource\Pages\CreateMasterTranslationString::route('/create'),
            'edit' => MasterTranslationStringResource\Pages\EditMasterTranslationString::route('/{record}/edit'),
        ];
    }
}
