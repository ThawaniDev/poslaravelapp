<?php

namespace App\Filament\Resources;

use App\Domain\ContentOnboarding\Enums\PurchaseType;
use App\Domain\ContentOnboarding\Models\TemplatePurchase;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class TemplatePurchaseResource extends Resource
{
    protected static ?string $model = TemplatePurchase::class;

    protected static ?string $navigationIcon = 'heroicon-o-credit-card';

    protected static ?string $navigationGroup = 'UI Management';

    protected static ?string $navigationLabel = null;

    protected static ?int $navigationSort = 11;

    public static function getNavigationLabel(): string
    {
        return __('ui.nav_template_purchases');
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
            Forms\Components\Section::make(__('ui.purchase_details'))
                ->schema([
                    Forms\Components\TextInput::make('store_id')
                        ->label(__('ui.store_id'))
                        ->disabled(),
                    Forms\Components\TextInput::make('marketplace_listing_id')
                        ->label(__('ui.listing_id'))
                        ->disabled(),
                    Forms\Components\TextInput::make('purchase_type')
                        ->label(__('ui.purchase_type'))
                        ->disabled(),
                    Forms\Components\TextInput::make('amount_paid')
                        ->label(__('ui.amount_paid'))
                        ->disabled(),
                    Forms\Components\TextInput::make('currency')
                        ->label(__('ui.currency'))
                        ->disabled(),
                    Forms\Components\TextInput::make('payment_reference')
                        ->label(__('ui.payment_reference'))
                        ->disabled(),
                    Forms\Components\TextInput::make('payment_gateway')
                        ->label(__('ui.payment_gateway'))
                        ->disabled(),
                    Forms\Components\Toggle::make('is_active')
                        ->label(__('ui.is_active')),
                    Forms\Components\Toggle::make('auto_renew')
                        ->label(__('ui.auto_renew')),
                ])
                ->columns(2),
        ]);
    }

    // ─── Table ───────────────────────────────────────────────

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('listing.title')
                    ->label(__('ui.listing'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('store_id')
                    ->label(__('ui.store_id'))
                    ->searchable()
                    ->limit(8),
                Tables\Columns\TextColumn::make('purchase_type')
                    ->label(__('ui.purchase_type'))
                    ->badge()
                    ->color(fn (TemplatePurchase $r) => match ($r->purchase_type) {
                        PurchaseType::OneTime => 'info',
                        PurchaseType::Subscription => 'warning',
                    })
                    ->formatStateUsing(fn (TemplatePurchase $r) => __('ui.purchase_type_' . $r->purchase_type->value)),
                Tables\Columns\TextColumn::make('amount_paid')
                    ->label(__('ui.amount_paid'))
                    ->formatStateUsing(fn (TemplatePurchase $r) => number_format((float) $r->amount_paid, 2) . ' ' . $r->currency)
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label(__('ui.is_active'))
                    ->boolean()
                    ->sortable(),
                Tables\Columns\TextColumn::make('subscription_expires_at')
                    ->label(__('ui.expires_at'))
                    ->dateTime()
                    ->placeholder('—')
                    ->sortable(),
                Tables\Columns\IconColumn::make('auto_renew')
                    ->label(__('ui.auto_renew'))
                    ->boolean(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('ui.purchased_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('purchase_type')
                    ->label(__('ui.purchase_type'))
                    ->options(collect(PurchaseType::cases())->mapWithKeys(
                        fn ($c) => [$c->value => __('ui.purchase_type_' . $c->value)],
                    )),
                Tables\Filters\TernaryFilter::make('is_active')->label(__('ui.is_active')),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    // ─── Pages ───────────────────────────────────────────────

    public static function getPages(): array
    {
        return [
            'index' => TemplatePurchaseResource\Pages\ListTemplatePurchases::route('/'),
            'edit' => TemplatePurchaseResource\Pages\EditTemplatePurchase::route('/{record}/edit'),
        ];
    }
}
