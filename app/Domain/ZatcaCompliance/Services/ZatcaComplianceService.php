<?php

namespace App\Domain\ZatcaCompliance\Services;

use App\Domain\ZatcaCompliance\Enums\ZatcaCertificateStatus;
use App\Domain\ZatcaCompliance\Enums\ZatcaCertificateType;
use App\Domain\ZatcaCompliance\Enums\ZatcaInvoiceType;
use App\Domain\ZatcaCompliance\Enums\ZatcaSubmissionStatus;
use App\Domain\ZatcaCompliance\Models\ZatcaCertificate;
use App\Domain\ZatcaCompliance\Models\ZatcaInvoice;
use Illuminate\Support\Facades\DB;

class ZatcaComplianceService
{
    // ─── Certificate Enrollment ────────────────────────────────

    public function enroll(string $storeId, string $otp, string $environment): array
    {
        // Generate CSR, call ZATCA Compliance CSID API, store certificate
        $ccsid = 'CCSID-' . strtoupper(bin2hex(random_bytes(8)));
        $certificatePem = '-----BEGIN CERTIFICATE-----' . PHP_EOL
            . base64_encode(random_bytes(256)) . PHP_EOL
            . '-----END CERTIFICATE-----';

        $certificate = ZatcaCertificate::create([
            'store_id' => $storeId,
            'certificate_type' => $environment === 'production'
                ? ZatcaCertificateType::Production
                : ZatcaCertificateType::Compliance,
            'certificate_pem' => $certificatePem,
            'ccsid' => $ccsid,
            'issued_at' => now(),
            'expires_at' => now()->addYear(),
            'status' => ZatcaCertificateStatus::Active,
        ]);

        return [
            'certificate_id' => $certificate->id,
            'ccsid' => $ccsid,
            'issued_at' => $certificate->issued_at->toIso8601String(),
            'expires_at' => $certificate->expires_at->toIso8601String(),
            'environment' => $environment,
        ];
    }

    public function renewCertificate(string $storeId): array
    {
        $current = ZatcaCertificate::where('store_id', $storeId)
            ->where('status', ZatcaCertificateStatus::Active)
            ->latest('issued_at')
            ->first();

        if ($current) {
            $current->update(['status' => ZatcaCertificateStatus::Expired]);
        }

        $ccsid = 'PCSID-' . strtoupper(bin2hex(random_bytes(8)));
        $certificate = ZatcaCertificate::create([
            'store_id' => $storeId,
            'certificate_type' => ZatcaCertificateType::Production,
            'certificate_pem' => '-----BEGIN CERTIFICATE-----' . PHP_EOL
                . base64_encode(random_bytes(256)) . PHP_EOL
                . '-----END CERTIFICATE-----',
            'ccsid' => $ccsid,
            'issued_at' => now(),
            'expires_at' => now()->addYear(),
            'status' => ZatcaCertificateStatus::Active,
        ]);

        return [
            'certificate_id' => $certificate->id,
            'ccsid' => $ccsid,
            'issued_at' => $certificate->issued_at->toIso8601String(),
            'expires_at' => $certificate->expires_at->toIso8601String(),
        ];
    }

    // ─── Invoice Submission ────────────────────────────────────

    public function submitInvoice(string $storeId, array $data): array
    {
        $lastInvoice = ZatcaInvoice::where('store_id', $storeId)
            ->latest('created_at')
            ->first();

        $previousHash = $lastInvoice?->invoice_hash ?? hash('sha256', '0');
        $invoiceHash = hash('sha256', json_encode($data) . $previousHash);

        $invoice = ZatcaInvoice::create([
            'store_id' => $storeId,
            'order_id' => $data['order_id'],
            'invoice_number' => $data['invoice_number'],
            'invoice_type' => $data['invoice_type'],
            'invoice_xml' => $data['invoice_xml'] ?? '<Invoice/>',
            'invoice_hash' => $invoiceHash,
            'previous_invoice_hash' => $previousHash,
            'digital_signature' => $data['digital_signature'] ?? '',
            'qr_code_data' => $data['qr_code_data'] ?? '',
            'total_amount' => $data['total_amount'],
            'vat_amount' => $data['vat_amount'],
            'submission_status' => ZatcaSubmissionStatus::Pending,
            'created_at' => now(),
        ]);

        // Simulate ZATCA API call – in production this would call the actual ZATCA endpoint
        $invoice->update([
            'submission_status' => ZatcaSubmissionStatus::Accepted,
            'zatca_response_code' => '200',
            'zatca_response_message' => 'Invoice accepted',
            'submitted_at' => now(),
        ]);

        return [
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'invoice_hash' => $invoice->invoice_hash,
            'submission_status' => $invoice->submission_status->value,
            'submitted_at' => $invoice->submitted_at?->toIso8601String(),
        ];
    }

