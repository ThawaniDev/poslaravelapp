<?php

namespace App\Filament\Resources;

use App\Domain\Subscription\Models\SubscriptionPlan;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Number;

class SubscriptionPlanResource extends Resource
{
    protected static ?string $model = SubscriptionPlan::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationGroup = null;

    public static function getNavigationGroup(): ?string
    {
        return __('nav.group_subscription_billing');
    }

    protected static ?string $navigationLabel = null;

    public static function getNavigationLabel(): string
    {
        return __('nav.subscription_plans');
    }

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    public static function canAccess(): bool
    {
        $user = auth('admin')->user();

        return $user && $user->hasAnyPermission(['billing.plans', 'billing.view']);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Tabs::make('Plan Builder')->tabs([
                // ─── General Info ─────────────────────────────────────
                Forms\Components\Tabs\Tab::make(__('subscription_plans.tab_general'))
                    ->icon('heroicon-o-information-circle')
                    ->schema([
                        Forms\Components\Section::make(__('subscription_plans.section_plan_details'))
                            ->description(__('subscription_plans.section_plan_details_desc'))
                            ->schema([
                                Forms\Components\Grid::make(2)->schema([
                                    Forms\Components\TextInput::make('name')
                                        ->label(__('subscription_plans.field_name_en'))
                                        ->required()
                                        ->maxLength(100)
                                        ->placeholder('e.g. Professional'),
                                    Forms\Components\TextInput::make('name_ar')
                                        ->label(__('subscription_plans.field_name_ar'))
                                        ->required()
                                        ->maxLength(100)
                                        ->placeholder('مثال: احترافي'),
                                ]),
                                Forms\Components\TextInput::make('slug')
                                    ->label(__('subscription_plans.field_slug'))
                                    ->required()
                                    ->maxLength(50)
                                    ->unique(ignoreRecord: true)
                                    ->alphaDash()
                                    ->placeholder('professional'),
                                Forms\Components\Grid::make(2)->schema([
                                    Forms\Components\Textarea::make('description')
                                        ->label(__('subscription_plans.field_description_en'))
                                        ->rows(3),
                                    Forms\Components\Textarea::make('description_ar')
                                        ->label(__('subscription_plans.field_description_ar'))
                                        ->rows(3),
                                ]),
                            ]),
                        Forms\Components\Section::make(__('subscription_plans.section_display_settings'))
                            ->schema([
                                Forms\Components\Grid::make(3)->schema([
                                    Forms\Components\Toggle::make('is_active')
                                        ->label(__('subscription_plans.field_is_active'))
                                        ->helperText(__('subscription_plans.field_is_active_helper'))
                                        ->default(true),
                                    Forms\Components\Toggle::make('is_highlighted')
                                        ->label(__('subscription_plans.field_is_highlighted'))
                                        ->helperText(__('subscription_plans.field_is_highlighted_helper')),
                                    Forms\Components\TextInput::make('sort_order')
                                        ->label(__('subscription_plans.field_sort_order'))
                                        ->numeric()
                                        ->default(0)
                                        ->helperText(__('subscription_plans.field_sort_order_helper')),
                                ]),
                            ])->collapsible(),
                    ]),

                // ─── Pricing ──────────────────────────────────────────
                Forms\Components\Tabs\Tab::make(__('subscription_plans.tab_pricing'))
                    ->icon('heroicon-o-currency-dollar')
                    ->schema([
                        Forms\Components\Section::make(__('subscription_plans.section_pricing'))
                            ->description(__('subscription_plans.section_pricing_desc'))
                            ->schema([
                                Forms\Components\Grid::make(2)->schema([
                                    Forms\Components\TextInput::make('monthly_price')
                                        ->label(__('subscription_plans.field_monthly_price'))
                                        ->required()
                                        ->numeric()
                                        ->prefix('SAR')
                                        ->minValue(0)
                                        ->step(0.01),
                                    Forms\Components\TextInput::make('annual_price')
                                        ->label(__('subscription_plans.field_annual_price'))
                                        ->required()
                                        ->numeric()
                                        ->prefix('SAR')
                                        ->minValue(0)
                                        ->step(0.01)
                                        ->helperText(__('subscription_plans.field_annual_price_helper')),
                                ]),
                            ]),
                        Forms\Components\Section::make(__('subscription_plans.section_trial'))
                            ->description(__('subscription_plans.section_trial_desc'))
                            ->schema([
                                Forms\Components\Grid::make(2)->schema([
                                    Forms\Components\TextInput::make('trial_days')
                                        ->label(__('subscription_plans.field_trial_days'))
                                        ->numeric()
                                        ->default(14)
                                        ->minValue(0)
                                        ->maxValue(90)
                                        ->helperText(__('subscription_plans.field_trial_days_helper')),
                                    Forms\Components\TextInput::make('grace_period_days')
                                        ->label(__('subscription_plans.field_grace_period_days'))
                                        ->numeric()
                                        ->default(7)
                                        ->minValue(0)
                                        ->maxValue(30)
                                        ->helperText(__('subscription_plans.field_grace_period_days_helper')),
                                ]),
                            ])->collapsible(),
                    ]),

                // ─── Feature Toggles ──────────────────────────────────
                Forms\Components\Tabs\Tab::make(__('subscription_plans.tab_features'))
                    ->icon('heroicon-o-cog-6-tooth')
                    ->schema([
                        Forms\Components\Section::make(__('subscription_plans.section_feature_toggles'))
                            ->description(__('subscription_plans.section_feature_toggles_desc'))
                            ->schema([
                                Forms\Components\Repeater::make('planFeatureToggles')
                                    ->relationship()
                                    ->label('')
                                    ->schema([
                                        Forms\Components\Select::make('feature_key')
                                            ->label(__('subscription_plans.field_feature_key'))
                                            ->options([
                                                'pos'                   => __('subscription_plans.feature_pos'),
                                                'zatca_phase2'          => __('subscription_plans.feature_zatca_phase2'),
                                                'inventory'             => __('subscription_plans.feature_inventory'),
                                                'reports_basic'         => __('subscription_plans.feature_reports_basic'),
                                                'barcode_scanning'      => __('subscription_plans.feature_barcode_scanning'),
                                                'cash_drawer'           => __('subscription_plans.feature_cash_drawer'),
                                                'customer_display'      => __('subscription_plans.feature_customer_display'),
                                                'receipt_printing'      => __('subscription_plans.feature_receipt_printing'),
                                                'offline_mode'          => __('subscription_plans.feature_offline_mode'),
                                                'mada_payments'         => __('subscription_plans.feature_mada_payments'),
                                                'reports_advanced'      => __('subscription_plans.feature_reports_advanced'),
                                                'multi_branch'          => __('subscription_plans.feature_multi_branch'),
                                                'delivery_integration'  => __('subscription_plans.feature_delivery_integration'),
                                                'customer_loyalty'      => __('subscription_plans.feature_customer_loyalty'),
                                                'api_access'            => __('subscription_plans.feature_api_access'),
                                                'white_label'           => __('subscription_plans.feature_white_label'),
                                                'priority_support'      => __('subscription_plans.feature_priority_support'),
                                                'dedicated_manager'     => __('subscription_plans.feature_dedicated_manager'),
                                                'custom_integrations'   => __('subscription_plans.feature_custom_integrations'),
                                                'sla_guarantee'         => __('subscription_plans.feature_sla_guarantee'),
                                            ])
                                            ->required()
                                            ->searchable()
                                            ->columnSpan(2),
                                        Forms\Components\Grid::make(2)->schema([
                                            Forms\Components\TextInput::make('name')
                                                ->label(__('subscription_plans.field_feature_name_en'))
                                                ->maxLength(100)
                                                ->placeholder('e.g. Point of Sale'),
                                            Forms\Components\TextInput::make('name_ar')
                                                ->label(__('subscription_plans.field_feature_name_ar'))
                                                ->maxLength(100)
                                                ->placeholder('مثال: نقطة البيع'),
                                        ])->columnSpanFull(),
                                        Forms\Components\Toggle::make('is_enabled')
                                            ->label(__('subscription_plans.field_is_enabled'))
                                            ->default(true),
                                    ])
                                    ->columns(3)
                                    ->defaultItems(0)
                                    ->addActionLabel(__('subscription_plans.action_add_feature'))
                                    ->reorderable(false)
                                    ->collapsible()
                                    ->itemLabel(function (array $state): ?string {
                                        $name    = $state['name']    ?? null;
                                        $nameAr  = $state['name_ar'] ?? null;
                                        $key     = $state['feature_key'] ?? null;
                                        if ($name && $nameAr) {
                                            return "{$name} / {$nameAr}";
                                        }
                                        return $name ?? $nameAr ?? $key ?? __('subscription_plans.new_feature_label');
                                    }),
                            ]),
                    ]),

                // ─── Limits ───────────────────────────────────────────
                Forms\Components\Tabs\Tab::make(__('subscription_plans.tab_limits'))
                    ->icon('heroicon-o-chart-bar')
                    ->schema([
                        Forms\Components\Section::make(__('subscription_plans.section_plan_limits'))
                            ->description(__('subscription_plans.section_plan_limits_desc'))
                            ->schema([
                                Forms\Components\Repeater::make('planLimits')
                                    ->relationship()
                                    ->label('')
                                    ->schema([
                                        Forms\Components\Grid::make(3)->schema([
                                            Forms\Components\Select::make('limit_key')
                                                ->label(__('subscription_plans.field_limit_key'))
                                                ->options([
                                                    'products'              => __('subscription_plans.limit_products'),
                                                    'staff_members'         => __('subscription_plans.limit_staff_members'),
                                                    'cashier_terminals'     => __('subscription_plans.limit_cashier_terminals'),
                                                    'branches'              => __('subscription_plans.limit_branches'),
                                                    'transactions_per_month'=> __('subscription_plans.limit_transactions_per_month'),
                                                    'storage_mb'            => __('subscription_plans.limit_storage_mb'),
                                                    'pdf_reports_per_month' => __('subscription_plans.limit_pdf_reports_per_month'),
                                                    'customers'             => __('subscription_plans.limit_customers'),
                                                    'categories'            => __('subscription_plans.limit_categories'),
                                                    'promotions'            => __('subscription_plans.limit_promotions'),
                                                    'custom_roles'          => __('subscription_plans.limit_custom_roles'),
                                                    'api_calls_day'         => __('subscription_plans.limit_api_calls_day'),
                                                    'reports'               => __('subscription_plans.limit_reports'),
                                                ])
                                                ->required()
                                                ->searchable(),
                                            Forms\Components\TextInput::make('limit_value')
                                                ->label(__('subscription_plans.field_limit_value'))
                                                ->numeric()
                                                ->required()
                                                ->minValue(-1)
                                                ->helperText(__('subscription_plans.field_limit_value_helper')),
                                            Forms\Components\TextInput::make('price_per_extra_unit')
                                                ->label(__('subscription_plans.field_overage_price'))
                                                ->numeric()
                                                ->prefix('SAR')
                                                ->minValue(0)
                                                ->step(0.01)
                                                ->helperText(__('subscription_plans.field_overage_price_helper')),
                                        ]),
                                    ])
                                    ->defaultItems(0)
                                    ->addActionLabel(__('subscription_plans.action_add_limit'))
                                    ->reorderable(false)
                                    ->collapsible()
                                    ->itemLabel(fn (array $state): ?string => $state['limit_key'] ?? __('subscription_plans.new_limit_label')),
                            ]),
                    ]),
            ])->columnSpanFull()->persistTabInQueryString(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label(__('subscription_plans.col_plan'))
                    ->searchable()
                    ->sortable()
                    ->description(fn (SubscriptionPlan $record): string => $record->name_ar ?? ''),
                Tables\Columns\TextColumn::make('slug')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('monthly_price')
                    ->label(__('subscription_plans.col_monthly'))
                    ->sortable()
                    ->money('SAR'),
                Tables\Columns\TextColumn::make('annual_price')
                    ->label(__('subscription_plans.col_annual'))
                    ->money('SAR'),
                Tables\Columns\TextColumn::make('trial_days')
                    ->label(__('subscription_plans.col_trial'))
                    ->suffix(' days')
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('storeSubscriptions_count')
                    ->counts('storeSubscriptions')
                    ->label(__('subscription_plans.col_subscribers'))
                    ->alignCenter()
                    ->sortable(),
                Tables\Columns\TextColumn::make('planFeatureToggles_count')
                    ->counts('planFeatureToggles')
                    ->label(__('subscription_plans.col_features'))
                    ->alignCenter()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\IconColumn::make('is_highlighted')
                    ->label('★')
                    ->boolean()
                    ->alignCenter()
                    ->toggleable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label(__('subscription_plans.col_active'))
                    ->boolean()
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('sort_order')
                    ->label(__('subscription_plans.col_order'))
                    ->sortable()
                    ->alignCenter()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label(__('subscription_plans.filter_status'))
                    ->placeholder(__('subscription_plans.filter_all_plans'))
                    ->trueLabel(__('subscription_plans.filter_active_only'))
                    ->falseLabel(__('subscription_plans.filter_inactive_only')),
                Tables\Filters\TernaryFilter::make('is_highlighted')
                    ->label(__('subscription_plans.filter_highlighted'))
                    ->placeholder(__('subscription_plans.filter_all'))
                    ->trueLabel(__('subscription_plans.filter_highlighted'))
                    ->falseLabel(__('subscription_plans.filter_not_highlighted')),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\EditAction::make(),
                    Tables\Actions\Action::make('toggle_active')
                        ->label(fn (SubscriptionPlan $record) => $record->is_active
                            ? __('subscription_plans.action_toggle_active_deactivate')
                            : __('subscription_plans.action_toggle_active_activate'))
                        ->icon(fn (SubscriptionPlan $record) => $record->is_active ? 'heroicon-o-x-circle' : 'heroicon-o-check-circle')
                        ->color(fn (SubscriptionPlan $record) => $record->is_active ? 'danger' : 'success')
                        ->requiresConfirmation()
                        ->action(fn (SubscriptionPlan $record) => $record->update(['is_active' => ! $record->is_active])),
                    Tables\Actions\Action::make('duplicate')
                        ->label(__('subscription_plans.action_duplicate'))
                        ->icon('heroicon-o-document-duplicate')
                        ->color('gray')
                        ->requiresConfirmation()
                        ->modalDescription(__('subscription_plans.action_duplicate_desc'))
                        ->action(function (SubscriptionPlan $record) {
                            $newPlan = $record->replicate();
                            $newPlan->name = $record->name . ' (Copy)';
                            $newPlan->name_ar = $record->name_ar . ' (نسخة)';
                            $newPlan->slug = $record->slug . '-copy-' . time();
                            $newPlan->is_active = false;
                            $newPlan->save();

                            foreach ($record->planFeatureToggles as $toggle) {
                                $newPlan->planFeatureToggles()->create(
                                    $toggle->only(['feature_key', 'name', 'name_ar', 'is_enabled'])
                                );
                            }
                            foreach ($record->planLimits as $limit) {
                                $newPlan->planLimits()->create($limit->only(['limit_key', 'limit_value', 'price_per_extra_unit']));
                            }
                        }),
                ]),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make(__('subscription_plans.info_plan_overview'))
                ->schema([
                    Infolists\Components\Grid::make(3)->schema([
                        Infolists\Components\TextEntry::make('name')->label(__('subscription_plans.info_name_en')),
                        Infolists\Components\TextEntry::make('name_ar')->label(__('subscription_plans.info_name_ar')),
                        Infolists\Components\TextEntry::make('slug'),
                    ]),
                    Infolists\Components\Grid::make(4)->schema([
                        Infolists\Components\TextEntry::make('monthly_price')->money('SAR'),
                        Infolists\Components\TextEntry::make('annual_price')->money('SAR'),
                        Infolists\Components\TextEntry::make('trial_days')->suffix(' days'),
                        Infolists\Components\TextEntry::make('grace_period_days')->suffix(' days'),
                    ]),
                    Infolists\Components\Grid::make(3)->schema([
                        Infolists\Components\IconEntry::make('is_active')->boolean(),
                        Infolists\Components\IconEntry::make('is_highlighted')->boolean(),
                        Infolists\Components\TextEntry::make('sort_order'),
                    ]),
                ]),
            Infolists\Components\Section::make(__('subscription_plans.section_feature_toggles'))
                ->schema([
                    Infolists\Components\RepeatableEntry::make('planFeatureToggles')
                        ->label('')
                        ->schema([
                            Infolists\Components\TextEntry::make('feature_key')->label(__('subscription_plans.info_feature_key')),
                            Infolists\Components\TextEntry::make('name')->label(__('subscription_plans.info_feature_name_en'))->placeholder('—'),
                            Infolists\Components\TextEntry::make('name_ar')->label(__('subscription_plans.info_feature_name_ar'))->placeholder('—'),
                            Infolists\Components\IconEntry::make('is_enabled')->boolean()->label(__('subscription_plans.info_is_enabled')),
                        ])
                        ->columns(4),
                ])->collapsible(),
            Infolists\Components\Section::make(__('subscription_plans.section_plan_limits'))
                ->schema([
                    Infolists\Components\RepeatableEntry::make('planLimits')
                        ->label('')
                        ->schema([
                            Infolists\Components\TextEntry::make('limit_key')->label(__('subscription_plans.info_resource')),
                            Infolists\Components\TextEntry::make('limit_value')->label(__('subscription_plans.info_limit')),
                            Infolists\Components\TextEntry::make('price_per_extra_unit')
                                ->label(__('subscription_plans.info_overage'))
                                ->money('SAR')
                                ->placeholder('—'),
                        ])
                        ->columns(3),
                ])->collapsible(),
        ]);
    }

    public static function getRelations(): array
    {
        return [
            SubscriptionPlanResource\RelationManagers\StoreSubscriptionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => SubscriptionPlanResource\Pages\ListSubscriptionPlans::route('/'),
            'create' => SubscriptionPlanResource\Pages\CreateSubscriptionPlan::route('/create'),
            'view' => SubscriptionPlanResource\Pages\ViewSubscriptionPlan::route('/{record}'),
            'edit' => SubscriptionPlanResource\Pages\EditSubscriptionPlan::route('/{record}/edit'),
        ];
    }
}
