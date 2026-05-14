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

    protected static ?string $navigationGroup = null;

    public static function getNavigationGroup(): ?string
    {
        return __('nav.group_subscription_billing');
    }

    protected static ?string $navigationLabel = null;

    public static function getNavigationLabel(): string
    {
        return __('nav.subscriptions');
    }

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'id';

    public static function canAccess(): bool
    {
        $user = auth('admin')->user();

        return $user && $user->hasAnyPermission(['billing.view', 'billing.edit']);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make(__('Subscription Details'))
                ->description(__('Manage the store subscription assignment and status'))
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
                                'active' => __('Active'),
                                'trial' => __('Trial'),
                                'grace' => __('Grace Period'),
                                'cancelled' => __('Cancelled'),
                                'expired' => __('Expired'),
                            ])
                            ->required(),
                        Forms\Components\Select::make('billing_cycle')
                            ->options([
                                'monthly' => __('Monthly'),
                                'yearly' => __('Yearly'),
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
                    ->label(__('Organization'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('subscriptionPlan.name')
                    ->label(__('Plan'))
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
                    ->label(__('Renews / Expires'))
                    ->date()
                    ->sortable()
                    ->color(fn (StoreSubscription $record) => $record->current_period_end && $record->current_period_end->isPast() ? 'danger' : null),
                Tables\Columns\TextColumn::make('invoices_count')
                    ->counts('invoices')
                    ->label(__('Invoices'))
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('Since'))
                    ->date()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'active' => __('Active'),
                        'trial' => __('Trial'),
                        'grace' => __('Grace Period'),
                        'cancelled' => __('Cancelled'),
                        'expired' => __('Expired'),
                    ])
                    ->multiple(),
                Tables\Filters\SelectFilter::make('billing_cycle')
                    ->options([
                        'monthly' => __('Monthly'),
                        'yearly' => __('Yearly'),
                    ]),
                Tables\Filters\SelectFilter::make('subscription_plan_id')
                    ->relationship('subscriptionPlan', 'name')
                    ->label(__('Plan'))
                    ->preload(),
                Tables\Filters\Filter::make('expiring_soon')
                    ->label(__('Expiring in 7 days'))
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
                        ->label(__('Change Plan'))
                        ->icon('heroicon-o-arrow-path')
                        ->color('primary')
                        ->visible(fn () => auth('admin')->user()?->hasPermission('billing.edit'))
                        ->requiresConfirmation()
                        ->modalHeading(fn (StoreSubscription $record) => __('Change Plan — :org', ['org' => $record->organization?->name ?? $record->organization_id]))
                        ->modalDescription(fn (StoreSubscription $record) => __('Current plan: :plan (:cycle). Select the new plan below. A prorated invoice will be generated immediately.', [
                            'plan'  => $record->subscriptionPlan?->name ?? '—',
                            'cycle' => $record->billing_cycle?->value ?? '—',
                        ]))
                        ->form([
                            Forms\Components\Select::make('new_plan_id')
                                ->label(__('New Plan'))
                                ->relationship('subscriptionPlan', 'name', fn (Builder $query, StoreSubscription $record) => $query->where('is_active', true)->where('id', '!=', $record->subscription_plan_id))
                                ->searchable()
                                ->preload()
                                ->required(),
                            Forms\Components\Select::make('billing_cycle')
                                ->options(['monthly' => __('Monthly'), 'yearly' => __('Yearly')])
                                ->default('monthly')
                                ->required(),
                        ])
                        ->action(function (StoreSubscription $record, array $data) {
                            try {
                                app(BillingService::class)->changePlan(
                                    $record->organization_id,
                                    $data['new_plan_id'],
                                    \App\Domain\Subscription\Enums\BillingCycle::from($data['billing_cycle']),
                                );
                                Notification::make()->title(__('Plan changed successfully'))->success()->send();
                            } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
                                Notification::make()->title(__('No active subscription found for this organization.'))->danger()->send();
                            } catch (\RuntimeException $e) {
                                Notification::make()->title($e->getMessage())->danger()->send();
                            }
                        }),
                    Tables\Actions\Action::make('apply_credit')
                        ->label(__('Apply Credit'))
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
                            Notification::make()->title(__('Credit of SAR :amount applied', ['amount' => number_format($data['amount'], 2)]))->success()->send();
                        }),
                    Tables\Actions\Action::make('cancel')
                        ->label(__('Cancel Subscription'))
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->visible(fn (StoreSubscription $record) => in_array($record->status?->value ?? $record->status, ['active', 'trial']) && auth('admin')->user()?->hasPermission('billing.edit'))
                        ->requiresConfirmation()
                        ->modalDescription(__('The subscription will enter grace period before expiring.'))
                        ->form([
                            Forms\Components\Textarea::make('reason')
                                ->label(__('Cancellation Reason'))
                                ->maxLength(500),
                        ])
                        ->action(function (StoreSubscription $record, array $data) {
                            $billing = app(BillingService::class);
                            $billing->cancelSubscription($record->organization_id, $data['reason'] ?? null);
                            Notification::make()->title(__('Subscription cancelled'))->warning()->send();
                        }),
                    Tables\Actions\Action::make('resume')
                        ->label(__('Resume Subscription'))
                        ->icon('heroicon-o-play')
                        ->color('success')
                        ->visible(fn (StoreSubscription $record) => in_array($record->status?->value ?? $record->status, ['cancelled', 'grace']) && auth('admin')->user()?->hasPermission('billing.edit'))
                        ->requiresConfirmation()
                        ->action(function (StoreSubscription $record) {
                            $billing = app(BillingService::class);
                            $billing->resumeSubscription($record->organization_id);
                            Notification::make()->title(__('Subscription resumed'))->success()->send();
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
            Infolists\Components\Section::make(__('Subscription Overview'))
                ->schema([
                    Infolists\Components\Grid::make(3)->schema([
                        Infolists\Components\TextEntry::make('organization.name')->label(__('Organization')),
                        Infolists\Components\TextEntry::make('subscriptionPlan.name')->label(__('Plan')),
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
            Infolists\Components\Section::make(__('Credits Applied'))
                ->schema([
                    Infolists\Components\RepeatableEntry::make('subscriptionCredits')
                        ->label('')
                        ->schema([
                            Infolists\Components\TextEntry::make('amount')->money('SAR'),
                            Infolists\Components\TextEntry::make('reason'),
                            Infolists\Components\TextEntry::make('appliedBy.name')->label(__('Applied By')),
                            Infolists\Components\TextEntry::make('applied_at')->dateTime(),
                        ])
                        ->columns(4),
                ])
                ->collapsible(),
            Infolists\Components\Section::make(__('Cancellation History'))
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
