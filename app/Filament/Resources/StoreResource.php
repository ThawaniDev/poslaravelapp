<?php

namespace App\Filament\Resources;

use App\Domain\Core\Models\Store;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class StoreResource extends Resource
{
    protected static ?string $model = Store::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-storefront';

    protected static ?string $navigationGroup = 'Core';

    protected static ?string $navigationLabel = 'Stores';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    public static function canAccess(): bool
    {
        $user = auth('admin')->user();

        return $user && $user->hasAnyPermission(['stores.view', 'stores.edit', 'stores.create']);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Store Information')->schema([
                Forms\Components\TextInput::make('name')->required()->maxLength(255),
                Forms\Components\TextInput::make('name_ar')->label('Name (Arabic)')->maxLength(255),
                Forms\Components\TextInput::make('slug')->required()->maxLength(255),
                Forms\Components\TextInput::make('branch_code')->maxLength(50),
                Forms\Components\Select::make('organization_id')
                    ->relationship('organization', 'name')
                    ->searchable()
                    ->preload(),
                Forms\Components\Select::make('business_type')->options([
                    'retail' => 'Retail', 'restaurant' => 'Restaurant', 'cafe' => 'Cafe',
                    'bakery' => 'Bakery', 'pharmacy' => 'Pharmacy', 'florist' => 'Florist',
                    'jewelry' => 'Jewelry', 'electronics' => 'Electronics', 'grocery' => 'Grocery',
                    'fashion' => 'Fashion', 'other' => 'Other',
                ]),
            ])->columns(2),
            Forms\Components\Section::make('Contact & Location')->schema([
                Forms\Components\TextInput::make('phone')->tel(),
                Forms\Components\TextInput::make('email')->email(),
                Forms\Components\TextInput::make('address')->maxLength(500),
                Forms\Components\TextInput::make('city')->maxLength(100),
                Forms\Components\TextInput::make('latitude')->numeric(),
                Forms\Components\TextInput::make('longitude')->numeric(),
                Forms\Components\Select::make('timezone')->options(
                    collect(timezone_identifiers_list())->mapWithKeys(fn ($tz) => [$tz => $tz])->toArray()
                )->searchable()->default('Asia/Muscat'),
                Forms\Components\Select::make('currency')->options(['OMR' => 'OMR', 'SAR' => 'SAR', 'AED' => 'AED', 'USD' => 'USD'])->default('OMR'),
                Forms\Components\Select::make('locale')->options(['ar' => 'Arabic', 'en' => 'English'])->default('ar'),
            ])->columns(2),
            Forms\Components\Section::make('Status')->schema([
                Forms\Components\Toggle::make('is_active')->default(true),
                Forms\Components\Toggle::make('is_main_branch')->default(false),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('organization.name')->label('Organization')->sortable(),
                Tables\Columns\TextColumn::make('city')->sortable(),
                Tables\Columns\TextColumn::make('business_type')->badge(),
                Tables\Columns\IconColumn::make('is_active')->boolean()->label('Active'),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')->label('Active'),
                Tables\Filters\SelectFilter::make('business_type')->options([
                    'retail' => 'Retail', 'restaurant' => 'Restaurant', 'cafe' => 'Cafe',
                    'bakery' => 'Bakery', 'pharmacy' => 'Pharmacy', 'other' => 'Other',
                ]),
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

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => StoreResource\Pages\ListStores::route('/'),
            'create' => StoreResource\Pages\CreateStore::route('/create'),
            'edit' => StoreResource\Pages\EditStore::route('/{record}/edit'),
        ];
    }
}
