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
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListZatcaInvoices extends ListRecords
{
    protected static string $resource = ZatcaInvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
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

                    TextInput::make('total_amount')
                        ->label(__('zatca.total_amount') . ' (SAR)')
                        ->numeric()
                        ->default('11.50')
                        ->required()
                        ->helperText('Including VAT'),

                    TextInput::make('vat_amount')
                        ->label(__('zatca.vat_amount') . ' (SAR)')
                        ->numeric()
                        ->default('1.50')
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
                            'invoice_number' => $invoiceNumber,
                            'invoice_type' => 'simplified',
                            'total_amount' => (float) $data['total_amount'],
                            'vat_amount' => (float) $data['vat_amount'],
                            'is_b2b' => false,
                            'buyer_name' => 'Test Buyer',
                            'buyer_tax_number' => null,
                        ]);

                        AdminActivityLog::record(
                            adminUserId: auth('admin')->id(),
                            action: 'zatca_test_invoice',
                            entityType: 'store',
                            entityId: $storeId,
                            details: [
                                'invoice_number' => $invoiceNumber,
                                'invoice_id' => $result['invoice_id'],
                                'status' => $result['submission_status'],
                            ],
                        );

                        $status = $result['submission_status'];
                        $accepted = in_array($status, ['accepted', 'reported'], true);

                        Notification::make()
                            ->title($accepted ? __('zatca.test_invoice_accepted') : __('zatca.test_invoice_rejected'))
                            ->body(
                                __('zatca.invoice_number') . ': ' . $invoiceNumber . "\n" .
                                __('zatca.submission_status') . ': ' . strtoupper($status)
                            )
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

