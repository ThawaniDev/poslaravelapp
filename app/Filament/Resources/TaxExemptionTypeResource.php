<?php

namespace App\Filament\Resources;

use App\Domain\AdminPanel\Models\AdminActivityLog;
use App\Domain\SystemConfig\Models\TaxExemptionType;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class TaxExemptionTypeResource extends Resource
{
    protected static ?string $model = TaxExemptionType::class;

    protected static ?string $navigationIcon = 'heroicon-o-receipt-percent';

    protected static ?string $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = null;

    protected static ?int $navigationSort = 3;

    public static function getNavigationLabel(): string
    {
        return __('settings.tax_exemptions');
    }

    public static function getModelLabel(): string
    {
        return __('settings.tax_exemption');
    }

    public static function getPluralModelLabel(): string
    {
        return __('settings.tax_exemptions');
    }

    public static function canAccess(): bool
    {
        $user = auth('admin')->user();
        return $user && $user->hasAnyPermission(['settings.edit']);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make(__('settings.exemption_details'))
                ->schema([
                    Forms\Components\TextInput::make('code')
                        ->label(__('settings.code'))
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->maxLength(50),
                    Forms\Components\Toggle::make('is_active')
                        ->label(__('settings.is_active'))
                        ->default(true),
                    Forms\Components\TextInput::make('name')
                        ->label(__('settings.name_en'))
                        ->required()
                        ->maxLength(255),
                    Forms\Components\TextInput::make('name_ar')
                        ->label(__('settings.name_ar'))
                        ->maxLength(255),
                ])->columns(2),

            Forms\Components\Section::make(__('settings.required_documents'))
                ->schema([
                    Forms\Components\Textarea::make('required_documents')
                        ->label(__('settings.required_documents'))
                        ->rows(4)
                        ->helperText(__('settings.required_documents_helper'))
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label(__('settings.code'))
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('gray'),
                Tables\Columns\TextColumn::make('name')
                    ->label(__('settings.name_en'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('name_ar')
                    ->label(__('settings.name_ar'))
                    ->searchable()
                    ->toggleable(),
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
                            action: 'delete_tax_exemption',
                            entityType: 'tax_exemption_type',
                            entityId: $record->id,
                            details: ['code' => $record->code, 'name' => $record->name],
                        );
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('name', 'asc');
    }

    public static function getPages(): array
    {
        return [
            'index' => TaxExemptionTypeResource\Pages\ListTaxExemptionTypes::route('/'),
            'create' => TaxExemptionTypeResource\Pages\CreateTaxExemptionType::route('/create'),
            'edit' => TaxExemptionTypeResource\Pages\EditTaxExemptionType::route('/{record}/edit'),
        ];
    }
}
