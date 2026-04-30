<?php

namespace App\Filament\Resources\ZatcaInvoiceResource\Pages;

use App\Domain\AdminPanel\Models\AdminActivityLog;
use App\Domain\Core\Models\Store;
use App\Domain\ZatcaCompliance\Services\ZatcaComplianceService;
use App\Filament\Resources\ZatcaInvoiceResource;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListZatcaInvoices extends ListRecords
{
    protected static string $resource = ZatcaInvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // ── Single manual test invoice ────────────────────────────────────
            Action::make('send_test_invoice')
                ->label(__('zatca.send_test_invoice'))
                ->icon('heroicon-o-beaker')
                ->color('info')
                ->form([
                    Select::make('store_id')
                        ->label(__('zatca.store'))
                        ->options(fn () => Store::orderBy('name')->pluck('name', 'id'))
                        ->searchable()
                        ->required()
                        ->helperText(__('zatca.test_invoice_store_help')),

                    Select::make('invoice_type')
                        ->label(__('zatca.invoice_type'))
                        ->options([
                            'simplified'  => 'Simplified (B2C)',
                            'standard'    => 'Standard (B2B)',
                            'credit_note' => 'Credit Note',
                            'debit_note'  => 'Debit Note',
                        ])
                        ->default('simplified')
                        ->required(),

                    Toggle::make('is_b2b')
                        ->label(__('zatca.b2b_credit_debit_label'))
                        ->default(false)
                        ->helperText(__('zatca.b2b_subtype_help')),

                    TextInput::make('total_amount')
                        ->label(__('zatca.total_amount') . ' (SAR)')
                        ->numeric()
                        ->default('115.00')
                        ->required()
                        ->helperText(__('zatca.including_vat')),

                    TextInput::make('vat_amount')
                        ->label(__('zatca.vat_amount') . ' (SAR)')
                        ->numeric()
                        ->default('15.00')
                        ->required(),

                    Placeholder::make('notice')
                        ->content(__('zatca.test_invoice_notice'))
                        ->columnSpanFull(),
                ])
                ->modalHeading(__('zatca.send_test_invoice'))
                ->modalDescription(__('zatca.test_invoice_description'))
                ->modalSubmitActionLabel(__('zatca.submit_test_invoice'))
                ->action(function (array $data) {
                    $storeId = $data['store_id'];
                    $invoiceNumber = 'TEST-' . now()->format('YmdHis') . '-' . strtoupper(substr(uniqid(), -4));

                    try {
                        $result = app(ZatcaComplianceService::class)->submitInvoice($storeId, [
                            'invoice_number'   => $invoiceNumber,
                            'invoice_type'     => $data['invoice_type'],
                            'total_amount'     => (float) $data['total_amount'],
                            'vat_amount'       => (float) $data['vat_amount'],
                            'is_b2b'           => ($data['invoice_type'] === 'standard') || (bool) ($data['is_b2b'] ?? false),
                            'buyer_name'       => 'Test Buyer',
                            'buyer_tax_number' => ($data['invoice_type'] === 'standard' || ($data['is_b2b'] ?? false))
                                ? '300000000000003'
                                : null,
                        ]);

                        AdminActivityLog::record(
                            adminUserId: auth('admin')->id(),
                            action: 'zatca_test_invoice',
                            entityType: 'store',
                            entityId: $storeId,
                            details: [
                                'invoice_number' => $invoiceNumber,
                                'invoice_id'     => $result['invoice_id'],
                                'status'         => $result['submission_status'],
                            ],
                        );

                        $status   = $result['submission_status'];
                        $accepted = in_array($status, ['accepted', 'reported'], true);

                        $invoice    = \App\Domain\ZatcaCompliance\Models\ZatcaInvoice::find($result['invoice_id']);
                        $errorLines = collect($invoice?->rejection_errors ?? [])
                            ->map(fn ($e) => '[' . ($e['code'] ?? '?') . '] ' . ($e['message'] ?? json_encode($e)))
                            ->join("\n");

                        $body = __('zatca.invoice_number') . ': ' . $invoiceNumber . "\n"
                            . __('zatca.submission_status') . ': ' . strtoupper($status);
                        if (! $accepted && $errorLines) {
                            $body .= "\n\n" . $errorLines;
                        }

                        Notification::make()
                            ->title($accepted ? __('zatca.test_invoice_accepted') : __('zatca.test_invoice_rejected'))
                            ->body($body)
                            ->{$accepted ? 'success' : 'danger'}()
                            ->persistent()
                            ->send();
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->title(__('zatca.test_invoice_failed'))
                            ->body($e->getMessage())
                            ->danger()
                            ->persistent()
                            ->send();
                    }
                }),
        ];
    }
}

