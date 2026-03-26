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

    protected static ?string $navigationGroup = 'Subscription & Billing';

    protected static ?string $navigationLabel = 'Subscription Plans';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    public static function canAccess(): bool
    {
        $user = auth('admin')->user();

        return $user && $user->hasAnyPermission(['billing.plans', 'billing.view']);
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) SubscriptionPlan::where('is_active', true)->count();
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'primary';
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Tabs::make('Plan Builder')->tabs([
                // ─── General Info ─────────────────────────────────────
                Forms\Components\Tabs\Tab::make('General')
                    ->icon('heroicon-o-information-circle')
                    ->schema([
                        Forms\Components\Section::make('Plan Details')
                            ->description('Basic plan information visible to subscribers')
                            ->schema([
                                Forms\Components\Grid::make(2)->schema([
                                    Forms\Components\TextInput::make('name')
                                        ->label('Plan Name (EN)')
                                        ->required()
                                        ->maxLength(100)
                                        ->placeholder('e.g. Professional'),
                                    Forms\Components\TextInput::make('name_ar')
                                        ->label('Plan Name (AR)')
                                        ->required()
                                        ->maxLength(100)
                                        ->placeholder('مثال: احترافي'),
                                ]),
                                Forms\Components\TextInput::make('slug')
                                    ->required()
                                    ->maxLength(50)
                                    ->unique(ignoreRecord: true)
                                    ->alphaDash()
                                    ->placeholder('professional'),
                                Forms\Components\Grid::make(2)->schema([
                                    Forms\Components\Textarea::make('description')
                                        ->label('Description (EN)')
                                        ->rows(3)
                                        ->placeholder('Best for growing businesses'),
                                    Forms\Components\Textarea::make('description_ar')
                                        ->label('Description (AR)')
                                        ->rows(3)
                                        ->placeholder('الأفضل للأعمال المتنامية'),
                                ]),
                            ]),
                        Forms\Components\Section::make('Display Settings')
                            ->schema([
                                Forms\Components\Grid::make(3)->schema([
                                    Forms\Components\Toggle::make('is_active')
                                        ->label('Active')
                                        ->helperText('Only active plans are visible to subscribers')
                                        ->default(true),
                                    Forms\Components\Toggle::make('is_highlighted')
                                        ->label('Highlighted / Recommended')
                                        ->helperText('Highlight this plan on the pricing page'),
                                    Forms\Components\TextInput::make('sort_order')
                                        ->numeric()
                                        ->default(0)
                                        ->helperText('Lower numbers appear first'),
                                ]),
                            ])->collapsible(),
                    ]),

                // ─── Pricing ──────────────────────────────────────────
                Forms\Components\Tabs\Tab::make('Pricing')
                    ->icon('heroicon-o-currency-dollar')
                    ->schema([
                        Forms\Components\Section::make('Subscription Pricing')
                            ->description('Set monthly and annual pricing in SAR')
                            ->schema([
                                Forms\Components\Grid::make(2)->schema([
                                    Forms\Components\TextInput::make('monthly_price')
                                        ->label('Monthly Price (SAR)')
                                        ->required()
                                        ->numeric()
                                        ->prefix('SAR')
                                        ->minValue(0)
                                        ->step(0.01),
                                    Forms\Components\TextInput::make('annual_price')
                                        ->label('Annual Price (SAR)')
                                        ->required()
                                        ->numeric()
                                        ->prefix('SAR')
                                        ->minValue(0)
                                        ->step(0.01)
                                        ->helperText('Typically a ~17% discount over monthly'),
                                ]),
                            ]),
                        Forms\Components\Section::make('Trial & Grace Period')
                            ->description('Configure trial and grace periods for this plan')
                            ->schema([
                                Forms\Components\Grid::make(2)->schema([
                                    Forms\Components\TextInput::make('trial_days')
                                        ->label('Trial Period (days)')
                                        ->numeric()
                                        ->default(14)
                                        ->minValue(0)
                                        ->maxValue(90)
                                        ->helperText('0 = no trial'),
                                    Forms\Components\TextInput::make('grace_period_days')
                                        ->label('Grace Period (days)')
                                        ->numeric()
                                        ->default(7)
                                        ->minValue(0)
                                        ->maxValue(30)
                                        ->helperText('Days after payment failure before suspension'),
                                ]),
                            ])->collapsible(),
                    ]),

                // ─── Feature Toggles ──────────────────────────────────
                Forms\Components\Tabs\Tab::make('Features')
                    ->icon('heroicon-o-cog-6-tooth')
                    ->schema([
                        Forms\Components\Section::make('Feature Toggles')
                            ->description('Enable or disable features for this plan. Each feature key maps to an application module.')
                            ->schema([
                                Forms\Components\Repeater::make('planFeatureToggles')
                                    ->relationship()
                                    ->label('')
                                    ->schema([
                                        Forms\Components\Grid::make(3)->schema([
                                            Forms\Components\Select::make('feature_key')
                                                ->label('Feature')
                                                ->options([
                                                    'pos_basic' => 'POS Basic',
                                                    'pos_advanced' => 'POS Advanced',
                                                    'inventory' => 'Inventory Management',
                                                    'multi_store' => 'Multi-Store',
                                                    'staff_management' => 'Staff Management',
                                                    'customer_management' => 'Customer Management',
                                                    'loyalty_program' => 'Loyalty Program',
                                                    'promotions' => 'Promotions & Discounts',
                                                    'delivery_integration' => 'Delivery Integration',
                                                    'analytics_basic' => 'Basic Analytics',
                                                    'analytics_advanced' => 'Advanced Analytics',
                                                    'custom_receipts' => 'Custom Receipts',
                                                    'cfd_display' => 'Customer-Facing Display',
                                                    'digital_signage' => 'Digital Signage',
                                                    'zatca_compliance' => 'ZATCA Compliance',
                                                    'api_access' => 'API Access',
                                                    'custom_roles' => 'Custom Roles',
                                                    'custom_themes' => 'Custom Themes',
                                                    'appointments' => 'Appointments',
                                                    'table_management' => 'Table Management',
                                                    'kitchen_display' => 'Kitchen Display',
                                                    'pharmacy' => 'Pharmacy Features',
                                                    'buyback_tradein' => 'Buyback/Trade-In',
                                                    'label_printing' => 'Label Printing',
                                                    'offline_mode' => 'Offline Mode',
                                                    'accounting_export' => 'Accounting Export',
                                                    'gamification' => 'Gamification',
                                                ])
                                                ->required()
                                                ->searchable()
                                                ->columnSpan(2),
                                            Forms\Components\Toggle::make('is_enabled')
                                                ->label('Enabled')
                                                ->default(true),
                                        ]),
                                    ])
                                    ->defaultItems(0)
                                    ->addActionLabel('Add Feature Toggle')
                                    ->reorderable(false)
                                    ->collapsible()
                                    ->itemLabel(fn (array $state): ?string => $state['feature_key'] ?? 'New Feature'),
                            ]),
                    ]),

                // ─── Limits ───────────────────────────────────────────
                Forms\Components\Tabs\Tab::make('Limits')
                    ->icon('heroicon-o-chart-bar')
                    ->schema([
                        Forms\Components\Section::make('Plan Limits')
                            ->description('Set hard limits for each resource. Stores exceeding limits will be prompted to upgrade.')
                            ->schema([
                                Forms\Components\Repeater::make('planLimits')
                                    ->relationship()
                                    ->label('')
                                    ->schema([
                                        Forms\Components\Grid::make(3)->schema([
                                            Forms\Components\Select::make('limit_key')
                                                ->label('Resource')
                                                ->options([
                                                    'products' => 'Products',
                                                    'staff_members' => 'Staff Members',
                                                    'stores' => 'Stores / Branches',
                                                    'transactions_month' => 'Transactions / Month',
                                                    'customers' => 'Customers',
                                                    'categories' => 'Categories',
                                                    'promotions' => 'Active Promotions',
                                                    'custom_roles' => 'Custom Roles',
                                                    'api_calls_day' => 'API Calls / Day',
                                                    'storage_mb' => 'Storage (MB)',
                                                    'reports' => 'Saved Reports',
                                                ])
                                                ->required()
                                                ->searchable(),
                                            Forms\Components\TextInput::make('limit_value')
                                                ->label('Limit')
                                                ->numeric()
                                                ->required()
                                                ->minValue(0)
                                                ->helperText('0 = disabled, -1 = unlimited'),
                                            Forms\Components\TextInput::make('price_per_extra_unit')
                                                ->label('Overage Price (SAR)')
                                                ->numeric()
                                                ->prefix('SAR')
                                                ->minValue(0)
                                                ->step(0.01)
                                                ->helperText('Charge per extra unit above limit'),
                                        ]),
                                    ])
                                    ->defaultItems(0)
                                    ->addActionLabel('Add Limit')
                                    ->reorderable(false)
                                    ->collapsible()
                                    ->itemLabel(fn (array $state): ?string => $state['limit_key'] ?? 'New Limit'),
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
                    ->label('Plan')
                    ->searchable()
                    ->sortable()
                    ->description(fn (SubscriptionPlan $record): string => $record->name_ar ?? ''),
                Tables\Columns\TextColumn::make('slug')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('monthly_price')
                    ->label('Monthly')
                    ->sortable()
                    ->money('SAR'),
                Tables\Columns\TextColumn::make('annual_price')
                    ->label('Annual')
                    ->money('SAR'),
                Tables\Columns\TextColumn::make('trial_days')
                    ->label('Trial')
                    ->suffix(' days')
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('storeSubscriptions_count')
                    ->counts('storeSubscriptions')
                    ->label('Subscribers')
                    ->alignCenter()
                    ->sortable(),
                Tables\Columns\TextColumn::make('planFeatureToggles_count')
                    ->counts('planFeatureToggles')
                    ->label('Features')
                    ->alignCenter()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\IconColumn::make('is_highlighted')
                    ->label('★')
                    ->boolean()
                    ->alignCenter()
                    ->toggleable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('sort_order')
                    ->label('Order')
                    ->sortable()
                    ->alignCenter()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Status')
                    ->placeholder('All Plans')
                    ->trueLabel('Active Only')
                    ->falseLabel('Inactive Only'),
                Tables\Filters\TernaryFilter::make('is_highlighted')
                    ->label('Highlighted')
                    ->placeholder('All')
                    ->trueLabel('Highlighted')
                    ->falseLabel('Not Highlighted'),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\EditAction::make(),
                    Tables\Actions\Action::make('toggle_active')
                        ->label(fn (SubscriptionPlan $record) => $record->is_active ? 'Deactivate' : 'Activate')
                        ->icon(fn (SubscriptionPlan $record) => $record->is_active ? 'heroicon-o-x-circle' : 'heroicon-o-check-circle')
                        ->color(fn (SubscriptionPlan $record) => $record->is_active ? 'danger' : 'success')
                        ->requiresConfirmation()
                        ->action(fn (SubscriptionPlan $record) => $record->update(['is_active' => ! $record->is_active])),
                    Tables\Actions\Action::make('duplicate')
                        ->label('Duplicate Plan')
                        ->icon('heroicon-o-document-duplicate')
                        ->color('gray')
                        ->requiresConfirmation()
                        ->modalDescription('This will create a new draft plan based on this one, including features and limits.')
                        ->action(function (SubscriptionPlan $record) {
                            $newPlan = $record->replicate();
                            $newPlan->name = $record->name . ' (Copy)';
                            $newPlan->name_ar = $record->name_ar . ' (نسخة)';
                            $newPlan->slug = $record->slug . '-copy-' . time();
                            $newPlan->is_active = false;
                            $newPlan->save();

                            foreach ($record->planFeatureToggles as $toggle) {
                                $newPlan->planFeatureToggles()->create($toggle->only(['feature_key', 'is_enabled']));
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
            Infolists\Components\Section::make('Plan Overview')
                ->schema([
                    Infolists\Components\Grid::make(3)->schema([
                        Infolists\Components\TextEntry::make('name')->label('Name (EN)'),
                        Infolists\Components\TextEntry::make('name_ar')->label('Name (AR)'),
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
            Infolists\Components\Section::make('Feature Toggles')
                ->schema([
                    Infolists\Components\RepeatableEntry::make('planFeatureToggles')
                        ->label('')
                        ->schema([
                            Infolists\Components\TextEntry::make('feature_key')->label('Feature'),
                            Infolists\Components\IconEntry::make('is_enabled')->boolean()->label('Enabled'),
                        ])
                        ->columns(2),
                ])->collapsible(),
            Infolists\Components\Section::make('Plan Limits')
                ->schema([
                    Infolists\Components\RepeatableEntry::make('planLimits')
                        ->label('')
                        ->schema([
                            Infolists\Components\TextEntry::make('limit_key')->label('Resource'),
                            Infolists\Components\TextEntry::make('limit_value')->label('Limit'),
                            Infolists\Components\TextEntry::make('price_per_extra_unit')
                                ->label('Overage')
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
