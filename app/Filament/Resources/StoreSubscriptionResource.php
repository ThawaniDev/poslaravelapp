<?php

namespace App\Filament\Resources;

use App\Domain\ProviderSubscription\Models\StoreSubscription;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class StoreSubscriptionResource extends Resource
{
    protected static ?string $model = StoreSubscription::class;

    protected static ?string $navigationIcon = 'heroicon-o-credit-card';

    protected static ?string $navigationGroup = 'Business';

    protected static ?string $navigationLabel = 'Subscriptions';

    protected static ?int $navigationSort = 2;

    public static function canAccess(): bool
    {
        $user = auth('admin')->user();

        return $user && $user->hasAnyPermission(['billing.view', 'billing.edit']);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Subscriptions')->schema([
                Forms\Components\Select::make('store_id')->relationship('store', 'name')->searchable()->preload(),
                Forms\Components\Select::make('subscription_plan_id')->relationship('subscriptionPlan', 'name')->searchable()->preload(),
                Forms\Components\Select::make('status')->options(array ('active' => 'Active','trial' => 'Trial','past_due' => 'Past Due','cancelled' => 'Cancelled','suspended' => 'Suspended',)),
                Forms\Components\Select::make('billing_cycle')->options(array ('monthly' => 'Monthly','annual' => 'Annual',)),
                Forms\Components\DateTimePicker::make('current_period_start'),
                Forms\Components\DateTimePicker::make('current_period_end'),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('store.name')->label('Store')->searchable(),
                Tables\Columns\TextColumn::make('subscriptionPlan.name')->label('Plan'),
                Tables\Columns\TextColumn::make('status')->badge(),
                Tables\Columns\TextColumn::make('billing_cycle'),
                Tables\Columns\TextColumn::make('current_period_start')->dateTime()->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('current_period_end')->dateTime()->toggleable(isToggledHiddenByDefault: true),
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
            'index' => StoreSubscriptionResource\Pages\ListStoreSubscriptions::route('/'),
            'edit' => StoreSubscriptionResource\Pages\EditStoreSubscription::route('/{record}/edit'),
        ];
    }
}
