<?php

namespace App\Filament\Resources;

use App\Domain\ProviderSubscription\Models\StoreSubscription;
use App\Domain\ProviderSubscription\Services\BillingService;
use App\Domain\Subscription\Enums\SubscriptionStatus;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class StoreSubscriptionResource extends Resource
{
    protected static ?string $model = StoreSubscription::class;

    protected static ?string $navigationIcon = 'heroicon-o-credit-card';

    protected static ?string $navigationGroup = 'Subscription & Billing';

    protected static ?string $navigationLabel = 'Subscriptions';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'id';

    public static function canAccess(): bool
    {
        $user = auth('admin')->user();

        return $user && $user->hasAnyPermission(['billing.view', 'billing.edit']);
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) StoreSubscription::whereIn('status', ['active', 'trial'])->count();
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'success';
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Subscription Details')
                ->description('Manage the store subscription assignment and status')
                ->schema([
                    Forms\Components\Grid::make(2)->schema([
                        Forms\Components\Select::make('organization_id')
                            ->relationship('organization', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->disabled(fn (?StoreSubscription $record) => $record !== null),
                        Forms\Components\Select::make('subscription_plan_id')
                            ->relationship('subscriptionPlan', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),
                    ]),
                    Forms\Components\Grid::make(3)->schema([
                        Forms\Components\Select::make('status')
                            ->options([
                                'active' => 'Active',
                                'trial' => 'Trial',
                                'grace' => 'Grace Period',
                                'cancelled' => 'Cancelled',
                                'expired' => 'Expired',
                            ])
                            ->required(),
                        Forms\Components\Select::make('billing_cycle')
                            ->options([
                                'monthly' => 'Monthly',
                                'yearly' => 'Yearly',
                            ])
                            ->required(),
                        Forms\Components\TextInput::make('payment_method')
                            ->placeholder('credit_card'),
                    ]),
                    Forms\Components\Grid::make(2)->schema([
                        Forms\Components\DateTimePicker::make('current_period_start')
                            ->required(),
                        Forms\Components\DateTimePicker::make('current_period_end')
                            ->required(),
                    ]),
                    Forms\Components\Grid::make(2)->schema([
                        Forms\Components\DateTimePicker::make('trial_ends_at'),
                        Forms\Components\DateTimePicker::make('cancelled_at'),
                    ]),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('organization.name')
                    ->label('Organization')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('subscriptionPlan.name')
                    ->label('Plan')
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn ($state): string => match ($state?->value ?? $state) {
                        'active' => 'success',
                        'trial' => 'info',
                        'grace' => 'warning',
                        'cancelled' => 'danger',
                        'expired' => 'gray',
                        default => 'gray',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('billing_cycle')
                    ->badge()
                    ->color('gray'),
                Tables\Columns\TextColumn::make('current_period_end')
                    ->label('Renews / Expires')
                    ->date()
                    ->sortable()
                    ->color(fn (StoreSubscription $record) => $record->current_period_end && $record->current_period_end->isPast() ? 'danger' : null),
                Tables\Columns\TextColumn::make('invoices_count')
                    ->counts('invoices')
                    ->label('Invoices')
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Since')
                    ->date()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'active' => 'Active',
                        'trial' => 'Trial',
                        'grace' => 'Grace Period',
                        'cancelled' => 'Cancelled',
                        'expired' => 'Expired',
                    ])
                    ->multiple(),
                Tables\Filters\SelectFilter::make('billing_cycle')
                    ->options([
                        'monthly' => 'Monthly',
                        'yearly' => 'Yearly',
                    ]),
                Tables\Filters\SelectFilter::make('subscription_plan_id')
                    ->relationship('subscriptionPlan', 'name')
                    ->label('Plan')
                    ->preload(),
                Tables\Filters\Filter::make('expiring_soon')
                    ->label('Expiring in 7 days')
                    ->query(fn (Builder $query) => $query
                        ->where('current_period_end', '<=', now()->addDays(7))
                        ->where('current_period_end', '>=', now())
                    ),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\EditAction::make(),
                    Tables\Actions\Action::make('change_plan')
                        ->label('Change Plan')
                        ->icon('heroicon-o-arrow-path')
                        ->color('primary')
                        ->visible(fn () => auth('admin')->user()?->hasPermission('billing.edit'))
                        ->form([
                            Forms\Components\Select::make('new_plan_id')
                                ->label('New Plan')
                                ->relationship('subscriptionPlan', 'name', fn (Builder $query, StoreSubscription $record) => $query->where('is_active', true)->where('id', '!=', $record->subscription_plan_id))
                                ->searchable()
                                ->preload()
                                ->required(),
                            Forms\Components\Select::make('billing_cycle')
                                ->options(['monthly' => 'Monthly', 'yearly' => 'Yearly'])
                                ->default('monthly')
                                ->required(),
                        ])
                        ->action(function (StoreSubscription $record, array $data) {
                            $billing = app(BillingService::class);
                            $billing->changePlan($record->organization_id, $data['new_plan_id'], $data['billing_cycle']);
                            Notification::make()->title('Plan changed successfully')->success()->send();
                        }),
                    Tables\Actions\Action::make('apply_credit')
                        ->label('Apply Credit')
                        ->icon('heroicon-o-banknotes')
                        ->color('success')
                        ->visible(fn () => auth('admin')->user()?->hasPermission('billing.edit'))
                        ->form([
                            Forms\Components\TextInput::make('amount')
                                ->numeric()
                                ->required()
                                ->prefix('SAR')
                                ->minValue(0.01),
                            Forms\Components\Textarea::make('reason')
                                ->required()
                                ->maxLength(500),
                        ])
                        ->action(function (StoreSubscription $record, array $data) {
                            $billing = app(BillingService::class);
                            $billing->applyCredit(
                                $record->id,
                                (float) $data['amount'],
                                $data['reason'],
                                auth('admin')->id()
                            );
                            Notification::make()->title('Credit of SAR ' . number_format($data['amount'], 2) . ' applied')->success()->send();
                        }),
                    Tables\Actions\Action::make('cancel')
                        ->label('Cancel Subscription')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->visible(fn (StoreSubscription $record) => in_array($record->status?->value ?? $record->status, ['active', 'trial']) && auth('admin')->user()?->hasPermission('billing.edit'))
                        ->requiresConfirmation()
                        ->modalDescription('The subscription will enter grace period before expiring.')
                        ->form([
                            Forms\Components\Textarea::make('reason')
                                ->label('Cancellation Reason')
                                ->maxLength(500),
                        ])
                        ->action(function (StoreSubscription $record, array $data) {
                            $billing = app(BillingService::class);
                            $billing->cancelSubscription($record->organization_id, $data['reason'] ?? null);
                            Notification::make()->title('Subscription cancelled')->warning()->send();
                        }),
                    Tables\Actions\Action::make('resume')
                        ->label('Resume Subscription')
                        ->icon('heroicon-o-play')
                        ->color('success')
                        ->visible(fn (StoreSubscription $record) => in_array($record->status?->value ?? $record->status, ['cancelled', 'grace']) && auth('admin')->user()?->hasPermission('billing.edit'))
                        ->requiresConfirmation()
                        ->action(function (StoreSubscription $record) {
                            $billing = app(BillingService::class);
                            $billing->resumeSubscription($record->organization_id);
                            Notification::make()->title('Subscription resumed')->success()->send();
                        }),
                ]),
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
            Infolists\Components\Section::make('Subscription Overview')
                ->schema([
                    Infolists\Components\Grid::make(3)->schema([
                        Infolists\Components\TextEntry::make('organization.name')->label('Organization'),
                        Infolists\Components\TextEntry::make('subscriptionPlan.name')->label('Plan'),
                        Infolists\Components\TextEntry::make('status')
                            ->badge()
                            ->color(fn ($state): string => match ($state?->value ?? $state) {
                                'active' => 'success',
                                'trial' => 'info',
                                'grace' => 'warning',
                                'cancelled' => 'danger',
                                'expired' => 'gray',
                                default => 'gray',
                            }),
                    ]),
                    Infolists\Components\Grid::make(4)->schema([
                        Infolists\Components\TextEntry::make('billing_cycle')->badge(),
                        Infolists\Components\TextEntry::make('payment_method'),
                        Infolists\Components\TextEntry::make('current_period_start')->date(),
                        Infolists\Components\TextEntry::make('current_period_end')->date(),
                    ]),
                    Infolists\Components\Grid::make(2)->schema([
                        Infolists\Components\TextEntry::make('trial_ends_at')->date()->placeholder('—'),
                        Infolists\Components\TextEntry::make('cancelled_at')->date()->placeholder('—'),
                    ]),
                ]),
            Infolists\Components\Section::make('Credits Applied')
                ->schema([
                    Infolists\Components\RepeatableEntry::make('subscriptionCredits')
                        ->label('')
                        ->schema([
                            Infolists\Components\TextEntry::make('amount')->money('SAR'),
                            Infolists\Components\TextEntry::make('reason'),
                            Infolists\Components\TextEntry::make('appliedBy.name')->label('Applied By'),
                            Infolists\Components\TextEntry::make('applied_at')->dateTime(),
                        ])
                        ->columns(4),
                ])
                ->collapsible(),
            Infolists\Components\Section::make('Cancellation History')
                ->schema([
                    Infolists\Components\RepeatableEntry::make('cancellationReasons')
                        ->label('')
                        ->schema([
                            Infolists\Components\TextEntry::make('reason_category')->badge(),
                            Infolists\Components\TextEntry::make('reason_text'),
                            Infolists\Components\TextEntry::make('created_at')->dateTime(),
                        ])
                        ->columns(3),
                ])
                ->collapsible()
                ->collapsed(),
        ]);
    }

    public static function getRelations(): array
    {
        return [
            StoreSubscriptionResource\RelationManagers\InvoicesRelationManager::class,
            StoreSubscriptionResource\RelationManagers\SubscriptionCreditsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => StoreSubscriptionResource\Pages\ListStoreSubscriptions::route('/'),
            'view' => StoreSubscriptionResource\Pages\ViewStoreSubscription::route('/{record}'),
            'edit' => StoreSubscriptionResource\Pages\EditStoreSubscription::route('/{record}/edit'),
        ];
    }
}
