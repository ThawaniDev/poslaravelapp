<?php

namespace App\Filament\Resources;

use App\Domain\Core\Enums\BusinessType;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use App\Domain\Core\Services\StoreService;
use App\Services\SupabaseStorageService;
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

class OrganizationResource extends Resource
{
    protected static ?string $model = Organization::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?string $navigationGroup = null;

    public static function getNavigationGroup(): ?string
    {
        return __('nav.group_core');
    }

    protected static ?string $navigationLabel = null;

    public static function getNavigationLabel(): string
    {
        return __('nav.organizations');
    }

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    public static function canAccess(): bool
    {
        $user = auth('admin')->user();

        return $user && $user->hasAnyPermission(['stores.view', 'stores.edit', 'stores.create']);
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'name_ar', 'email', 'phone', 'cr_number', 'vat_number'];
    }

    // ─── Form ────────────────────────────────────────────────────

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Tabs::make('OrganizationTabs')
                ->tabs([
                    Forms\Components\Tabs\Tab::make(__('Basic Info'))
                        ->icon('heroicon-o-building-office-2')
                        ->schema([
                            Forms\Components\Section::make(__('Organization Identity'))
                                ->schema([
                                    Forms\Components\TextInput::make('name')
                                        ->label(__('Name (EN)'))
                                        ->required()
                                        ->maxLength(255)
                                        ->live(onBlur: true)
                                        ->afterStateUpdated(fn (Forms\Set $set, ?string $state) => $set('slug', Str::slug($state))),
                                    Forms\Components\TextInput::make('name_ar')
                                        ->label(__('Name (AR)'))
                                        ->maxLength(255),
                                    Forms\Components\TextInput::make('slug')
                                        ->required()
                                        ->maxLength(255)
                                        ->unique(ignoreRecord: true),
                                    Forms\Components\Select::make('business_type')
                                        ->options(BusinessType::class)
                                        ->native(false)
                                        ->searchable(),
                                    Forms\Components\FileUpload::make('logo_url')
                                        ->label(__('Logo'))
                                        ->image()
                                        ->imageEditor()
                                        ->maxSize(5120)
                                        ->saveUploadedFileUsing(function ($file) {
                                            return app(SupabaseStorageService::class)->upload($file, 'OrganizationsLogos');
                                        })
                                        ->deleteUploadedFileUsing(function ($file) {
                                            app(SupabaseStorageService::class)->delete($file);
                                        }),
                                ])
                                ->columns(2),

                            Forms\Components\Section::make(__('Legal Information'))
                                ->schema([
                                    Forms\Components\TextInput::make('cr_number')
                                        ->label(__('CR Number'))
                                        ->maxLength(50)
                                        ->helperText(__('Commercial Registration number')),
                                    Forms\Components\TextInput::make('vat_number')
                                        ->label(__('VAT Number'))
                                        ->maxLength(50)
                                        ->helperText(__('Tax identification number')),
                                ])
                                ->columns(2),
                        ]),

                    Forms\Components\Tabs\Tab::make(__('Contact & Location'))
                        ->icon('heroicon-o-map-pin')
                        ->schema([
                            Forms\Components\Section::make(__('Contact'))
                                ->schema([
                                    Forms\Components\TextInput::make('phone')->tel()->maxLength(20),
                                    Forms\Components\TextInput::make('email')->email()->maxLength(255),
                                ])
                                ->columns(2),

                            Forms\Components\Section::make(__('Address'))
                                ->schema([
                                    Forms\Components\TextInput::make('address')->maxLength(500)->columnSpanFull(),
                                    Forms\Components\TextInput::make('city')->maxLength(100),
                                    Forms\Components\Select::make('country')
                                        ->options([
                                            'SA' => __('Saudi Arabia'),
                                            'OM' => __('Oman'),
                                            'AE' => __('UAE'),
                                            'BH' => __('Bahrain'),
                                            'KW' => __('Kuwait'),
                                            'QA' => __('Qatar'),
                                        ])
                                        ->default('SA')
                                        ->native(false),
                                ])
                                ->columns(2),
                        ]),

                    Forms\Components\Tabs\Tab::make(__('Status'))
                        ->icon('heroicon-o-cog-6-tooth')
                        ->schema([
                            Forms\Components\Section::make(__('Activation'))
                                ->schema([
                                    Forms\Components\Toggle::make('is_active')
                                        ->label(__('Active'))
                                        ->default(true)
                                        ->helperText(__('Inactive organizations and all their stores will be suspended')),
                                ]),
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
                    ->description(fn (Organization $record) => $record->name_ar)
                    ->wrap(),
                Tables\Columns\TextColumn::make('cr_number')
                    ->label(__('CR Number'))
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('vat_number')
                    ->label(__('VAT'))
                    ->searchable()
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
                Tables\Columns\TextColumn::make('country')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('city')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('subscription.subscriptionPlan.name')
                    ->label(__('Plan'))
                    ->placeholder(__('No plan'))
                    ->toggleable(),
                Tables\Columns\TextColumn::make('subscription.status')
                    ->label(__('Sub. Status'))
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
                Tables\Columns\TextColumn::make('stores_count')
                    ->counts('stores')
                    ->label(__('Stores'))
                    ->sortable()
                    ->badge()
                    ->color('info'),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean()
                    ->label(__('Active'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('phone')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('email')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime('M j, Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')->label(__('Active')),
                Tables\Filters\SelectFilter::make('business_type')
                    ->options(BusinessType::class)
                    ->multiple(),
                Tables\Filters\SelectFilter::make('country')
                    ->options([
                        'SA' => __('Saudi Arabia'),
                        'OM' => __('Oman'),
                        'AE' => __('UAE'),
                        'BH' => __('Bahrain'),
                        'KW' => __('Kuwait'),
                        'QA' => __('Qatar'),
                    ]),
                Tables\Filters\Filter::make('has_stores')
                    ->label(__('Has Stores'))
                    ->query(fn (Builder $query) => $query->has('stores')),
                Tables\Filters\Filter::make('no_stores')
                    ->label(__('No Stores'))
                    ->query(fn (Builder $query) => $query->doesntHave('stores')),
                Tables\Filters\Filter::make('has_subscription')
                    ->label(__('Has Subscription'))
                    ->query(fn (Builder $query) => $query->whereHas('subscription')),
                Tables\Filters\Filter::make('no_subscription')
                    ->label(__('No Subscription'))
                    ->query(fn (Builder $query) => $query->whereDoesntHave('subscription')),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\EditAction::make(),
                    Tables\Actions\Action::make('onboard_store')
                        ->label(__('Onboard New Store'))
                        ->icon('heroicon-o-plus-circle')
                        ->color('success')
                        ->visible(fn () => auth('admin')->user()?->hasPermission('stores.create'))
                        ->form([
                            Forms\Components\TextInput::make('store_name')
                                ->label(__('Store Name (EN)'))
                                ->required()
                                ->maxLength(255),
                            Forms\Components\TextInput::make('store_name_ar')
                                ->label(__('Store Name (AR)'))
                                ->maxLength(255),
                            Forms\Components\Select::make('business_type')
                                ->options(BusinessType::class)
                                ->required()
                                ->native(false),
                            Forms\Components\TextInput::make('phone')->tel(),
                            Forms\Components\TextInput::make('email')->email(),
                            Forms\Components\TextInput::make('city')->maxLength(100),
                            Forms\Components\Toggle::make('is_main_branch')
                                ->label(__('Main Branch'))
                                ->default(false),
                        ])
                        ->action(function (Organization $record, array $data) {
                            $storeService = app(StoreService::class);
                            $storeService->createStore([
                                'organization_id' => $record->id,
                                'name' => $data['store_name'],
                                'name_ar' => $data['store_name_ar'] ?? null,
                                'slug' => Str::slug($data['store_name']),
                                'business_type' => $data['business_type'],
                                'phone' => $data['phone'] ?? null,
                                'email' => $data['email'] ?? null,
                                'city' => $data['city'] ?? null,
                                'is_main_branch' => $data['is_main_branch'] ?? false,
                            ]);

                            Notification::make()
                                ->title(__('Store created and onboarding started'))
                                ->success()
                                ->send();
                        }),
                    Tables\Actions\Action::make('suspend')
                        ->label(__('Suspend'))
                        ->icon('heroicon-o-no-symbol')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalDescription(__('This will deactivate the organization and all its stores.'))
                        ->visible(fn (Organization $record) => $record->is_active && auth('admin')->user()?->hasPermission('stores.suspend'))
                        ->action(function (Organization $record) {
                            $record->update(['is_active' => false]);
                            $record->stores()->update(['is_active' => false]);
                            Notification::make()->title(__('Organization suspended'))->warning()->send();
                        }),
                    Tables\Actions\Action::make('activate')
                        ->label(__('Activate'))
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->visible(fn (Organization $record) => ! $record->is_active && auth('admin')->user()?->hasPermission('stores.suspend'))
                        ->action(function (Organization $record) {
                            $record->update(['is_active' => true]);
                            $record->stores()->update(['is_active' => true]);
                            Notification::make()->title(__('Organization activated'))->success()->send();
                        }),
                ]),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('bulk_suspend')
                        ->label(__('Suspend Selected'))
                        ->icon('heroicon-o-no-symbol')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->deselectRecordsAfterCompletion()
                        ->visible(fn () => auth('admin')->user()?->hasPermission('stores.suspend'))
                        ->action(function ($records) {
                            $records->each(function ($record) {
                                $record->update(['is_active' => false]);
                                $record->stores()->update(['is_active' => false]);
                            });
                        }),
                    Tables\Actions\BulkAction::make('bulk_activate')
                        ->label(__('Activate Selected'))
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->deselectRecordsAfterCompletion()
                        ->visible(fn () => auth('admin')->user()?->hasPermission('stores.suspend'))
                        ->action(function ($records) {
                            $records->each(function ($record) {
                                $record->update(['is_active' => true]);
                                $record->stores()->update(['is_active' => true]);
                            });
                        }),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    // ─── Infolist (View Page) ────────────────────────────────────

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Tabs::make('OrgTabs')
                ->tabs([
                    Infolists\Components\Tabs\Tab::make(__('Overview'))
                        ->icon('heroicon-o-building-office-2')
                        ->schema([
                            Infolists\Components\Section::make(__('Organization Identity'))
                                ->schema([
                                    Infolists\Components\TextEntry::make('name')->weight('bold'),
                                    Infolists\Components\TextEntry::make('name_ar')->label(__('Name (AR)')),
                                    Infolists\Components\TextEntry::make('slug')->copyable(),
                                    Infolists\Components\TextEntry::make('business_type')
                                        ->badge()
                                        ->color(fn ($state) => match ($state?->value ?? $state) {
                                            'grocery' => 'success', 'restaurant' => 'warning',
                                            'pharmacy' => 'success', 'bakery' => 'danger',
                                            'electronics' => 'info', 'florist' => 'danger',
                                            'jewelry' => 'info', 'fashion' => 'primary',
                                            default => 'gray',
                                        }),
                                    Infolists\Components\ImageEntry::make('logo_url')
                                        ->label(__('Logo'))
                                        ->getStateUsing(fn ($record) => SupabaseStorageService::resolveUrl($record->logo_url))
                                        ->placeholder(__('N/A')),
                                ])
                                ->columns(3),

                            Infolists\Components\Section::make(__('Legal Information'))
                                ->schema([
                                    Infolists\Components\TextEntry::make('cr_number')->label(__('CR Number'))->placeholder(__('N/A'))->copyable(),
                                    Infolists\Components\TextEntry::make('vat_number')->label(__('VAT Number'))->placeholder(__('N/A'))->copyable(),
                                ])
                                ->columns(2),

                            Infolists\Components\Section::make(__('Contact & Location'))
                                ->schema([
                                    Infolists\Components\TextEntry::make('phone')->copyable()->placeholder(__('N/A')),
                                    Infolists\Components\TextEntry::make('email')->copyable()->placeholder(__('N/A')),
                                    Infolists\Components\TextEntry::make('address')->placeholder(__('N/A'))->columnSpanFull(),
                                    Infolists\Components\TextEntry::make('city')->placeholder(__('N/A')),
                                    Infolists\Components\TextEntry::make('country')->placeholder(__('N/A')),
                                ])
                                ->columns(3),

                            Infolists\Components\Section::make(__('Status'))
                                ->schema([
                                    Infolists\Components\IconEntry::make('is_active')->boolean()->label(__('Active')),
                                    Infolists\Components\TextEntry::make('stores_count')
                                        ->label(__('Total Stores'))
                                        ->state(fn (Organization $record) => $record->stores()->count())
                                        ->badge()
                                        ->color('info'),
                                    Infolists\Components\TextEntry::make('created_at')->dateTime(),
                                    Infolists\Components\TextEntry::make('updated_at')->dateTime(),
                                ])
                                ->columns(4),
                        ]),

                    Infolists\Components\Tabs\Tab::make(__('Subscription'))
                        ->icon('heroicon-o-credit-card')
                        ->schema([
                            Infolists\Components\Section::make(__('Current Subscription'))
                                ->schema([
                                    Infolists\Components\TextEntry::make('subscription.subscriptionPlan.name')
                                        ->label(__('Plan'))
                                        ->placeholder(__('No subscription'))
                                        ->weight('bold'),
                                    Infolists\Components\TextEntry::make('subscription.status')
                                        ->label(__('Status'))
                                        ->badge()
                                        ->color(fn ($state) => match ($state?->value ?? $state) {
                                            'active' => 'success', 'trial' => 'info',
                                            'grace' => 'warning', 'expired', 'cancelled' => 'danger',
                                            default => 'gray',
                                        }),
                                    Infolists\Components\TextEntry::make('subscription.billing_cycle')
                                        ->label(__('Billing Cycle'))
                                        ->placeholder(__('N/A')),
                                    Infolists\Components\TextEntry::make('subscription.payment_method')
                                        ->label(__('Payment Method'))
                                        ->placeholder(__('N/A')),
                                    Infolists\Components\TextEntry::make('subscription.current_period_start')
                                        ->label(__('Period Start'))
                                        ->dateTime()
                                        ->placeholder(__('N/A')),
                                    Infolists\Components\TextEntry::make('subscription.current_period_end')
                                        ->label(__('Period End'))
                                        ->dateTime()
                                        ->placeholder(__('N/A')),
                                    Infolists\Components\TextEntry::make('subscription.trial_ends_at')
                                        ->label(__('Trial Ends'))
                                        ->dateTime()
                                        ->placeholder(__('No trial')),
                                    Infolists\Components\TextEntry::make('subscription.cancelled_at')
                                        ->label(__('Cancelled At'))
                                        ->dateTime()
                                        ->placeholder(__('N/A')),
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
            OrganizationResource\RelationManagers\StoresRelationManager::class,
        ];
    }

    // ─── Pages ───────────────────────────────────────────────────

    public static function getPages(): array
    {
        return [
            'index' => OrganizationResource\Pages\ListOrganizations::route('/'),
            'create' => OrganizationResource\Pages\CreateOrganization::route('/create'),
            'view' => OrganizationResource\Pages\ViewOrganization::route('/{record}'),
            'edit' => OrganizationResource\Pages\EditOrganization::route('/{record}/edit'),
        ];
    }
}
