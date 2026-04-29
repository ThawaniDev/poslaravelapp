<?php

namespace App\Filament\Resources\ZatcaCertificateResource\Pages;

use App\Domain\Core\Models\Store;
use App\Domain\ZatcaCompliance\Services\CertificateService;
use App\Domain\ZatcaCompliance\Services\ZatcaComplianceService;
use App\Filament\Resources\ZatcaCertificateResource;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListZatcaCertificates extends ListRecords
{
    protected static string $resource = ZatcaCertificateResource::class;

    /** All 6 ZATCA-required compliance invoice combinations. */
    private const COMPLIANCE_MATRIX = [
        ['label' => 'Simplified (B2C)',         'type' => 'simplified',  'is_b2b' => false],
        ['label' => 'Standard (B2B)',            'type' => 'standard',    'is_b2b' => true],
        ['label' => 'Credit Note – Simplified',  'type' => 'credit_note', 'is_b2b' => false],
        ['label' => 'Credit Note – Standard',    'type' => 'credit_note', 'is_b2b' => true],
        ['label' => 'Debit Note – Simplified',   'type' => 'debit_note',  'is_b2b' => false],
        ['label' => 'Debit Note – Standard',     'type' => 'debit_note',  'is_b2b' => true],
    ];

    protected function getHeaderActions(): array
    {
        return [
            // ── Run all 6 compliance invoice types ───────────────────────────
            Action::make('run_compliance_tests')
                ->label('Run All 6 Compliance Tests')
                ->icon('heroicon-o-rocket-launch')
                ->color('warning')
                ->modalHeading('Run All 6 ZATCA Compliance Tests')
                ->modalDescription('Submits all 6 required invoice types (Simplified, Standard, Credit Note ×2, Debit Note ×2) sequentially and shows a pass/fail report. Required before exchanging the CCSID for a real PCSID.')
                ->modalSubmitActionLabel('Run Tests Now')
                ->form([
                    Select::make('store_id')
                        ->label('Store')
                        ->options(fn () => Store::orderBy('name')->pluck('name', 'id'))
                        ->searchable()
                        ->required(),
                ])
                ->action(function (array $data) {
                    $storeId = $data['store_id'];
                    $service = app(ZatcaComplianceService::class);
                    $passed  = 0;
                    $results = [];

                    // Reference UUIDs captured from the two base invoices so that
                    // credit/debit notes can point back at them (ZATCA requires
                    // BillingReference UUID on every note).
                    $refSimplifiedUuid = null;
                    $refStandardUuid   = null;

                    // Pre-seed from the most recent accepted base invoices for
                    // this store. ZATCA tracks compliance per CCSID and refuses
                    // to re-accept a duplicate base invoice ("Submitted before"),
                    // so for repeat runs we must reuse the previously accepted
                    // UUIDs as the BillingReference for the note tests.
                    $invoiceModel = \App\Domain\ZatcaCompliance\Models\ZatcaInvoice::class;
                    $refSimplifiedUuid = $invoiceModel::query()
                        ->where('store_id', $storeId)
                        ->where('invoice_type', 'simplified')
                        ->whereIn('submission_status', ['accepted', 'reported'])
                        ->orderByDesc('submitted_at')
                        ->value('uuid');
                    $refStandardUuid = $invoiceModel::query()
                        ->where('store_id', $storeId)
                        ->where('invoice_type', 'standard')
                        ->whereIn('submission_status', ['accepted', 'reported'])
                        ->orderByDesc('submitted_at')
                        ->value('uuid');

                    foreach (self::COMPLIANCE_MATRIX as $case) {
                        $invoiceNumber = 'CTEST-' . now()->format('YmdHis') . '-' . strtoupper(substr(uniqid(), -4));

                        // Build the per-invoice payload, attaching the reference
                        // UUID + adjustment reason for note types.
                        $payload = [
                            'invoice_number'   => $invoiceNumber,
                            'invoice_type'     => $case['type'],
                            'total_amount'     => 115.00,
                            'vat_amount'       => 15.00,
                            'is_b2b'           => $case['is_b2b'],
                            'buyer_name'       => 'Compliance Test Buyer',
                            'buyer_tax_number' => $case['is_b2b'] ? '300000000000003' : null,
                        ];

                        if (in_array($case['type'], ['credit_note', 'debit_note'], true)) {
                            $payload['reference_invoice_uuid'] = $case['is_b2b']
                                ? $refStandardUuid
                                : $refSimplifiedUuid;
                            // No historical accepted base UUID? Fall back to a
                            // freshly-generated UUID. ZATCA's compliance check
                            // for note types validates structure (BR-KSA-17,
                            // billing reference present + adjustment reason),
                            // not that the referenced UUID matches a previously
                            // accepted base invoice in their system.
                            if (empty($payload['reference_invoice_uuid'])) {
                                $payload['reference_invoice_uuid'] = (string) \Illuminate\Support\Str::uuid();
                            }
                            // ZATCA BR-KSA-17: every credit AND debit note
                            // must carry a free-text reason (KSA-10).
                            $payload['adjustment_reason'] = $case['type'] === 'credit_note'
                                ? 'Compliance test credit note'
                                : 'Compliance test debit note';
                        }

                        try {
                            $result = $service->submitInvoice($storeId, $payload);

                            $status   = $result['submission_status'];
                            $ok       = in_array($status, ['accepted', 'reported'], true);
                            $invoice  = \App\Domain\ZatcaCompliance\Models\ZatcaInvoice::find($result['invoice_id']);
                            $errors   = collect($invoice?->rejection_errors ?? [])
                                ->map(fn ($e) => '[' . ($e['code'] ?? '?') . '] ' . ($e['message'] ?? json_encode($e)))
                                ->join(' | ');

                            // Capture the UUID of the base invoices so the notes
                            // produced later in the loop can reference them.
                            if ($ok && $case['type'] === 'simplified') {
                                $refSimplifiedUuid = $result['uuid'] ?? $invoice?->uuid;
                            }
                            if ($ok && $case['type'] === 'standard') {
                                $refStandardUuid = $result['uuid'] ?? $invoice?->uuid;
                            }

                            // Surface ZATCA HTTP status (e.g. 401) when no
                            // structured errors were returned — otherwise
                            // we'd just see "REJECTED" with no detail.
                            if (! $ok && $errors === '' && $invoice) {
                                $errors = 'HTTP ' . ($invoice->zatca_response_code ?? '?')
                                    . ($invoice->zatca_response_message ? ': ' . $invoice->zatca_response_message : '');
                            }

                            if ($ok) {
                                $passed++;
                            }

                            $results[] = [
                                'label'  => $case['label'],
                                'ok'     => $ok,
                                'status' => strtoupper($status),
                                'errors' => $errors,
                            ];
                        } catch (\Throwable $e) {
                            $results[] = [
                                'label'  => $case['label'],
                                'ok'     => false,
                                'status' => 'EXCEPTION',
                                'errors' => $e->getMessage(),
                            ];
                        }
                    }

                    $total = count(self::COMPLIANCE_MATRIX);
                    $lines = [];
                    foreach ($results as $r) {
                        $icon = $r['ok'] ? '✅' : '❌';
                        $line = "{$icon} {$r['label']} → {$r['status']}";
                        if (! $r['ok'] && $r['errors']) {
                            $line .= "\n      {$r['errors']}";
                        }
                        $lines[] = $line;
                    }
                    $body = implode("\n", $lines) . "\n\n{$passed}/{$total} passed";

                    Notification::make()
                        ->title($passed === $total ? 'All 6 types accepted ✅' : "{$passed}/{$total} types passed")
                        ->body($body)
                        ->color($passed === $total ? 'success' : ($passed === 0 ? 'danger' : 'warning'))
                        ->persistent()
                        ->send();

                    foreach ($results as $r) {
                        if (! $r['ok'] && $r['errors']) {
                            Notification::make()
                                ->title('❌ Failed: ' . $r['label'])
                                ->body($r['errors'])
                                ->danger()
                                ->persistent()
                                ->send();
                        }
                    }
                }),

            // ── Get Production Certificate (PCSID) ───────────────────────────
            Action::make('get_pcsid')
                ->label('Get Production Certificate (PCSID)')
                ->icon('heroicon-o-shield-check')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Exchange Compliance → Production Certificate')
                ->modalDescription('Calls ZATCA /production/csids to exchange the active compliance CCSID for a real PCSID. Only do this after all 6 compliance invoice types have been accepted for this store.')
                ->form([
                    Select::make('store_id')
                        ->label('Store')
                        ->options(fn () => Store::orderBy('name')->pluck('name', 'id'))
                        ->searchable()
                        ->required(),
                ])
                ->action(function (array $data) {
                    try {
                        $store = Store::findOrFail($data['store_id']);
                        $cert = app(CertificateService::class)->renewCertificate($store);
                        Notification::make()
                            ->title('Production certificate issued')
                            ->body('PCSID: ' . $cert->pcsid . "\nExpires: " . $cert->expires_at)
                            ->success()
                            ->persistent()
                            ->send();
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->title('PCSID issuance failed')
                            ->body($e->getMessage())
                            ->danger()
                            ->persistent()
                            ->send();
                    }
                }),
        ];
    }
}
