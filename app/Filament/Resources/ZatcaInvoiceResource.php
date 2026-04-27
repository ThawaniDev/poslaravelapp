<?php

namespace App\Filament\Resources;

use App\Domain\AdminPanel\Models\AdminActivityLog;
use App\Domain\ZatcaCompliance\Enums\ZatcaSubmissionStatus;
use App\Domain\ZatcaCompliance\Models\ZatcaInvoice;
use App\Domain\ZatcaCompliance\Services\ZatcaComplianceService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ZatcaInvoiceResource extends Resource
{
    protected static ?string $model = ZatcaInvoice::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?int $navigationSort = 15;

    public static function getNavigationGroup(): ?string
    {
        return __('nav.group_zatca');
    }

    public static function getNavigationLabel(): string
    {
        return __('zatca.invoices');
    }

    public static function getModelLabel(): string
    {
        return __('zatca.invoice');
    }

    public static function getPluralModelLabel(): string
    {
        return __('zatca.invoices');
    }

    public static function canAccess(): bool
    {
        $user = auth('admin')->user();
        return $user && $user->hasAnyPermission(['settings.credentials']);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make(__('zatca.invoice_info'))->schema([
                Forms\Components\TextInput::make('invoice_number')->label(__('zatca.invoice_number'))->disabled(),
                Forms\Components\TextInput::make('invoice_type')->label(__('zatca.invoice_type'))->disabled(),
                Forms\Components\TextInput::make('uuid')->label('UUID')->disabled(),
                Forms\Components\TextInput::make('icv')->label('ICV')->disabled()->numeric(),
                Forms\Components\TextInput::make('total_amount')->label(__('zatca.total_amount'))->disabled()->numeric(),
                Forms\Components\TextInput::make('vat_amount')->label(__('zatca.vat_amount'))->disabled()->numeric(),
                Forms\Components\TextInput::make('submission_status')->label(__('zatca.submission_status'))->disabled(),
                Forms\Components\DateTimePicker::make('submitted_at')->label(__('zatca.submitted_at'))->disabled(),
                Forms\Components\TextInput::make('zatca_response_code')->label(__('zatca.response_code'))->disabled(),
                Forms\Components\Textarea::make('zatca_response_message')->label(__('zatca.response_message'))->rows(3)->disabled()->columnSpanFull(),
                Forms\Components\Toggle::make('is_b2b')->label(__('zatca.is_b2b'))->disabled(),
                Forms\Components\TextInput::make('buyer_name')->label(__('zatca.buyer_name'))->disabled(),
                Forms\Components\TextInput::make('buyer_tax_number')->label(__('zatca.buyer_tax_number'))->disabled(),
            ])->columns(2),

            Forms\Components\Section::make(__('zatca.invoice_xml'))
                ->collapsible()
                ->collapsed()
                ->schema([
                    Forms\Components\Textarea::make('invoice_xml')->label('XML')->rows(20)->disabled()->columnSpanFull(),
                    Forms\Components\Textarea::make('invoice_hash')->label(__('zatca.invoice_hash'))->rows(2)->disabled(),
                    Forms\Components\Textarea::make('previous_invoice_hash')->label(__('zatca.previous_hash'))->rows(2)->disabled(),
                ]),

            Forms\Components\Section::make(__('zatca.qr_code'))
                ->schema([
                    Forms\Components\View::make('filament.components.zatca-qr-code'),
                ])
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('store.name')->label(__('zatca.store'))->searchable()->sortable(),
                Tables\Columns\TextColumn::make('invoice_number')->label(__('zatca.invoice_number'))->searchable()->copyable(),
                Tables\Columns\BadgeColumn::make('is_test_badge')
                    ->label('')
                    ->getStateUsing(fn (ZatcaInvoice $record) => str_starts_with($record->invoice_number, 'TEST-') ? __('zatca.test_invoice_badge') : null)
                    ->color('warning')
                    ->toggleable(isToggledHiddenByDefault: false),
                Tables\Columns\TextColumn::make('invoice_type')->label(__('zatca.invoice_type'))->badge()->toggleable(),
                Tables\Columns\TextColumn::make('icv')->label('ICV')->numeric()->sortable()->toggleable(),
                Tables\Columns\TextColumn::make('total_amount')->label(__('zatca.total_amount'))->numeric(2)->sortable(),
                Tables\Columns\TextColumn::make('vat_amount')->label(__('zatca.vat_amount'))->numeric(2)->toggleable(),
                Tables\Columns\TextColumn::make('submission_status')
                    ->label(__('zatca.submission_status'))
                    ->badge()
                    ->color(fn ($state) => match ($state?->value ?? $state) {
                        'accepted', 'reported' => 'success',
                        'submitted' => 'info',
                        'pending' => 'warning',
                        'warning' => 'warning',
                        'rejected' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn ($state) => __('zatca.sub_status_' . ($state?->value ?? $state))),
                Tables\Columns\IconColumn::make('is_b2b')->label('B2B')->boolean()->toggleable(),
                Tables\Columns\TextColumn::make('submitted_at')->label(__('zatca.submitted_at'))->dateTime('Y-m-d H:i')->sortable(),
                Tables\Columns\TextColumn::make('created_at')->label(__('zatca.created_at'))->dateTime('Y-m-d')->toggleable(isToggledHiddenByDefault: true)->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('submission_status')
                    ->label(__('zatca.submission_status'))
                    ->options(collect(ZatcaSubmissionStatus::cases())->mapWithKeys(fn ($c) => [$c->value => __('zatca.sub_status_' . $c->value)])),
                Tables\Filters\SelectFilter::make('invoice_type')
                    ->label(__('zatca.invoice_type'))
                    ->options([
                        'standard' => __('zatca.type_standard'),
                        'simplified' => __('zatca.type_simplified'),
                    ]),
                Tables\Filters\TernaryFilter::make('is_b2b')->label('B2B'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('view_xml')
                    ->label(__('zatca.view_xml'))
                    ->icon('heroicon-o-code-bracket')
                    ->color('info')
                    ->modalHeading(fn (ZatcaInvoice $record) => $record->invoice_number)
                    ->modalContent(fn (ZatcaInvoice $record) => view('filament.modals.zatca-xml', ['xml' => $record->invoice_xml]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel(__('zatca.close')),
                Tables\Actions\Action::make('download_xml')
                    ->label(__('zatca.download_xml'))
                    ->icon('heroicon-o-arrow-down-tray')
                    ->action(function (ZatcaInvoice $record) {
                        return response()->streamDownload(
                            fn () => print($record->invoice_xml ?? ''),
                            "zatca-invoice-{$record->invoice_number}.xml",
                            ['Content-Type' => 'application/xml']
                        );
                    }),
                Tables\Actions\Action::make('retry')
                    ->label(__('zatca.retry'))
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->visible(fn (ZatcaInvoice $record) => in_array(($record->submission_status?->value ?? $record->submission_status), ['rejected', 'pending', 'warning'], true))
                    ->action(function (ZatcaInvoice $record) {
                        try {
                            app(ZatcaComplianceService::class)->retrySubmission($record->store_id, $record->id);
                            AdminActivityLog::record(
                                adminUserId: auth('admin')->id(),
                                action: 'retry_zatca_invoice',
                                entityType: 'zatca_invoice',
                                entityId: $record->id,
                            );
                            Notification::make()->title(__('zatca.retry_dispatched'))->success()->send();
                        } catch (\Throwable $e) {
                            Notification::make()->title(__('zatca.action_failed'))->body($e->getMessage())->danger()->send();
                        }
                    }),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ZatcaInvoiceResource\Pages\ListZatcaInvoices::route('/'),
            'view' => ZatcaInvoiceResource\Pages\ViewZatcaInvoice::route('/{record}'),
        ];
    }
}