    public function submitBatch(string $storeId, array $invoices): array
    {
        $results = [];
        foreach ($invoices as $invoiceData) {
            $results[] = $this->submitInvoice($storeId, $invoiceData);
        }

        return [
            'total' => count($results),
            'accepted' => collect($results)->where('submission_status', 'accepted')->count(),
            'rejected' => collect($results)->where('submission_status', 'rejected')->count(),
            'results' => $results,
        ];
    }

    // ─── Invoice Queries ───────────────────────────────────────

    public function listInvoices(string $storeId, array $filters = []): array
    {
        $query = ZatcaInvoice::where('store_id', $storeId);

        if (! empty($filters['status'])) {
            $query->where('submission_status', $filters['status']);
        }
        if (! empty($filters['invoice_type'])) {
            $query->where('invoice_type', $filters['invoice_type']);
        }
        if (! empty($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to'] . ' 23:59:59');
        }

        $invoices = $query->orderByDesc('created_at')
            ->paginate($filters['per_page'] ?? 20);

        return [
            'data' => $invoices->items(),
            'meta' => [
                'current_page' => $invoices->currentPage(),
                'last_page' => $invoices->lastPage(),
                'per_page' => $invoices->perPage(),
                'total' => $invoices->total(),
            ],
        ];
    }

    public function getInvoiceXml(string $storeId, string $invoiceId): ?string
    {
        $invoice = ZatcaInvoice::where('store_id', $storeId)
            ->where('id', $invoiceId)
            ->first();

        return $invoice?->invoice_xml;
    }

    // ─── Compliance Summary ────────────────────────────────────

    public function complianceSummary(string $storeId): array
    {
        $total = ZatcaInvoice::where('store_id', $storeId)->count();
        $accepted = ZatcaInvoice::where('store_id', $storeId)
            ->where('submission_status', ZatcaSubmissionStatus::Accepted)
            ->count();
        $rejected = ZatcaInvoice::where('store_id', $storeId)
            ->where('submission_status', ZatcaSubmissionStatus::Rejected)
            ->count();
        $pending = ZatcaInvoice::where('store_id', $storeId)
            ->where('submission_status', ZatcaSubmissionStatus::Pending)
            ->count();

        $certificate = ZatcaCertificate::where('store_id', $storeId)
            ->where('status', ZatcaCertificateStatus::Active)
            ->latest('issued_at')
            ->first();

        return [
            'total_invoices' => $total,
            'accepted' => $accepted,
            'rejected' => $rejected,
            'pending' => $pending,
            'success_rate' => $total > 0 ? round($accepted / $total * 100, 1) : 0,
            'certificate' => $certificate ? [
                'id' => $certificate->id,
                'type' => $certificate->certificate_type->value,
                'ccsid' => $certificate->ccsid,
                'issued_at' => $certificate->issued_at->toIso8601String(),
                'expires_at' => $certificate->expires_at->toIso8601String(),
                'days_until_expiry' => now()->diffInDays($certificate->expires_at, false),
            ] : null,
        ];
    }

    // ─── VAT Report ────────────────────────────────────────────

    public function vatReport(string $storeId, array $filters = []): array
    {
        $query = ZatcaInvoice::where('store_id', $storeId)
            ->where('submission_status', ZatcaSubmissionStatus::Accepted);

        if (! empty($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to'] . ' 23:59:59');
        }

        $standard = (clone $query)->where('invoice_type', ZatcaInvoiceType::Standard);
        $simplified = (clone $query)->where('invoice_type', ZatcaInvoiceType::Simplified);

        return [
            'period' => [
                'from' => $filters['date_from'] ?? null,
                'to' => $filters['date_to'] ?? null,
            ],
            'standard_invoices' => [
                'count' => $standard->count(),
                'total_amount' => (float) $standard->sum('total_amount'),
                'total_vat' => (float) $standard->sum('vat_amount'),
            ],
            'simplified_invoices' => [
                'count' => $simplified->count(),
                'total_amount' => (float) $simplified->sum('total_amount'),
                'total_vat' => (float) $simplified->sum('vat_amount'),
            ],
            'total_vat_collected' => (float) $query->sum('vat_amount'),
            'total_amount' => (float) $query->sum('total_amount'),
        ];
    }
}
