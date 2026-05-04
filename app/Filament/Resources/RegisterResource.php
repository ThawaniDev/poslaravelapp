<?php

namespace App\Filament\Resources;

use App\Domain\Core\Enums\RegisterPlatform;
use App\Domain\Core\Models\Register;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class RegisterResource extends Resource
{
    protected static ?string $model = Register::class;

    protected static ?string $navigationIcon = 'heroicon-o-device-tablet';

    protected static ?string $navigationGroup = null;

    public static function getNavigationGroup(): ?string
    {
        return __('nav.group_core');
    }

    protected static ?string $navigationLabel = null;

    public static function getNavigationLabel(): string
    {
        return __('nav.terminals');
    }

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'name';

    public static function canAccess(): bool
    {
        $user = auth('admin')->user();

        return $user && $user->hasAnyPermission(['terminals.view', 'terminals.edit', 'terminals.create']);
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'device_id', 'serial_number', 'nearpay_tid'];
    }

    // ─── Form ────────────────────────────────────────────────────

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Tabs::make('TerminalTabs')
                ->tabs([
                    // ── Tab 1: Basic Info ─────────────────────────
                    Forms\Components\Tabs\Tab::make(__('Basic Info'))
                        ->icon('heroicon-o-device-tablet')
                        ->schema([
                            Forms\Components\Section::make(__('Terminal Identity'))
                                ->description(__('Core terminal information'))
                                ->schema([
                                    Forms\Components\Select::make('store_id')
                                        ->label(__('Store'))
                                        ->relationship('store', 'name')
                                        ->searchable()
                                        ->preload()
                                        ->required(),
                                    Forms\Components\TextInput::make('name')
                                        ->label(__('terminals.terminal') . ' ' . __('Name'))
                                        ->required()
                                        ->maxLength(255),
                                    Forms\Components\TextInput::make('device_id')
                                        ->label(__('Device ID'))
                                        ->maxLength(255),
                                    Forms\Components\Select::make('platform')
                                        ->options(RegisterPlatform::class)
                                        ->native(false)
                                        ->searchable(),
                                    Forms\Components\TextInput::make('app_version')
                                        ->label(__('App Version'))
                                        ->maxLength(50)
                                        ->disabled(),
                                    Forms\Components\Toggle::make('is_active')
                                        ->label(__('Active'))
                                        ->default(true),
                                ])
                                ->columns(2),

                            Forms\Components\Section::make(__('terminals.device_model'))
                                ->description(__('Hardware details'))
                                ->schema([
                                    Forms\Components\TextInput::make('device_model')
                                        ->label(__('terminals.device_model'))
                                        ->maxLength(255),
                                    Forms\Components\TextInput::make('os_version')
                                        ->label(__('terminals.os_version'))
                                        ->maxLength(50),
                                    Forms\Components\TextInput::make('serial_number')
                                        ->label(__('terminals.serial_number'))
                                        ->maxLength(255),
                                    Forms\Components\Toggle::make('nfc_capable')
                                        ->label(__('terminals.nfc_capable'))
                                        ->default(false),
                                ])
                                ->columns(2),
                        ]),

                    // ── Tab 2: SoftPOS Settings ───────────────────
                    Forms\Components\Tabs\Tab::make(__('terminals.softpos_settings'))
                        ->icon('heroicon-o-credit-card')
                        ->schema([
                            Forms\Components\Section::make(__('terminals.softpos'))
                                ->description(__('NearPay SoftPOS configuration'))
                                ->schema([
                                    Forms\Components\Toggle::make('softpos_enabled')
                                        ->label(__('terminals.softpos'))
                                        ->helperText(__('Enable SoftPOS tap-to-pay on this terminal'))
                                        ->reactive(),
                                    Forms\Components\Select::make('softpos_status')
                                        ->label(__('terminals.softpos_status'))
                                        ->options([
                                            'pending'     => __('terminals.status_pending'),
                                            'active'      => __('terminals.status_active'),
                                            'suspended'   => __('terminals.status_suspended'),
                                            'deactivated' => __('terminals.status_deactivated'),
                                        ])
                                        ->native(false),
                                    Forms\Components\TextInput::make('nearpay_tid')
                                        ->label(__('terminals.nearpay_tid'))
                                        ->maxLength(255),
                                    Forms\Components\TextInput::make('nearpay_mid')
                                        ->label(__('terminals.nearpay_mid'))
                                        ->maxLength(255),
                                ])
                                ->columns(2),

                            Forms\Components\Section::make(__('terminals.acquirer_source'))
                                ->description(__('Payment acquirer configuration'))
                                ->schema([
                                    Forms\Components\Select::make('acquirer_source')
                                        ->label(__('terminals.acquirer_source'))
                                        ->options([
                                            'HALA'     => __('terminals.acquirer_hala'),
                                            'Al Rajhi' => __('terminals.acquirer_bank_rajhi'),
                                            'SNB'      => __('terminals.acquirer_bank_snb'),
                                            'Geidea'   => __('terminals.acquirer_geidea'),
                                            'Other'    => __('terminals.acquirer_other'),
                                        ])
                                        ->native(false)
                                        ->searchable(),
                                    Forms\Components\TextInput::make('acquirer_name')
                                        ->label(__('terminals.acquirer_name'))
                                        ->maxLength(255),
                                    Forms\Components\TextInput::make('acquirer_reference')
                                        ->label(__('terminals.acquirer_reference'))
                                        ->maxLength(255),
                                ])
                                ->columns(3),
                        ]),

                    // ── Tab 3: Fees & Settlement ──────────────────
                    Forms\Components\Tabs\Tab::make(__('Fees & Settlement'))
                        ->icon('heroicon-o-banknotes')
                        ->schema([
                            Forms\Components\Section::make(__('terminals.fee_profile'))
                                ->description(__('Transaction fee configuration'))
                                ->schema([
                                    Forms\Components\Select::make('fee_profile')
                                        ->label(__('terminals.fee_profile'))
                                        ->options([
                                            'standard'    => __('terminals.fee_standard'),
                                            'custom'      => __('terminals.fee_custom'),
                                            'promotional' => __('terminals.fee_promotional'),
                                        ])
                                        ->native(false),
                                    Forms\Components\TextInput::make('fee_mada_percentage')
                                        ->label(__('terminals.fee_mada'))
                                        ->numeric()
                                        ->step(0.0001)
                                        ->suffix('%')
                                        ->helperText(__('e.g. 0.0150 = 1.50%')),
                                    Forms\Components\TextInput::make('fee_visa_mc_percentage')
                                        ->label(__('terminals.fee_visa_mc'))
                                        ->numeric()
                                        ->step(0.0001)
                                        ->suffix('%'),
                                    Forms\Components\TextInput::make('fee_flat_per_txn')
                                        ->label(__('terminals.fee_flat'))
                                        ->helperText(__('terminals.fee_flat_helper'))
                                        ->numeric()
                                        ->step(0.01)
                                        ->suffix('SAR'),
                                    Forms\Components\TextInput::make('wameed_margin_percentage')
                                        ->label(__('terminals.wameed_margin'))
                                        ->numeric()
                                        ->step(0.0001)
                                        ->suffix('%'),
                                ])
                                ->columns(2),

                            Forms\Components\Section::make(__('SoftPOS Bilateral Billing'))
                                ->description(__('Per-terminal bilateral fee rates. Merchant rate ≥ Gateway rate (platform margin = difference).'))
                                ->icon('heroicon-o-credit-card')
                                ->schema([
                                    Forms\Components\TextInput::make('softpos_mada_merchant_rate')
                                        ->label(__('Mada – Merchant Rate'))
                                        ->helperText(__('Charged to merchant. e.g. 0.006 = 0.6%'))
                                        ->numeric()->step(0.000001)->suffix('%'),
                                    Forms\Components\TextInput::make('softpos_mada_gateway_rate')
                                        ->label(__('Mada – Gateway Rate'))
                                        ->helperText(__('Paid to gateway. Must be ≤ merchant rate.'))
                                        ->numeric()->step(0.000001)->suffix('%'),
                                    Forms\Components\TextInput::make('softpos_card_merchant_fee')
                                        ->label(__('Visa/MC/Amex – Merchant Fee'))
                                        ->helperText(__('Fixed SAR per transaction charged to merchant.'))
                                        ->numeric()->step(0.001)->suffix('SAR'),
                                    Forms\Components\TextInput::make('softpos_card_gateway_fee')
                                        ->label(__('Visa/MC/Amex – Gateway Fee'))
                                        ->helperText(__('Fixed SAR per transaction paid to gateway.'))
                                        ->numeric()->step(0.001)->suffix('SAR'),
                                ])
                                ->columns(2)
                                ->visible(fn ($record) => $record?->softpos_enabled),

                            Forms\Components\Section::make(__('Settlement'))
                                ->description(__('Payment settlement configuration'))
                                ->schema([
                                    Forms\Components\Select::make('settlement_cycle')
                                        ->label(__('terminals.settlement_cycle'))
                                        ->options([
                                            'T+1' => 'T+1 (Next business day)',
                                            'T+2' => 'T+2 (Two business days)',
                                            'T+3' => 'T+3 (Three business days)',
                                            'weekly' => 'Weekly',
                                        ])
                                        ->native(false),
                                    Forms\Components\TextInput::make('settlement_bank_name')
                                        ->label(__('terminals.settlement_bank'))
                                        ->maxLength(255),
                                    Forms\Components\TextInput::make('settlement_iban')
                                        ->label(__('terminals.settlement_iban'))
                                        ->maxLength(34),
                                ])
                                ->columns(3),
                        ]),

                    // ── Tab 4: Notes ──────────────────────────────
                    Forms\Components\Tabs\Tab::make(__('Notes'))
                        ->icon('heroicon-o-document-text')
                        ->schema([
                            Forms\Components\Section::make(__('terminals.admin_notes'))
                                ->schema([
                                    Forms\Components\Textarea::make('admin_notes')
                                        ->label(__('terminals.admin_notes'))
                                        ->rows(5)
                                        ->columnSpanFull(),
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
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('store.name')
                    ->label(__('Store'))
                    ->sortable()
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('device_id')
                    ->label(__('Device ID'))
                    ->searchable()
                    ->toggleable()
                    ->limit(20),
                Tables\Columns\TextColumn::make('platform')
                    ->badge()
                    ->color(fn ($state) => match ($state?->value ?? $state) {
                        'android' => 'success',
                        'ios'     => 'info',
                        'windows' => 'primary',
                        'macos'   => 'gray',
                        default   => 'gray',
                    })
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean()
                    ->label(__('Active'))
                    ->sortable(),
                Tables\Columns\IconColumn::make('softpos_enabled')
                    ->boolean()
                    ->label(__('terminals.softpos'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('softpos_status')
                    ->label(__('terminals.softpos_status'))
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'active'      => 'success',
                        'pending'     => 'warning',
                        'suspended'   => 'danger',
                        'deactivated' => 'gray',
                        default       => 'gray',
                    })
                    ->toggleable(),
                Tables\Columns\TextColumn::make('acquirer_source')
                    ->label(__('terminals.acquirer_source'))
                    ->badge()
                    ->color('gray')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('fee_profile')
                    ->label(__('terminals.fee_profile'))
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'standard'    => 'gray',
                        'custom'      => 'info',
                        'promotional' => 'success',
                        default       => 'gray',
                    })
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\IconColumn::make('is_online')
                    ->boolean()
                    ->label(__('Online'))
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('last_sync_at')
                    ->label(__('Last Sync'))
                    ->dateTime('M j, Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('last_transaction_at')
                    ->label(__('terminals.last_transaction_at'))
                    ->dateTime('M j, Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime('M j, Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label(__('Active')),
                Tables\Filters\TernaryFilter::make('softpos_enabled')
                    ->label(__('terminals.softpos')),
                Tables\Filters\SelectFilter::make('softpos_status')
                    ->options([
                        'pending'     => __('terminals.status_pending'),
                        'active'      => __('terminals.status_active'),
                        'suspended'   => __('terminals.status_suspended'),
                        'deactivated' => __('terminals.status_deactivated'),
                    ]),
                Tables\Filters\SelectFilter::make('platform')
                    ->options(RegisterPlatform::class),
                Tables\Filters\SelectFilter::make('store_id')
                    ->relationship('store', 'name')
                    ->searchable()
                    ->preload()
                    ->label(__('Store')),
                Tables\Filters\SelectFilter::make('acquirer_source')
                    ->options([
                        'HALA'     => __('terminals.acquirer_hala'),
                        'Al Rajhi' => __('terminals.acquirer_bank_rajhi'),
                        'SNB'      => __('terminals.acquirer_bank_snb'),
                        'Geidea'   => __('terminals.acquirer_geidea'),
                        'Other'    => __('terminals.acquirer_other'),
                    ]),
                Tables\Filters\SelectFilter::make('fee_profile')
                    ->options([
                        'standard'    => __('terminals.fee_standard'),
                        'custom'      => __('terminals.fee_custom'),
                        'promotional' => __('terminals.fee_promotional'),
                    ]),
                Tables\Filters\TernaryFilter::make('is_online')
                    ->label(__('Online')),
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
                    Tables\Actions\Action::make('view_sessions')
                        ->label(__('terminals.view_sessions'))
                        ->icon('heroicon-o-clock')
                        ->color('info')
                        ->visible(fn () => auth('admin')->user()?->hasPermission('pos_sessions.view'))
                        ->url(fn (Register $record) => PosSessionResource::getUrl('index', [
                            'tableFilters' => ['register_id' => ['value' => $record->id]],
                        ])),
                    Tables\Actions\Action::make('view_transactions')
                        ->label(__('terminals.view_transactions'))
                        ->icon('heroicon-o-receipt-percent')
                        ->color('info')
                        ->visible(fn () => auth('admin')->user()?->hasPermission('transactions.view'))
                        ->url(fn (Register $record) => TransactionResource::getUrl('index', [
                            'tableFilters' => ['register_id' => ['value' => $record->id]],
                        ])),
                    Tables\Actions\Action::make('clone_terminal')
                        ->label(__('terminals.clone_terminal'))
                        ->icon('heroicon-o-document-duplicate')
                        ->color('gray')
                        ->visible(fn () => auth('admin')->user()?->hasPermission('terminals.create'))
                        ->form([
                            Forms\Components\TextInput::make('name')
                                ->label(__('Name'))
                                ->required()
                                ->maxLength(255),
                            Forms\Components\TextInput::make('device_id')
                                ->label(__('Device ID'))
                                ->maxLength(255)
                                ->helperText(__('terminals.clone_device_id_hint')),
                        ])
                        ->action(function (Register $record, array $data) {
                            $copy = $record->replicate([
                                'last_sync_at', 'last_transaction_at', 'is_online',
                                'softpos_activated_at', 'created_at', 'updated_at',
                            ]);
                            $copy->name = $data['name'];
                            $copy->device_id = $data['device_id'] ?? null;
                            $copy->softpos_status = 'pending';
                            $copy->save();
                            Notification::make()->title(__('terminals.cloned'))->success()->send();
                        }),
                    Tables\Actions\Action::make('toggle_status')
                        ->label(fn (Register $record) => $record->is_active ? __('Deactivate') : __('Activate'))
                        ->icon(fn (Register $record) => $record->is_active ? 'heroicon-o-no-symbol' : 'heroicon-o-check-circle')
                        ->color(fn (Register $record) => $record->is_active ? 'danger' : 'success')
                        ->requiresConfirmation()
                        ->visible(fn () => auth('admin')->user()?->hasPermission('terminals.edit'))
                        ->action(function (Register $record) {
                            $record->update(['is_active' => ! $record->is_active]);
                            Notification::make()
                                ->title($record->is_active ? __('terminals.activated') : __('terminals.deactivated'))
                                ->success()->send();
                        }),
                    Tables\Actions\Action::make('activate_softpos')
                        ->label(__('terminals.softpos_activated'))
                        ->icon('heroicon-o-bolt')
                        ->color('success')
                        ->requiresConfirmation()
                        ->visible(fn (Register $record) => $record->softpos_enabled && $record->softpos_status !== 'active'
                            && $record->nearpay_tid && $record->acquirer_source
                            && auth('admin')->user()?->hasPermission('terminals.edit'))
                        ->action(function (Register $record) {
                            $record->update([
                                'softpos_status'       => 'active',
                                'softpos_activated_at' => now(),
                            ]);
                            Notification::make()->title(__('terminals.softpos_activated'))->success()->send();
                        }),
                    Tables\Actions\Action::make('suspend_softpos')
                        ->label(__('terminals.softpos_suspended'))
                        ->icon('heroicon-o-pause-circle')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->visible(fn (Register $record) => $record->softpos_status === 'active'
                            && auth('admin')->user()?->hasPermission('terminals.edit'))
                        ->action(function (Register $record) {
                            $record->update(['softpos_status' => 'suspended']);
                            Notification::make()->title(__('terminals.softpos_suspended'))->warning()->send();
                        }),
                    Tables\Actions\Action::make('deactivate_softpos')
                        ->label(__('terminals.softpos_deactivated'))
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalDescription(__('This will fully deactivate SoftPOS on this terminal.'))
                        ->visible(fn (Register $record) => in_array($record->softpos_status, ['active', 'suspended'])
                            && auth('admin')->user()?->hasPermission('terminals.edit'))
                        ->action(function (Register $record) {
                            $record->update(['softpos_status' => 'deactivated']);
                            Notification::make()->title(__('terminals.softpos_deactivated'))->danger()->send();
                        }),
                    Tables\Actions\DeleteAction::make()
                        ->visible(fn () => auth('admin')->user()?->hasPermission('terminals.delete')),
                ]),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('bulk_activate')
                        ->label(__('Activate Selected'))
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->deselectRecordsAfterCompletion()
                        ->visible(fn () => auth('admin')->user()?->hasPermission('terminals.edit'))
                        ->action(fn ($records) => $records->each(fn ($r) => $r->update(['is_active' => true]))),
                    Tables\Actions\BulkAction::make('bulk_deactivate')
                        ->label(__('Deactivate Selected'))
                        ->icon('heroicon-o-no-symbol')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->deselectRecordsAfterCompletion()
                        ->visible(fn () => auth('admin')->user()?->hasPermission('terminals.edit'))
                        ->action(fn ($records) => $records->each(fn ($r) => $r->update(['is_active' => false]))),
                    Tables\Actions\BulkAction::make('bulk_toggle_softpos')
                        ->label(__('terminals.bulk_toggle_softpos'))
                        ->icon('heroicon-o-bolt')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->modalDescription(__('terminals.bulk_toggle_softpos_warning'))
                        ->deselectRecordsAfterCompletion()
                        ->visible(fn () => auth('admin')->user()?->hasPermission('terminals.edit'))
                        ->action(fn ($records) => $records->each(fn ($r) => $r->update([
                            'softpos_enabled' => ! $r->softpos_enabled,
                        ]))),
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn () => auth('admin')->user()?->hasPermission('terminals.delete')),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    // ─── Infolist (View Page) ────────────────────────────────────

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Tabs::make('TerminalTabs')
                ->tabs([
                    Infolists\Components\Tabs\Tab::make(__('Overview'))
                        ->icon('heroicon-o-device-tablet')
                        ->schema([
                            Infolists\Components\Section::make(__('Terminal Identity'))
                                ->schema([
                                    Infolists\Components\TextEntry::make('name')->weight('bold'),
                                    Infolists\Components\TextEntry::make('store.name')->label(__('Store')),
                                    Infolists\Components\TextEntry::make('device_id')->label(__('Device ID'))->copyable()->placeholder(__('N/A')),
                                    Infolists\Components\TextEntry::make('platform')
                                        ->badge()
                                        ->color(fn ($state) => match ($state?->value ?? $state) {
                                            'android' => 'success', 'ios' => 'info',
                                            'windows' => 'primary', 'macos' => 'gray',
                                            default => 'gray',
                                        }),
                                    Infolists\Components\TextEntry::make('app_version')->placeholder(__('N/A')),
                                    Infolists\Components\IconEntry::make('is_active')->boolean()->label(__('Active')),
                                    Infolists\Components\IconEntry::make('is_online')->boolean()->label(__('Online')),
                                    Infolists\Components\TextEntry::make('last_sync_at')->dateTime()->placeholder(__('Never')),
                                ])
                                ->columns(4),

                            Infolists\Components\Section::make(__('Device Hardware'))
                                ->schema([
                                    Infolists\Components\TextEntry::make('device_model')->label(__('terminals.device_model'))->placeholder(__('N/A')),
                                    Infolists\Components\TextEntry::make('os_version')->label(__('terminals.os_version'))->placeholder(__('N/A')),
                                    Infolists\Components\TextEntry::make('serial_number')->label(__('terminals.serial_number'))->copyable()->placeholder(__('N/A')),
                                    Infolists\Components\IconEntry::make('nfc_capable')->boolean()->label(__('terminals.nfc_capable')),
                                ])
                                ->columns(4),
                        ]),

                    Infolists\Components\Tabs\Tab::make(__('terminals.softpos_settings'))
                        ->icon('heroicon-o-credit-card')
                        ->schema([
                            Infolists\Components\Section::make(__('terminals.softpos'))
                                ->schema([
                                    Infolists\Components\IconEntry::make('softpos_enabled')->boolean()->label(__('terminals.softpos')),
                                    Infolists\Components\TextEntry::make('softpos_status')
                                        ->label(__('terminals.softpos_status'))
                                        ->badge()
                                        ->color(fn ($state) => match ($state) {
                                            'active' => 'success', 'pending' => 'warning',
                                            'suspended' => 'danger', 'deactivated' => 'gray',
                                            default => 'gray',
                                        }),
                                    Infolists\Components\TextEntry::make('nearpay_tid')->label(__('terminals.nearpay_tid'))->copyable()->placeholder(__('N/A')),
                                    Infolists\Components\TextEntry::make('nearpay_mid')->label(__('terminals.nearpay_mid'))->copyable()->placeholder(__('N/A')),
                                    Infolists\Components\TextEntry::make('softpos_activated_at')->label(__('terminals.softpos_activated_at'))->dateTime()->placeholder(__('Not activated')),
                                    Infolists\Components\TextEntry::make('last_transaction_at')->label(__('terminals.last_transaction_at'))->dateTime()->placeholder(__('N/A')),
                                ])
                                ->columns(3),

                            Infolists\Components\Section::make(__('Acquirer'))
                                ->schema([
                                    Infolists\Components\TextEntry::make('acquirer_source')->label(__('terminals.acquirer_source'))->placeholder(__('N/A')),
                                    Infolists\Components\TextEntry::make('acquirer_name')->label(__('terminals.acquirer_name'))->placeholder(__('N/A')),
                                    Infolists\Components\TextEntry::make('acquirer_reference')->label(__('terminals.acquirer_reference'))->copyable()->placeholder(__('N/A')),
                                ])
                                ->columns(3),
                        ]),

                    Infolists\Components\Tabs\Tab::make(__('Fees & Settlement'))
                        ->icon('heroicon-o-banknotes')
                        ->schema([
                            Infolists\Components\Section::make(__('Fee Configuration'))
                                ->schema([
                                    Infolists\Components\TextEntry::make('fee_profile')
                                        ->label(__('terminals.fee_profile'))
                                        ->badge()
                                        ->color(fn ($state) => match ($state) {
                                            'standard' => 'gray', 'custom' => 'info',
                                            'promotional' => 'success', default => 'gray',
                                        }),
                                    Infolists\Components\TextEntry::make('fee_mada_percentage')
                                        ->label(__('terminals.fee_mada'))
                                        ->formatStateUsing(fn ($state) => $state ? number_format((float) $state * 100, 2) . '%' : 'N/A'),
                                    Infolists\Components\TextEntry::make('fee_visa_mc_percentage')
                                        ->label(__('terminals.fee_visa_mc'))
                                        ->formatStateUsing(fn ($state) => $state ? number_format((float) $state * 100, 2) . '%' : 'N/A'),
                                    Infolists\Components\TextEntry::make('fee_flat_per_txn')
                                        ->label(__('terminals.fee_flat'))
                                        ->formatStateUsing(fn ($state) => $state ? $state . ' SAR' : 'N/A'),
                                    Infolists\Components\TextEntry::make('wameed_margin_percentage')
                                        ->label(__('terminals.wameed_margin'))
                                        ->formatStateUsing(fn ($state) => $state ? number_format((float) $state * 100, 2) . '%' : 'N/A'),
                                ])
                                ->columns(3),

                            Infolists\Components\Section::make(__('SoftPOS Bilateral Billing'))
                                ->description(__('Per-terminal bilateral fee rates. Margin = merchant rate − gateway rate.'))
                                ->icon('heroicon-o-credit-card')
                                ->schema([
                                    Infolists\Components\TextEntry::make('softpos_mada_merchant_rate')
                                        ->label(__('Mada Merchant Rate'))
                                        ->formatStateUsing(fn ($state) => number_format((float) $state * 100, 4) . '%'),
                                    Infolists\Components\TextEntry::make('softpos_mada_gateway_rate')
                                        ->label(__('Mada Gateway Rate'))
                                        ->formatStateUsing(fn ($state) => number_format((float) $state * 100, 4) . '%'),
                                    Infolists\Components\TextEntry::make('softpos_mada_merchant_rate')
                                        ->label(__('Mada Margin'))
                                        ->formatStateUsing(fn ($state, $record) =>
                                            number_format(((float) $record->softpos_mada_merchant_rate - (float) $record->softpos_mada_gateway_rate) * 100, 4) . '%'
                                        )
                                        ->badge()->color('success'),
                                    Infolists\Components\TextEntry::make('softpos_card_merchant_fee')
                                        ->label(__('Card Merchant Fee'))
                                        ->formatStateUsing(fn ($state) => number_format((float) $state, 3) . ' SAR'),
                                    Infolists\Components\TextEntry::make('softpos_card_gateway_fee')
                                        ->label(__('Card Gateway Fee'))
                                        ->formatStateUsing(fn ($state) => number_format((float) $state, 3) . ' SAR'),
                                    Infolists\Components\TextEntry::make('softpos_card_merchant_fee')
                                        ->label(__('Card Margin'))
                                        ->formatStateUsing(fn ($state, $record) =>
                                            number_format((float) $record->softpos_card_merchant_fee - (float) $record->softpos_card_gateway_fee, 3) . ' SAR'
                                        )
                                        ->badge()->color('success'),
                                ])
                                ->columns(3)
                                ->visible(fn ($record) => $record?->softpos_enabled),

                            Infolists\Components\Section::make(__('Settlement'))
                                ->schema([
                                    Infolists\Components\TextEntry::make('settlement_cycle')->label(__('terminals.settlement_cycle'))->placeholder(__('N/A')),
                                    Infolists\Components\TextEntry::make('settlement_bank_name')->label(__('terminals.settlement_bank'))->placeholder(__('N/A')),
                                    Infolists\Components\TextEntry::make('settlement_iban')->label(__('terminals.settlement_iban'))->copyable()->placeholder(__('N/A')),
                                ])
                                ->columns(3),
                        ]),

                    Infolists\Components\Tabs\Tab::make(__('Notes'))
                        ->icon('heroicon-o-document-text')
                        ->schema([
                            Infolists\Components\Section::make(__('terminals.admin_notes'))
                                ->schema([
                                    Infolists\Components\TextEntry::make('admin_notes')
                                        ->label(__('terminals.admin_notes'))
                                        ->placeholder(__('No notes'))
                                        ->columnSpanFull(),
                                    Infolists\Components\TextEntry::make('created_at')->dateTime(),
                                    Infolists\Components\TextEntry::make('updated_at')->dateTime(),
                                ])
                                ->columns(2),
                        ]),
                ])
                ->columnSpanFull(),
        ]);
    }

    // ─── Relations ───────────────────────────────────────────────

    public static function getRelations(): array
    {
        return [];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['store']);
    }

    // ─── Pages ───────────────────────────────────────────────────

    public static function getPages(): array
    {
        return [
            'index'  => RegisterResource\Pages\ListRegisters::route('/'),
            'create' => RegisterResource\Pages\CreateRegister::route('/create'),
            'view'   => RegisterResource\Pages\ViewRegister::route('/{record}'),
            'edit'   => RegisterResource\Pages\EditRegister::route('/{record}/edit'),
        ];
    }
}
