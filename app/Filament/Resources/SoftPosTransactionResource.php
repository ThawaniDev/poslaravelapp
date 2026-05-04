<?php

namespace App\Filament\Resources;

use App\Domain\ProviderSubscription\Models\SoftPosTransaction;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;

class SoftPosTransactionResource extends Resource
{
    protected static ?string $model = SoftPosTransaction::class;

    protected static ?string $navigationIcon = 'heroicon-o-credit-card';

    protected static ?string $navigationGroup = null;

    public static function getNavigationGroup(): ?string
    {
        return __('nav.group_core');
    }

    protected static ?string $navigationLabel = null;

    protected static ?int $navigationSort = 5;

    public static function getNavigationLabel(): string
    {
        return __('softpos.transactions');
    }

    public static function getModelLabel(): string
    {
        return __('softpos.transaction');
    }

    public static function getPluralModelLabel(): string
    {
        return __('softpos.transactions');
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) static::getModel()::where('status', 'completed')
            ->whereDate('created_at', today())
            ->count() ?: null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'success';
    }

    // ── ACL ────────────────────────────────────────────────────

    public static function canAccess(): bool
    {
        $user = auth('admin')->user();
        return $user && $user->hasAnyPermission(['softpos.financials.view', 'softpos.view']);
    }

    public static function canCreate(): bool  { return false; }
    public static function canEdit($record): bool   { return false; }
    public static function canDelete($record): bool { return false; }

    protected static function cardSchemeLabel(?string $state): string
    {
        return match (strtolower((string) $state)) {
            'mada'       => __('softpos.card_scheme_mada'),
            'visa'       => __('softpos.card_scheme_visa'),
            'mastercard' => __('softpos.card_scheme_mastercard'),
            'amex'       => __('softpos.card_scheme_amex'),
            default      => __('softpos.card_scheme_unknown'),
        };
    }

    protected static function feeTypeLabel(?string $state): string
    {
        return match ($state) {
            'percentage' => __('softpos.fee_type_percentage'),
            'fixed'      => __('softpos.fee_type_fixed'),
            default      => __('softpos.not_available'),
        };
    }

    protected static function statusLabel(?string $state): string
    {
        return match ($state) {
            'completed' => __('softpos.status_completed'),
            'pending'   => __('softpos.status_pending'),
            'failed'    => __('softpos.status_failed'),
            'refunded'  => __('softpos.status_refunded'),
            default     => __('softpos.not_available'),
        };
    }

    // ── Form (view-only) ───────────────────────────────────────

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('softpos.transaction_details'))
                    ->schema([
                        Forms\Components\TextInput::make('id')
                            ->label(__('softpos.id'))
                            ->disabled(),
                        Forms\Components\TextInput::make('store.name')
                            ->label(__('softpos.store'))
                            ->disabled(),
                        Forms\Components\TextInput::make('terminal.name')
                            ->label(__('softpos.terminal'))
                            ->disabled(),
                        Forms\Components\TextInput::make('payment_method')
                            ->label(__('softpos.card_scheme'))
                            ->formatStateUsing(fn (?string $state) => static::cardSchemeLabel($state))
                            ->disabled(),
                        Forms\Components\TextInput::make('status')
                            ->label(__('softpos.status'))
                            ->formatStateUsing(fn (?string $state) => static::statusLabel($state))
                            ->disabled(),
                    ])->columns(3),

                Forms\Components\Section::make(__('softpos.amounts'))
                    ->schema([
                        Forms\Components\TextInput::make('amount')
                            ->label(__('softpos.amount') . ' (SAR)')
                            ->disabled()
                            ->numeric(),
                        Forms\Components\TextInput::make('platform_fee')
                            ->label(__('softpos.platform_fee') . ' (SAR)')
                            ->disabled()
                            ->numeric(),
                        Forms\Components\TextInput::make('gateway_fee')
                            ->label(__('softpos.gateway_fee') . ' (SAR)')
                            ->disabled()
                            ->numeric(),
                        Forms\Components\TextInput::make('margin')
                            ->label(__('softpos.margin') . ' (SAR)')
                            ->disabled()
                            ->numeric(),
                        Forms\Components\TextInput::make('fee_type')
                            ->label(__('softpos.fee_type'))
                            ->formatStateUsing(fn (?string $state) => static::feeTypeLabel($state))
                            ->disabled(),
                    ])->columns(5),

                Forms\Components\Section::make(__('softpos.reference'))
                    ->schema([
                        Forms\Components\TextInput::make('transaction_ref')
                            ->label(__('softpos.transaction_ref'))
                            ->disabled(),
                        Forms\Components\TextInput::make('order_id')
                            ->label(__('softpos.order_id'))
                            ->disabled(),
                    ])->columns(2),

                Forms\Components\Section::make(__('common.timestamps'))
                    ->schema([
                        Forms\Components\TextInput::make('created_at')
                            ->label(__('common.created_at'))
                            ->disabled()
                            ->formatStateUsing(fn ($state) => $state ? \Carbon\Carbon::parse($state)->format('Y-m-d H:i:s') : null),
                    ])->columns(1)->collapsible(),
            ]);
    }

    // ── Table ──────────────────────────────────────────────────

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['store:id,name', 'terminal:id,name,code']))
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('softpos.date'))
                    ->dateTime('d M Y, H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('store.name')
                    ->label(__('softpos.store'))
                    ->searchable()
                    ->limit(20),

                Tables\Columns\TextColumn::make('terminal.name')
                    ->label(__('softpos.terminal'))
                    ->searchable()
                    ->limit(15)
                    ->toggleable(),

                Tables\Columns\TextColumn::make('payment_method')
                    ->label(__('softpos.card_scheme'))
                    ->badge()
                    ->color(fn (?string $state) => match (strtolower((string) $state)) {
                        'mada'       => 'success',
                        'visa'       => 'info',
                        'mastercard' => 'warning',
                        'amex'       => 'danger',
                        default      => 'gray',
                    })
                    ->formatStateUsing(fn (?string $state) => static::cardSchemeLabel($state)),

                Tables\Columns\TextColumn::make('fee_type')
                    ->label(__('softpos.fee_type'))
                    ->badge()
                    ->color(fn (?string $state) => match ($state) {
                        'percentage' => 'info',
                        'fixed'      => 'warning',
                        default      => 'gray',
                    })
                    ->formatStateUsing(fn (?string $state) => static::feeTypeLabel($state)),

                Tables\Columns\TextColumn::make('amount')
                    ->label(__('softpos.amount'))
                    ->money('SAR')
                    ->sortable()
                    ->summarize([
                        Tables\Columns\Summarizers\Sum::make()
                            ->money('SAR')
                            ->label(__('softpos.total_volume')),
                    ]),

                Tables\Columns\TextColumn::make('platform_fee')
                    ->label(__('softpos.platform_fee'))
                    ->money('SAR')
                    ->sortable()
                    ->color('success')
                    ->summarize([
                        Tables\Columns\Summarizers\Sum::make()
                            ->money('SAR')
                            ->label(__('softpos.total_platform_fee')),
                    ]),

                Tables\Columns\TextColumn::make('gateway_fee')
                    ->label(__('softpos.gateway_fee'))
                    ->money('SAR')
                    ->color('danger')
                    ->toggleable()
                    ->summarize([
                        Tables\Columns\Summarizers\Sum::make()
                            ->money('SAR')
                            ->label(__('softpos.total_gateway_fee')),
                    ]),

                Tables\Columns\TextColumn::make('margin')
                    ->label(__('softpos.margin'))
                    ->money('SAR')
                    ->sortable()
                    ->color(fn (?string $state) => ($state ?? 0) > 0 ? 'success' : 'gray')
                    ->summarize([
                        Tables\Columns\Summarizers\Sum::make()
                            ->money('SAR')
                            ->label(__('softpos.total_margin')),
                    ]),

                Tables\Columns\TextColumn::make('status')
                    ->label(__('softpos.status'))
                    ->badge()
                    ->color(fn (?string $state) => match ($state) {
                        'completed' => 'success',
                        'pending'   => 'warning',
                        'failed'    => 'danger',
                        'refunded'  => 'gray',
                        default     => 'gray',
                    })
                    ->formatStateUsing(fn (?string $state) => static::statusLabel($state)),

                Tables\Columns\TextColumn::make('transaction_ref')
                    ->label(__('softpos.transaction_ref'))
                    ->limit(16)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Filter::make('date_range')
                    ->label(__('softpos.date_range'))
                    ->form([
                        Forms\Components\DatePicker::make('from')
                            ->label(__('common.from'))
                            ->default(now()->startOfMonth()),
                        Forms\Components\DatePicker::make('until')
                            ->label(__('common.until'))
                            ->default(now()),
                    ])
                    ->query(function (Builder $query, array $data) {
                        return $query
                            ->when($data['from'],  fn ($q) => $q->whereDate('created_at', '>=', $data['from']))
                            ->when($data['until'], fn ($q) => $q->whereDate('created_at', '<=', $data['until']));
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['from'])  $indicators[] = __('common.from') . ': ' . $data['from'];
                        if ($data['until']) $indicators[] = __('common.until') . ': ' . $data['until'];
                        return $indicators;
                    }),

                SelectFilter::make('payment_method')
                    ->label(__('softpos.card_scheme'))
                    ->options([
                        'mada'       => __('softpos.card_scheme_mada'),
                        'visa'       => __('softpos.card_scheme_visa'),
                        'mastercard' => __('softpos.card_scheme_mastercard'),
                        'amex'       => __('softpos.card_scheme_amex'),
                    ])
                    ->query(fn (Builder $query, array $data) => $query->when(
                        $data['value'],
                        fn ($q, $v) => $q->whereRaw('LOWER(payment_method) = ?', [strtolower($v)])
                    )),

                SelectFilter::make('fee_type')
                    ->label(__('softpos.fee_type'))
                    ->options([
                        'percentage' => __('softpos.fee_type_percentage'),
                        'fixed'      => __('softpos.fee_type_fixed'),
                    ]),

                SelectFilter::make('status')
                    ->label(__('softpos.status'))
                    ->options([
                        'completed' => __('softpos.status_completed'),
                        'pending'   => __('softpos.status_pending'),
                        'failed'    => __('softpos.status_failed'),
                        'refunded'  => __('softpos.status_refunded'),
                    ]),
            ])
            ->filtersLayout(Tables\Enums\FiltersLayout::AboveContent)
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([])
            ->striped()
            ->poll('30s');
    }

    // ── Pages ──────────────────────────────────────────────────

    public static function getPages(): array
    {
        return [
            'index' => \App\Filament\Resources\SoftPosTransactionResource\Pages\ListSoftPosTransactions::route('/'),
            'view'  => \App\Filament\Resources\SoftPosTransactionResource\Pages\ViewSoftPosTransaction::route('/{record}'),
        ];
    }
}
