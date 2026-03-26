<?php

namespace App\Filament\Resources;

use App\Domain\Core\Enums\BusinessType;
use App\Domain\Core\Models\Store;
use App\Domain\Core\Services\OnboardingService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class StoreResource extends Resource
{
    protected static ?string $model = Store::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-storefront';

    protected static ?string $navigationGroup = 'Core';

    protected static ?string $navigationLabel = 'Stores';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'name';

    public static function canAccess(): bool
    {
        $user = auth('admin')->user();

        return $user && $user->hasAnyPermission(['stores.view', 'stores.edit', 'stores.create']);
    }

    public static function getNavigationBadge(): ?string
    {
        $count = Store::where('is_active', true)->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'success';
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'name_ar', 'email', 'phone', 'slug', 'branch_code'];
    }

    // ─── Form ────────────────────────────────────────────────────

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Tabs::make('StoreTabs')
                ->tabs([
                    // ── Tab 1: Basic Info ─────────────────────────
                    Forms\Components\Tabs\Tab::make('Basic Info')
                        ->icon('heroicon-o-building-storefront')
                        ->schema([
                            Forms\Components\Section::make('Store Identity')
                                ->description('Core store information visible to customers')
                                ->schema([
                                    Forms\Components\Select::make('organization_id')
                                        ->label('Organization')
                                        ->relationship('organization', 'name')
                                        ->searchable()
                                        ->preload()
                                        ->required()
                                        ->createOptionForm([
                                            Forms\Components\TextInput::make('name')->required()->maxLength(255),
                                            Forms\Components\TextInput::make('name_ar')->label('Name (Arabic)'),
                                            Forms\Components\TextInput::make('email')->email(),
                                            Forms\Components\TextInput::make('phone')->tel(),
                                            Forms\Components\Select::make('country')
                                                ->options(['SA' => 'Saudi Arabia', 'OM' => 'Oman', 'AE' => 'UAE', 'BH' => 'Bahrain', 'KW' => 'Kuwait', 'QA' => 'Qatar'])
                                                ->default('SA'),
                                        ]),
                                    Forms\Components\TextInput::make('name')
                                        ->label('Store Name (EN)')
                                        ->required()
                                        ->maxLength(255)
                                        ->live(onBlur: true)
                                        ->afterStateUpdated(fn (Forms\Set $set, ?string $state) => $set('slug', Str::slug($state))),
                                    Forms\Components\TextInput::make('name_ar')
                                        ->label('Store Name (AR)')
                                        ->maxLength(255),
                                    Forms\Components\TextInput::make('slug')
                                        ->required()
                                        ->maxLength(255)
                                        ->unique(ignoreRecord: true)
                                        ->helperText('Auto-generated from name'),
                                    Forms\Components\TextInput::make('branch_code')
                                        ->maxLength(50)
                                        ->helperText('Unique branch identifier e.g. BR-001'),
                                    Forms\Components\Select::make('business_type')
                                        ->options(BusinessType::class)
                                        ->required()
                                        ->native(false)
                                        ->searchable(),
                                ])
                                ->columns(2),
                        ]),

                    // ── Tab 2: Contact & Location ─────────────────
                    Forms\Components\Tabs\Tab::make('Contact & Location')
                        ->icon('heroicon-o-map-pin')
                        ->schema([
                            Forms\Components\Section::make('Contact Details')
                                ->schema([
                                    Forms\Components\TextInput::make('phone')->tel()->maxLength(20),
                                    Forms\Components\TextInput::make('email')->email()->maxLength(255),
                                ])
                                ->columns(2),

                            Forms\Components\Section::make('Address')
                                ->schema([
                                    Forms\Components\TextInput::make('address')->maxLength(500)->columnSpanFull(),
                                    Forms\Components\TextInput::make('city')->maxLength(100),
                                    Forms\Components\TextInput::make('latitude')->numeric()->step(0.000001),
                                    Forms\Components\TextInput::make('longitude')->numeric()->step(0.000001),
                                ])
                                ->columns(3),
                        ]),

                    // ── Tab 3: Settings & Preferences ─────────────
                    Forms\Components\Tabs\Tab::make('Settings')
                        ->icon('heroicon-o-cog-6-tooth')
                        ->schema([
                            Forms\Components\Section::make('Regional Settings')
                                ->schema([
                                    Forms\Components\Select::make('timezone')
                                        ->options(collect(timezone_identifiers_list())
                                            ->filter(fn ($tz) => str_starts_with($tz, 'Asia/') || str_starts_with($tz, 'Europe/') || str_starts_with($tz, 'America/') || str_starts_with($tz, 'Africa/'))
                                            ->mapWithKeys(fn ($tz) => [$tz => $tz])
                                            ->toArray())
                                        ->searchable()
                                        ->default('Asia/Riyadh'),
                                    Forms\Components\Select::make('currency')
                                        ->options([
                                            'SAR' => 'SAR — Saudi Riyal',
                                            'OMR' => 'OMR — Omani Rial',
                                            'AED' => 'AED — UAE Dirham',
                                            'BHD' => 'BHD — Bahraini Dinar',
                                            'KWD' => 'KWD — Kuwaiti Dinar',
                                            'QAR' => 'QAR — Qatari Riyal',
                                            'USD' => 'USD — US Dollar',
                                        ])
                                        ->default('SAR')
                                        ->native(false),
                                    Forms\Components\Select::make('locale')
                                        ->options(['ar' => 'العربية (Arabic)', 'en' => 'English'])
                                        ->default('ar')
                                        ->native(false),
                                ])
                                ->columns(3),

                            Forms\Components\Section::make('Status & Flags')
                                ->schema([
                                    Forms\Components\Toggle::make('is_active')
                                        ->label('Active')
                                        ->default(true)
                                        ->helperText('Inactive stores cannot process orders'),
                                    Forms\Components\Toggle::make('is_main_branch')
                                        ->label('Main Branch')
                                        ->default(false)
                                        ->helperText('Only one store per organization should be the main branch'),
                                    Forms\Components\TextInput::make('storage_used_mb')
                                        ->label('Storage Used (MB)')
                                        ->numeric()
                                        ->disabled()
                                        ->default(0),
                                ])
                                ->columns(3),
                        ]),
                ])
                ->columnSpanFull()
                ->persistTabInQueryString(),
        ]);
    }

    // ─── Table ───────────────────────────────────────────────────

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->description(fn (Store $record) => $record->name_ar)
                    ->wrap(),
                Tables\Columns\TextColumn::make('organization.name')
                    ->label('Organization')
                    ->sortable()
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('branch_code')
                    ->label('Branch')
                    ->badge()
                    ->color('gray')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('city')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('business_type')
                    ->badge()
                    ->color(fn ($state) => match ($state?->value ?? $state) {
                        'grocery' => 'success',
                        'restaurant' => 'warning',
                        'pharmacy' => 'success',
                        'bakery' => 'danger',
                        'electronics' => 'info',
                        'florist' => 'danger',
                        'jewelry' => 'info',
                        'fashion' => 'primary',
                        default => 'gray',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('organization.subscription.subscriptionPlan.name')
                    ->label('Plan')
                    ->placeholder('No plan')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('organization.subscription.status')
                    ->label('Sub. Status')
                    ->badge()
                    ->color(fn ($state) => match ($state?->value ?? $state) {
                        'active' => 'success',
                        'trial' => 'info',
                        'grace' => 'warning',
                        'cancelled' => 'danger',
                        'expired' => 'danger',
                        default => 'gray',
                    })
                    ->toggleable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean()
                    ->label('Active')
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_main_branch')
                    ->boolean()
                    ->label('Main')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('phone')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('email')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('onboardingProgress.is_wizard_completed')
                    ->label('Onboarded')
                    ->formatStateUsing(fn ($state) => $state ? '✅ Yes' : '⏳ In progress')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime('M j, Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')->label('Active'),
                Tables\Filters\SelectFilter::make('business_type')
                    ->options(BusinessType::class)
                    ->multiple(),
                Tables\Filters\SelectFilter::make('organization_id')
                    ->relationship('organization', 'name')
                    ->searchable()
                    ->preload()
                    ->label('Organization'),
                Tables\Filters\TernaryFilter::make('is_main_branch')->label('Main Branch'),
                Tables\Filters\Filter::make('has_subscription')
                    ->label('Has Subscription')
                    ->query(fn (Builder $query) => $query->whereHas('organization.subscription')),
                Tables\Filters\Filter::make('no_subscription')
                    ->label('No Subscription')
                    ->query(fn (Builder $query) => $query->whereDoesntHave('organization.subscription')),
                Tables\Filters\Filter::make('onboarding_incomplete')
                    ->label('Onboarding Incomplete')
                    ->query(fn (Builder $query) => $query->whereHas('onboardingProgress', fn ($q) => $q->where('is_wizard_completed', false))),
                Tables\Filters\Filter::make('created_at')
                    ->form([
                        Forms\Components\DatePicker::make('from'),
                        Forms\Components\DatePicker::make('until'),
                    ])
                    ->query(function (Builder $query, array $data) {
                        return $query
                            ->when($data['from'] ?? null, fn (Builder $q, $d) => $q->whereDate('created_at', '>=', $d))
                            ->when($data['until'] ?? null, fn (Builder $q, $d) => $q->whereDate('created_at', '<=', $d));
                    }),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\EditAction::make(),
                    Tables\Actions\Action::make('suspend')
                        ->label('Suspend')
                        ->icon('heroicon-o-no-symbol')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalDescription('This will deactivate the store. All operations will be paused.')
                        ->visible(fn (Store $record) => $record->is_active && auth('admin')->user()?->hasPermission('stores.suspend'))
                        ->action(function (Store $record) {
                            $record->update(['is_active' => false]);
                            Notification::make()->title('Store suspended')->warning()->send();
                        }),
                    Tables\Actions\Action::make('activate')
                        ->label('Activate')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->visible(fn (Store $record) => ! $record->is_active && auth('admin')->user()?->hasPermission('stores.suspend'))
                        ->action(function (Store $record) {
                            $record->update(['is_active' => true]);
                            Notification::make()->title('Store activated')->success()->send();
                        }),
                    Tables\Actions\Action::make('reset_onboarding')
                        ->label('Reset Onboarding')
                        ->icon('heroicon-o-arrow-path')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->modalDescription('This will reset the onboarding wizard so the store can go through setup again.')
                        ->visible(fn () => auth('admin')->user()?->hasPermission('stores.edit'))
                        ->action(function (Store $record) {
                            app(OnboardingService::class)->resetOnboarding($record->id);
                            Notification::make()->title('Onboarding reset')->success()->send();
                        }),
                ]),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('bulk_suspend')
                        ->label('Suspend Selected')
                        ->icon('heroicon-o-no-symbol')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->deselectRecordsAfterCompletion()
                        ->visible(fn () => auth('admin')->user()?->hasPermission('stores.suspend'))
                        ->action(fn ($records) => $records->each(fn ($r) => $r->update(['is_active' => false]))),
                    Tables\Actions\BulkAction::make('bulk_activate')
                        ->label('Activate Selected')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->deselectRecordsAfterCompletion()
                        ->visible(fn () => auth('admin')->user()?->hasPermission('stores.suspend'))
                        ->action(fn ($records) => $records->each(fn ($r) => $r->update(['is_active' => true]))),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    // ─── Infolist (View Page) ────────────────────────────────────

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Tabs::make('StoreTabs')
                ->tabs([
                    Infolists\Components\Tabs\Tab::make('Overview')
                        ->icon('heroicon-o-building-storefront')
                        ->schema([
                            Infolists\Components\Section::make('Store Identity')
                                ->schema([
                                    Infolists\Components\TextEntry::make('name')->weight('bold'),
                                    Infolists\Components\TextEntry::make('name_ar')->label('Name (AR)'),
                                    Infolists\Components\TextEntry::make('slug')->copyable(),
                                    Infolists\Components\TextEntry::make('branch_code')->placeholder('N/A'),
                                    Infolists\Components\TextEntry::make('organization.name')->label('Organization'),
                                    Infolists\Components\TextEntry::make('business_type')
                                        ->badge()
                                        ->color(fn ($state) => match ($state?->value ?? $state) {
                                            'grocery' => 'success', 'restaurant' => 'warning',
                                            'pharmacy' => 'success', 'bakery' => 'danger',
                                            'electronics' => 'info', 'florist' => 'danger',
                                            'jewelry' => 'info', 'fashion' => 'primary',
                                            default => 'gray',
                                        }),
                                ])
                                ->columns(3),

                            Infolists\Components\Section::make('Contact & Location')
                                ->schema([
                                    Infolists\Components\TextEntry::make('phone')->copyable()->placeholder('N/A'),
                                    Infolists\Components\TextEntry::make('email')->copyable()->placeholder('N/A'),
                                    Infolists\Components\TextEntry::make('address')->placeholder('N/A')->columnSpanFull(),
                                    Infolists\Components\TextEntry::make('city')->placeholder('N/A'),
                                    Infolists\Components\TextEntry::make('latitude')->placeholder('N/A'),
                                    Infolists\Components\TextEntry::make('longitude')->placeholder('N/A'),
                                ])
                                ->columns(3),

                            Infolists\Components\Section::make('Status')
                                ->schema([
                                    Infolists\Components\IconEntry::make('is_active')->boolean()->label('Active'),
                                    Infolists\Components\IconEntry::make('is_main_branch')->boolean()->label('Main Branch'),
                                    Infolists\Components\TextEntry::make('timezone'),
                                    Infolists\Components\TextEntry::make('currency'),
                                    Infolists\Components\TextEntry::make('locale'),
                                    Infolists\Components\TextEntry::make('storage_used_mb')->suffix(' MB'),
                                    Infolists\Components\TextEntry::make('created_at')->dateTime(),
                                    Infolists\Components\TextEntry::make('updated_at')->dateTime(),
                                ])
                                ->columns(4),
                        ]),

                    Infolists\Components\Tabs\Tab::make('Subscription')
                        ->icon('heroicon-o-credit-card')
                        ->schema([
                            Infolists\Components\Section::make('Organization Subscription')
                                ->schema([
                                    Infolists\Components\TextEntry::make('organization.subscription.subscriptionPlan.name')
                                        ->label('Plan')
                                        ->placeholder('No subscription')
                                        ->weight('bold'),
                                    Infolists\Components\TextEntry::make('organization.subscription.status')
                                        ->label('Status')
                                        ->badge()
                                        ->color(fn ($state) => match ($state?->value ?? $state) {
                                            'active' => 'success', 'trial' => 'info',
                                            'grace' => 'warning', 'expired', 'cancelled' => 'danger',
                                            default => 'gray',
                                        }),
                                    Infolists\Components\TextEntry::make('organization.subscription.billing_cycle')
                                        ->label('Billing Cycle')
                                        ->placeholder('N/A'),
                                    Infolists\Components\TextEntry::make('organization.subscription.payment_method')
                                        ->label('Payment Method')
                                        ->placeholder('N/A'),
                                    Infolists\Components\TextEntry::make('organization.subscription.current_period_start')
                                        ->label('Period Start')
                                        ->dateTime()
                                        ->placeholder('N/A'),
                                    Infolists\Components\TextEntry::make('organization.subscription.current_period_end')
                                        ->label('Period End')
                                        ->dateTime()
                                        ->placeholder('N/A'),
                                    Infolists\Components\TextEntry::make('organization.subscription.trial_ends_at')
                                        ->label('Trial Ends')
                                        ->dateTime()
                                        ->placeholder('No trial'),
                                ])
                                ->columns(4),
                        ]),

                    Infolists\Components\Tabs\Tab::make('Onboarding')
                        ->icon('heroicon-o-academic-cap')
                        ->schema([
                            Infolists\Components\Section::make('Onboarding Progress')
                                ->schema([
                                    Infolists\Components\TextEntry::make('onboardingProgress.current_step')
                                        ->label('Current Step')
                                        ->placeholder('Not started'),
                                    Infolists\Components\IconEntry::make('onboardingProgress.is_wizard_completed')
                                        ->boolean()
                                        ->label('Wizard Completed'),
                                    Infolists\Components\TextEntry::make('onboardingProgress.completed_steps')
                                        ->label('Completed Steps')
                                        ->formatStateUsing(fn ($state) => is_array($state) ? implode(', ', $state) : ($state ?? 'None'))
                                        ->columnSpanFull(),
                                    Infolists\Components\TextEntry::make('onboardingProgress.started_at')
                                        ->label('Started')
                                        ->dateTime()
                                        ->placeholder('N/A'),
                                    Infolists\Components\TextEntry::make('onboardingProgress.completed_at')
                                        ->label('Completed')
                                        ->dateTime()
                                        ->placeholder('N/A'),
                                ])
                                ->columns(4),
                        ]),
                ])
                ->columnSpanFull(),
        ]);
    }

    // ─── Relations ───────────────────────────────────────────────

    public static function getRelations(): array
    {
        return [
            StoreResource\RelationManagers\WorkingHoursRelationManager::class,
            StoreResource\RelationManagers\UsersRelationManager::class,
            StoreResource\RelationManagers\RegistersRelationManager::class,
        ];
    }

    // ─── Pages ───────────────────────────────────────────────────

    public static function getPages(): array
    {
        return [
            'index' => StoreResource\Pages\ListStores::route('/'),
            'create' => StoreResource\Pages\CreateStore::route('/create'),
            'map' => StoreResource\Pages\MapStores::route('/map'),
            'view' => StoreResource\Pages\ViewStore::route('/{record}'),
            'edit' => StoreResource\Pages\EditStore::route('/{record}/edit'),
        ];
    }
}
