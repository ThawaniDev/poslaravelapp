<?php

namespace App\Filament\Resources;

use App\Domain\Promotion\Enums\DiscountType;
use App\Domain\Subscription\Models\SubscriptionDiscount;
use App\Domain\Subscription\Models\SubscriptionPlan;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SubscriptionDiscountResource extends Resource
{
    protected static ?string $model = SubscriptionDiscount::class;

    protected static ?string $navigationIcon = 'heroicon-o-tag';

    protected static ?string $navigationGroup = 'Subscription & Billing';

    protected static ?string $navigationLabel = 'Discounts';

    protected static ?int $navigationSort = 4;

    public static function canAccess(): bool
    {
        $user = auth('admin')->user();

        return $user && $user->hasAnyPermission(['billing.edit']);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Discount Details')
                ->schema([
                    Forms\Components\TextInput::make('code')
                        ->required()
                        ->maxLength(50)
                        ->unique(ignoreRecord: true)
                        ->prefixIcon('heroicon-m-ticket')
                        ->helperText('Unique discount code stores will enter'),

                    Forms\Components\Select::make('type')
                        ->options(DiscountType::class)
                        ->required()
                        ->native(false)
                        ->live(),

                    Forms\Components\TextInput::make('value')
                        ->required()
                        ->numeric()
                        ->minValue(0)
                        ->suffix(fn (Forms\Get $get) => $get('type') === 'percentage' ? '%' : 'SAR')
                        ->rules([
                            fn (Forms\Get $get) => $get('type') === 'percentage' ? 'max:100' : 'max:999999',
                        ]),

                    Forms\Components\TextInput::make('max_uses')
                        ->numeric()
                        ->minValue(1)
                        ->helperText('Leave empty for unlimited uses'),
                ])
                ->columns(2),

            Forms\Components\Section::make('Validity & Scope')
                ->schema([
                    Forms\Components\DateTimePicker::make('valid_from')
                        ->label('Valid From')
                        ->native(false),

                    Forms\Components\DateTimePicker::make('valid_to')
                        ->label('Valid Until')
                        ->native(false)
                        ->after('valid_from'),

                    Forms\Components\Select::make('applicable_plan_ids')
                        ->label('Applicable Plans')
                        ->multiple()
                        ->options(fn () => SubscriptionPlan::query()->pluck('name', 'id'))
                        ->helperText('Leave empty to apply to all plans')
                        ->columnSpanFull(),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->searchable()
                    ->copyable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->color(fn (DiscountType $state) => match ($state) {
                        DiscountType::Percentage => 'info',
                        DiscountType::Fixed => 'success',
                    }),

                Tables\Columns\TextColumn::make('value')
                    ->formatStateUsing(fn ($record) => $record->type === DiscountType::Percentage
                        ? "{$record->value}%"
                        : number_format($record->value, 2) . ' SAR'
                    )
                    ->sortable(),

                Tables\Columns\TextColumn::make('times_used')
                    ->label('Usage')
                    ->formatStateUsing(fn ($record) => $record->max_uses
                        ? "{$record->times_used} / {$record->max_uses}"
                        : "{$record->times_used} / ∞"
                    )
                    ->color(fn ($record) => $record->max_uses && $record->times_used >= $record->max_uses ? 'danger' : 'gray'),

                Tables\Columns\TextColumn::make('valid_from')
                    ->dateTime('M j, Y')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('valid_to')
                    ->dateTime('M j, Y')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options(DiscountType::class),

                Tables\Filters\Filter::make('active')
                    ->label('Currently Active')
                    ->query(fn ($query) => $query
                        ->where(fn ($q) => $q->whereNull('valid_to')->orWhere('valid_to', '>', now()))
                        ->where(fn ($q) => $q->whereNull('max_uses')->orWhereColumn('times_used', '<', 'max_uses'))
                    ),

                Tables\Filters\Filter::make('expired')
                    ->label('Expired')
                    ->query(fn ($query) => $query->where('valid_to', '<', now())),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('duplicate')
                    ->icon('heroicon-m-document-duplicate')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->action(function (SubscriptionDiscount $record) {
                        $new = $record->replicate(['times_used']);
                        $new->code = $record->code . '_copy';
                        $new->times_used = 0;
                        $new->save();
                    }),
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
            Infolists\Components\Section::make('Discount Details')
                ->schema([
                    Infolists\Components\TextEntry::make('code')->copyable()->weight('bold'),
                    Infolists\Components\TextEntry::make('type')->badge(),
                    Infolists\Components\TextEntry::make('value')
                        ->formatStateUsing(fn ($record) => $record->type === DiscountType::Percentage
                            ? "{$record->value}%"
                            : number_format($record->value, 2) . ' SAR'
                        ),
                    Infolists\Components\TextEntry::make('times_used')
                        ->label('Usage')
                        ->formatStateUsing(fn ($record) => $record->max_uses
                            ? "{$record->times_used} / {$record->max_uses}"
                            : "{$record->times_used} / ∞"
                        ),
                ])
                ->columns(4),

            Infolists\Components\Section::make('Validity')
                ->schema([
                    Infolists\Components\TextEntry::make('valid_from')->dateTime('M j, Y H:i')->placeholder('No start date'),
                    Infolists\Components\TextEntry::make('valid_to')->dateTime('M j, Y H:i')->placeholder('No expiry'),
                    Infolists\Components\TextEntry::make('applicable_plan_ids')
                        ->label('Applicable Plans')
                        ->formatStateUsing(function ($record) {
                            if (empty($record->applicable_plan_ids)) {
                                return 'All plans';
                            }

                            return SubscriptionPlan::whereIn('id', $record->applicable_plan_ids)
                                ->pluck('name')
                                ->join(', ');
                        })
                        ->columnSpanFull(),
                ])
                ->columns(2),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => SubscriptionDiscountResource\Pages\ListSubscriptionDiscounts::route('/'),
            'create' => SubscriptionDiscountResource\Pages\CreateSubscriptionDiscount::route('/create'),
            'view' => SubscriptionDiscountResource\Pages\ViewSubscriptionDiscount::route('/{record}'),
            'edit' => SubscriptionDiscountResource\Pages\EditSubscriptionDiscount::route('/{record}/edit'),
        ];
    }
}
