<?php

namespace App\Filament\Resources;

use App\Domain\ProviderPayment\Enums\PaymentPurpose;
use App\Domain\ProviderPayment\Enums\ProviderPaymentStatus;
use App\Domain\ProviderPayment\Models\ProviderPayment;
use App\Domain\ProviderPayment\Services\PaymentEmailService;
use App\Domain\ProviderPayment\Services\ProviderPaymentService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ProviderPaymentResource extends Resource
{
    protected static ?string $model = ProviderPayment::class;

    protected static ?string $navigationIcon = 'heroicon-o-credit-card';

    public static function getNavigationGroup(): ?string
    {
        return __('nav.group_subscription_billing');
    }

    public static function getNavigationLabel(): string
    {
        return __('provider_payments.nav_label');
    }

    public static function getModelLabel(): string
    {
        return __('provider_payments.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('provider_payments.plural_model_label');
    }

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'cart_id';

    public static function canAccess(): bool
    {
        $user = auth('admin')->user();

        return $user && $user->hasAnyPermission(['provider_payments.view', 'subscription.view']);
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('status', ProviderPaymentStatus::Pending)->count() ?: null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make(__('provider_payments.section_payment_details'))
                ->description(__('provider_payments.section_payment_details_desc'))
                ->schema([
                    Forms\Components\Grid::make(3)->schema([
                        Forms\Components\Select::make('organization_id')
                            ->label(__('provider_payments.field_organization'))
                            ->relationship('organization', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),
                        Forms\Components\Select::make('purpose')
                            ->label(__('provider_payments.field_purpose'))
                            ->options(collect(PaymentPurpose::cases())->mapWithKeys(fn ($p) => [$p->value => $p->label()]))
                            ->required(),
                        Forms\Components\TextInput::make('purpose_label')
                            ->label(__('provider_payments.field_purpose_label'))
                            ->maxLength(200),
                    ]),
                    Forms\Components\Grid::make(4)->schema([
                        Forms\Components\TextInput::make('amount')
                            ->label(__('provider_payments.field_amount'))
                            ->numeric()
                            ->prefix('SAR')
                            ->required(),
                        Forms\Components\TextInput::make('tax_amount')
                            ->label(__('provider_payments.field_tax'))
                            ->numeric()
                            ->prefix('SAR')
                            ->default(0),
                        Forms\Components\TextInput::make('total_amount')
                            ->label(__('provider_payments.field_total'))
                            ->numeric()
                            ->prefix('SAR')
                            ->required(),
                        Forms\Components\TextInput::make('currency')
                            ->default('SAR')
                            ->maxLength(3),
                    ]),
                    Forms\Components\Grid::make(3)->schema([
                        Forms\Components\Select::make('status')
                            ->label(__('provider_payments.field_status'))
                            ->options(collect(ProviderPaymentStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()]))
                            ->required()
                            ->default('pending'),
                        Forms\Components\TextInput::make('gateway')
                            ->label(__('provider_payments.field_gateway'))
                            ->default('paytabs'),
                        Forms\Components\TextInput::make('tran_ref')
                            ->label(__('provider_payments.field_tran_ref'))
                            ->maxLength(100),
                    ]),
                ]),
            Forms\Components\Section::make(__('provider_payments.section_gateway_response'))
                ->schema([
                    Forms\Components\Grid::make(3)->schema([
                        Forms\Components\TextInput::make('response_status')
                            ->label(__('provider_payments.field_response_status'))
                            ->maxLength(5),
                        Forms\Components\TextInput::make('response_code')
                            ->label(__('provider_payments.field_response_code'))
                            ->maxLength(20),
                        Forms\Components\TextInput::make('response_message')
                            ->label(__('provider_payments.field_response_message'))
                            ->maxLength(255),
                    ]),
                    Forms\Components\Grid::make(3)->schema([
                        Forms\Components\TextInput::make('card_type')
                            ->label(__('provider_payments.field_card_type')),
                        Forms\Components\TextInput::make('card_scheme')
                            ->label(__('provider_payments.field_card_scheme')),
                        Forms\Components\TextInput::make('payment_description')
                            ->label(__('provider_payments.field_payment_description')),
                    ]),
                ])->collapsible(),
            Forms\Components\Section::make(__('provider_payments.section_tracking'))
                ->schema([
                    Forms\Components\Grid::make(3)->schema([
                        Forms\Components\Toggle::make('confirmation_email_sent')
                            ->label(__('provider_payments.field_email_sent')),
                        Forms\Components\Toggle::make('invoice_generated')
                            ->label(__('provider_payments.field_invoice_generated')),
                        Forms\Components\Toggle::make('ipn_received')
                            ->label(__('provider_payments.field_ipn_received')),
                    ]),
                    Forms\Components\Textarea::make('notes')
                        ->label(__('provider_payments.field_notes'))
                        ->rows(3),
                ])->collapsible(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('cart_id')
                    ->label(__('provider_payments.col_cart_id'))
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->fontFamily('mono')
                    ->size('sm'),
                Tables\Columns\TextColumn::make('organization.name')
                    ->label(__('provider_payments.col_organization'))
                    ->searchable()
                    ->sortable()
                    ->limit(25),
                Tables\Columns\TextColumn::make('purpose')
                    ->label(__('provider_payments.col_purpose'))
                    ->formatStateUsing(fn ($state) => $state?->label() ?? '-')
                    ->sortable(),
                Tables\Columns\TextColumn::make('purpose_label')
                    ->label(__('provider_payments.col_purpose_label'))
                    ->limit(30)
                    ->toggleable(),
                Tables\Columns\TextColumn::make('total_amount')
                    ->label(__('provider_payments.col_total'))
                    ->money('SAR')
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('original_amount')
                    ->label(__('provider_payments.col_original_amount'))
                    ->formatStateUsing(fn ($state, $record) => $record->original_currency ? number_format((float) $state, 2) . ' ' . $record->original_currency : '-')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('status')
                    ->label(__('provider_payments.col_status'))
                    ->badge()
                    ->color(fn ($state) => $state?->color() ?? 'gray')
                    ->formatStateUsing(fn ($state) => $state?->label() ?? '-')
                    ->sortable(),
                Tables\Columns\TextColumn::make('gateway')
                    ->label(__('provider_payments.col_gateway'))
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('tran_ref')
                    ->label(__('provider_payments.col_tran_ref'))
                    ->copyable()
                    ->fontFamily('mono')
                    ->size('sm')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('card_scheme')
                    ->label(__('provider_payments.col_card'))
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\IconColumn::make('confirmation_email_sent')
                    ->label(__('provider_payments.col_email'))
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger'),
                Tables\Columns\IconColumn::make('invoice_generated')
                    ->label(__('provider_payments.col_invoice'))
                    ->boolean()
                    ->trueIcon('heroicon-o-document-check')
                    ->falseIcon('heroicon-o-document')
                    ->trueColor('success')
                    ->falseColor('danger'),
                Tables\Columns\IconColumn::make('ipn_received')
                    ->label(__('provider_payments.col_ipn'))
                    ->boolean()
                    ->trueColor('success')
                    ->falseColor('warning')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('provider_payments.col_date'))
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label(__('provider_payments.filter_status'))
                    ->options(collect(ProviderPaymentStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()]))
                    ->multiple(),
                Tables\Filters\SelectFilter::make('purpose')
                    ->label(__('provider_payments.filter_purpose'))
                    ->options(collect(PaymentPurpose::cases())->mapWithKeys(fn ($p) => [$p->value => $p->label()]))
                    ->multiple(),
                Tables\Filters\TernaryFilter::make('confirmation_email_sent')
                    ->label(__('provider_payments.filter_email_sent')),
                Tables\Filters\TernaryFilter::make('invoice_generated')
                    ->label(__('provider_payments.filter_invoice_generated')),
                Tables\Filters\Filter::make('date_range')
                    ->form([
                        Forms\Components\DatePicker::make('from')->label(__('provider_payments.filter_from')),
                        Forms\Components\DatePicker::make('until')->label(__('provider_payments.filter_until')),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'], fn (Builder $q, $date) => $q->whereDate('created_at', '>=', $date))
                            ->when($data['until'], fn (Builder $q, $date) => $q->whereDate('created_at', '<=', $date));
                    }),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\EditAction::make(),
                    Tables\Actions\Action::make('resend_email')
                        ->label(__('provider_payments.action_resend_email'))
                        ->icon('heroicon-o-envelope')
                        ->color('info')
                        ->visible(fn (ProviderPayment $record) => $record->isSuccessful() && auth('admin')->user()?->hasPermission('provider_payments.manage'))
                        ->requiresConfirmation()
                        ->action(function (ProviderPayment $record) {
                            $emailService = app(PaymentEmailService::class);
                            $sent = $emailService->sendPaymentConfirmation($record);

                            if ($sent) {
                                Notification::make()->title(__('provider_payments.email_resent_success'))->success()->send();
                            } else {
                                Notification::make()->title(__('provider_payments.email_resent_failed'))->danger()->send();
                            }
                        }),
                    Tables\Actions\Action::make('query_gateway')
                        ->label(__('provider_payments.action_query_gateway'))
                        ->icon('heroicon-o-arrow-path')
                        ->color('warning')
                        ->visible(fn (ProviderPayment $record) => $record->tran_ref && auth('admin')->user()?->hasPermission('provider_payments.manage'))
                        ->requiresConfirmation()
                        ->action(function (ProviderPayment $record) {
                            try {
                                $service = app(ProviderPaymentService::class);
                                $service->syncFromGateway($record->id);
                                Notification::make()->title(__('provider_payments.gateway_query_success'))->success()->send();
                            } catch (\RuntimeException $e) {
                                Notification::make()->title($e->getMessage())->danger()->send();
                            }
                        }),
                    Tables\Actions\Action::make('process_refund')
                        ->label(__('provider_payments.action_refund'))
                        ->icon('heroicon-o-arrow-uturn-left')
                        ->color('danger')
                        ->visible(fn (ProviderPayment $record) => $record->canRefund() && auth('admin')->user()?->hasPermission('provider_payments.refund'))
                        ->requiresConfirmation()
                        ->modalDescription(__('provider_payments.refund_confirm_desc'))
                        ->form([
                            Forms\Components\TextInput::make('refund_amount')
                                ->label(__('provider_payments.field_refund_amount'))
                                ->numeric()
                                ->required()
                                ->prefix('SAR')
                                ->default(fn (ProviderPayment $record) => $record->total_amount),
                            Forms\Components\Textarea::make('refund_reason')
                                ->label(__('provider_payments.field_refund_reason'))
                                ->required(),
                        ])
                        ->action(function (ProviderPayment $record, array $data) {
                            try {
                                $service = app(ProviderPaymentService::class);
                                $service->processRefund($record->id, (float) $data['refund_amount'], $data['refund_reason']);
                                Notification::make()->title(__('provider_payments.refund_success'))->success()->send();
                            } catch (\RuntimeException $e) {
                                Notification::make()->title($e->getMessage())->danger()->send();
                            }
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
            Infolists\Components\Section::make(__('provider_payments.section_payment_details'))
                ->schema([
                    Infolists\Components\Grid::make(4)->schema([
                        Infolists\Components\TextEntry::make('cart_id')
                            ->label(__('provider_payments.col_cart_id'))
                            ->copyable()
                            ->fontFamily('mono')
                            ->weight('bold'),
                        Infolists\Components\TextEntry::make('organization.name')
                            ->label(__('provider_payments.col_organization')),
                        Infolists\Components\TextEntry::make('purpose')
                            ->label(__('provider_payments.col_purpose'))
                            ->formatStateUsing(fn ($state) => $state?->label() ?? '-'),
                        Infolists\Components\TextEntry::make('status')
                            ->label(__('provider_payments.col_status'))
                            ->badge()
                            ->color(fn ($state) => $state?->color() ?? 'gray')
                            ->formatStateUsing(fn ($state) => $state?->label() ?? '-'),
                    ]),
                    Infolists\Components\TextEntry::make('purpose_label')
                        ->label(__('provider_payments.field_purpose_label')),
                ]),
            Infolists\Components\Section::make(__('provider_payments.section_amounts'))
                ->schema([
                    Infolists\Components\Grid::make(4)->schema([
                        Infolists\Components\TextEntry::make('amount')
                            ->label(__('provider_payments.field_amount'))
                            ->money('SAR'),
                        Infolists\Components\TextEntry::make('tax_amount')
                            ->label(__('provider_payments.field_tax'))
                            ->money('SAR'),
                        Infolists\Components\TextEntry::make('total_amount')
                            ->label(__('provider_payments.field_total'))
                            ->money('SAR')
                            ->weight('bold')
                            ->size(Infolists\Components\TextEntry\TextEntrySize::Large),
                        Infolists\Components\TextEntry::make('currency')
                            ->label(__('provider_payments.field_currency')),
                    ]),
                    Infolists\Components\Grid::make(3)
                        ->schema([
                            Infolists\Components\TextEntry::make('original_amount')
                                ->label(__('provider_payments.field_original_amount'))
                                ->formatStateUsing(fn ($state, $record) => number_format((float) $state, 2) . ' ' . $record->original_currency),
                            Infolists\Components\TextEntry::make('exchange_rate_used')
                                ->label(__('provider_payments.field_exchange_rate'))
                                ->formatStateUsing(fn ($state) => '1 USD = ' . number_format((float) $state, 4) . ' SAR'),
                            Infolists\Components\TextEntry::make('amount')
                                ->label(__('provider_payments.field_converted_amount'))
                                ->formatStateUsing(fn ($state, $record) => number_format((float) $record->original_amount, 2) . ' USD × ' . number_format((float) $record->exchange_rate_used, 4) . ' = ' . number_format((float) $state, 2) . ' SAR'),
                        ])
                        ->visible(fn ($record) => $record->original_currency !== null),
                ]),
            Infolists\Components\Section::make(__('provider_payments.section_gateway_response'))
                ->schema([
                    Infolists\Components\Grid::make(3)->schema([
                        Infolists\Components\TextEntry::make('gateway')
                            ->label(__('provider_payments.field_gateway')),
                        Infolists\Components\TextEntry::make('tran_ref')
                            ->label(__('provider_payments.field_tran_ref'))
                            ->copyable()
                            ->fontFamily('mono'),
                        Infolists\Components\TextEntry::make('tran_type')
                            ->label(__('provider_payments.field_tran_type')),
                    ]),
                    Infolists\Components\Grid::make(3)->schema([
                        Infolists\Components\TextEntry::make('response_status')
                            ->label(__('provider_payments.field_response_status'))
                            ->badge()
                            ->color(fn ($state) => match ($state) {
                                'A' => 'success',
                                'D' => 'danger',
                                'E' => 'danger',
                                'H' => 'warning',
                                default => 'gray',
                            }),
                        Infolists\Components\TextEntry::make('response_code')
                            ->label(__('provider_payments.field_response_code')),
                        Infolists\Components\TextEntry::make('response_message')
                            ->label(__('provider_payments.field_response_message')),
                    ]),
                    Infolists\Components\Grid::make(3)->schema([
                        Infolists\Components\TextEntry::make('card_type')
                            ->label(__('provider_payments.field_card_type'))
                            ->placeholder('-'),
                        Infolists\Components\TextEntry::make('card_scheme')
                            ->label(__('provider_payments.field_card_scheme'))
                            ->placeholder('-'),
                        Infolists\Components\TextEntry::make('payment_description')
                            ->label(__('provider_payments.field_payment_description'))
                            ->placeholder('-'),
                    ]),
                ])->collapsible(),
            Infolists\Components\Section::make(__('provider_payments.section_tracking'))
                ->schema([
                    Infolists\Components\Grid::make(3)->schema([
                        Infolists\Components\IconEntry::make('confirmation_email_sent')
                            ->label(__('provider_payments.field_email_sent'))
                            ->boolean()
                            ->trueColor('success')
                            ->falseColor('danger'),
                        Infolists\Components\IconEntry::make('invoice_generated')
                            ->label(__('provider_payments.field_invoice_generated'))
                            ->boolean()
                            ->trueColor('success')
                            ->falseColor('danger'),
                        Infolists\Components\IconEntry::make('ipn_received')
                            ->label(__('provider_payments.field_ipn_received'))
                            ->boolean()
                            ->trueColor('success')
                            ->falseColor('warning'),
                    ]),
                    Infolists\Components\Grid::make(3)->schema([
                        Infolists\Components\TextEntry::make('confirmation_email_sent_at')
                            ->label(__('provider_payments.field_email_sent_at'))
                            ->dateTime()
                            ->placeholder('-'),
                        Infolists\Components\TextEntry::make('invoice_generated_at')
                            ->label(__('provider_payments.field_invoice_generated_at'))
                            ->dateTime()
                            ->placeholder('-'),
                        Infolists\Components\TextEntry::make('ipn_received_at')
                            ->label(__('provider_payments.field_ipn_received_at'))
                            ->dateTime()
                            ->placeholder('-'),
                    ]),
                    Infolists\Components\TextEntry::make('confirmation_email_error')
                        ->label(__('provider_payments.field_email_error'))
                        ->placeholder(__('provider_payments.no_error'))
                        ->color('danger')
                        ->visible(fn ($record) => $record->confirmation_email_error !== null),
                ])->collapsible(),
            Infolists\Components\Section::make(__('provider_payments.section_refund'))
                ->schema([
                    Infolists\Components\Grid::make(3)->schema([
                        Infolists\Components\TextEntry::make('refund_amount')
                            ->label(__('provider_payments.field_refund_amount'))
                            ->money('SAR')
                            ->placeholder('-'),
                        Infolists\Components\TextEntry::make('refund_tran_ref')
                            ->label(__('provider_payments.field_refund_tran_ref'))
                            ->copyable()
                            ->fontFamily('mono')
                            ->placeholder('-'),
                        Infolists\Components\TextEntry::make('refunded_at')
                            ->label(__('provider_payments.field_refunded_at'))
                            ->dateTime()
                            ->placeholder('-'),
                    ]),
                    Infolists\Components\TextEntry::make('refund_reason')
                        ->label(__('provider_payments.field_refund_reason'))
                        ->placeholder('-'),
                ])
                ->visible(fn ($record) => $record->refund_amount !== null)
                ->collapsible(),
            Infolists\Components\Section::make(__('provider_payments.section_email_logs'))
                ->schema([
                    Infolists\Components\RepeatableEntry::make('emailLogs')
                        ->label('')
                        ->schema([
                            Infolists\Components\TextEntry::make('email_type')
                                ->label(__('provider_payments.email_log_type'))
                                ->formatStateUsing(fn ($state) => $state?->value ?? '-'),
                            Infolists\Components\TextEntry::make('recipient_email')
                                ->label(__('provider_payments.email_log_recipient')),
                            Infolists\Components\TextEntry::make('subject')
                                ->label(__('provider_payments.email_log_subject'))
                                ->limit(50),
                            Infolists\Components\TextEntry::make('status')
                                ->label(__('provider_payments.email_log_status'))
                                ->badge()
                                ->color(fn ($state) => match ($state) {
                                    'sent' => 'success',
                                    'pending' => 'warning',
                                    'failed' => 'danger',
                                    default => 'gray',
                                }),
                            Infolists\Components\TextEntry::make('created_at')
                                ->label(__('provider_payments.email_log_date'))
                                ->dateTime(),
                        ])
                        ->columns(5),
                ])->collapsible(),
            Infolists\Components\Section::make(__('provider_payments.section_notes'))
                ->schema([
                    Infolists\Components\TextEntry::make('notes')
                        ->label('')
                        ->placeholder(__('provider_payments.no_notes')),
                ])
                ->visible(fn ($record) => $record->notes !== null)
                ->collapsible(),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ProviderPaymentResource\Pages\ListProviderPayments::route('/'),
            'create' => ProviderPaymentResource\Pages\CreateProviderPayment::route('/create'),
            'view' => ProviderPaymentResource\Pages\ViewProviderPayment::route('/{record}'),
            'edit' => ProviderPaymentResource\Pages\EditProviderPayment::route('/{record}/edit'),
        ];
    }
}
