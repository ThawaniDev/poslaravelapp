<?php

namespace App\Filament\Resources;

use App\Domain\Subscription\Models\PlanAddOn;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PlanAddOnResource extends Resource
{
    protected static ?string $model = PlanAddOn::class;

    protected static ?string $navigationIcon = 'heroicon-o-puzzle-piece';

    protected static ?string $navigationGroup = 'Subscription & Billing';

    protected static ?string $navigationLabel = 'Plan Add-Ons';

    protected static ?int $navigationSort = 5;

    public static function canAccess(): bool
    {
        $user = auth('admin')->user();

        return $user && $user->hasAnyPermission(['billing.plans']);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Add-On Details')
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->required()
                        ->maxLength(255),

                    Forms\Components\TextInput::make('name_ar')
                        ->label('Name (Arabic)')
                        ->maxLength(255),

                    Forms\Components\TextInput::make('slug')
                        ->required()
                        ->maxLength(255)
                        ->unique(ignoreRecord: true)
                        ->helperText('URL-safe identifier'),

                    Forms\Components\Toggle::make('is_active')
                        ->default(true),
                ])
                ->columns(2),

            Forms\Components\Section::make('Pricing')
                ->schema([
                    Forms\Components\TextInput::make('monthly_price')
                        ->required()
                        ->numeric()
                        ->minValue(0)
                        ->prefix('SAR')
                        ->label('Monthly Price'),
                ])
                ->columns(2),

            Forms\Components\Section::make('Description')
                ->schema([
                    Forms\Components\Textarea::make('description')
                        ->rows(3)
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('name_ar')
                    ->label('Arabic Name')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('slug')
                    ->color('gray')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('monthly_price')
                    ->money('SAR')
                    ->sortable(),

                Tables\Columns\TextColumn::make('store_add_ons_count')
                    ->counts('storeAddOns')
                    ->label('Active Stores')
                    ->badge()
                    ->color('info'),

                Tables\Columns\IconColumn::make('is_active')
                    ->boolean(),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active Status'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('toggle_active')
                    ->icon(fn (PlanAddOn $record) => $record->is_active ? 'heroicon-m-x-circle' : 'heroicon-m-check-circle')
                    ->color(fn (PlanAddOn $record) => $record->is_active ? 'danger' : 'success')
                    ->label(fn (PlanAddOn $record) => $record->is_active ? 'Deactivate' : 'Activate')
                    ->requiresConfirmation()
                    ->action(fn (PlanAddOn $record) => $record->update(['is_active' => ! $record->is_active])),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make('Add-On Details')
                ->schema([
                    Infolists\Components\TextEntry::make('name')->weight('bold'),
                    Infolists\Components\TextEntry::make('name_ar')->label('Arabic')->placeholder('—'),
                    Infolists\Components\TextEntry::make('slug')->color('gray'),
                    Infolists\Components\IconEntry::make('is_active')->boolean(),
                ])
                ->columns(4),

            Infolists\Components\Section::make('Pricing')
                ->schema([
                    Infolists\Components\TextEntry::make('monthly_price')->money('SAR'),
                ])
                ->columns(2),

            Infolists\Components\Section::make('Description')
                ->schema([
                    Infolists\Components\TextEntry::make('description')->placeholder('No description'),
                ]),
        ]);
    }

    public static function getRelations(): array
    {
        return [
            PlanAddOnResource\RelationManagers\StoreAddOnsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => PlanAddOnResource\Pages\ListPlanAddOns::route('/'),
            'create' => PlanAddOnResource\Pages\CreatePlanAddOn::route('/create'),
            'view' => PlanAddOnResource\Pages\ViewPlanAddOn::route('/{record}'),
            'edit' => PlanAddOnResource\Pages\EditPlanAddOn::route('/{record}/edit'),
        ];
    }
}
