<?php

namespace App\Domain\ZatcaCompliance\Controllers\Api;

use App\Domain\ZatcaCompliance\Models\ZatcaDevice;
use App\Domain\ZatcaCompliance\Requests\EnrollRequest;
use App\Domain\ZatcaCompliance\Requests\InvoiceFilterRequest;
use App\Domain\ZatcaCompliance\Requests\SubmitBatchRequest;
use App\Domain\ZatcaCompliance\Requests\SubmitInvoiceRequest;
use App\Domain\ZatcaCompliance\Services\DeviceService;
use App\Domain\ZatcaCompliance\Services\HashChainService;
use App\Domain\ZatcaCompliance\Services\ZatcaComplianceService;
use App\Http\Controllers\Api\BaseApiController;
use Illuminate\Http\Request;

class ZatcaComplianceController extends BaseApiController
{
    public function __construct(
        private readonly ZatcaComplianceService $service,
        private readonly DeviceService $devices,
        private readonly HashChainService $chain,
    ) {}

    public function enroll(EnrollRequest $request)
    {
        $storeId = $this->resolvedStoreId($request) ?? $request->user()->store_id;
        $result = $this->service->enroll(
            $storeId,
            $request->validated('otp'),
            $request->validated('environment'),
        );

        return $this->created($result, __('zatca.enrolled'));
    }

    public function renew(Request $request)
    {
        $storeId = $this->resolvedStoreId($request) ?? $request->user()->store_id;
        $result = $this->service->renewCertificate($storeId);

        return $this->success($result, __('zatca.renewed'));
    }

    public function submitInvoice(SubmitInvoiceRequest $request)
    {
        $storeId = $this->resolvedStoreId($request) ?? $request->user()->store_id;
        $result = $this->service->submitInvoice($storeId, $request->validated());

        return $this->created($result, __('zatca.invoice_submitted'));
    }

    public function submitBatch(SubmitBatchRequest $request)
    {
        $storeId = $this->resolvedStoreId($request) ?? $request->user()->store_id;
        $result = $this->service->submitBatch($storeId, $request->validated('invoices'));

        return $this->success($result, __('zatca.batch_submitted'));
    }

    public function invoices(InvoiceFilterRequest $request)
    {
        $storeId = $this->resolvedStoreId($request) ?? $request->user()->store_id;
        $result = $this->service->listInvoices($storeId, $request->validated());

        return $this->success($result, __('zatca.invoices_retrieved'));
    }

    public function invoiceXml(Request $request, string $invoiceId)
    {
        $storeId = $this->resolvedStoreId($request) ?? $request->user()->store_id;
        $xml = $this->service->getInvoiceXml($storeId, $invoiceId);

        if (! $xml) {
            return $this->notFound(__('zatca.invoice_not_found'));
        }

        return response($xml, 200, ['Content-Type' => 'application/xml']);
    }

    public function complianceSummary(Request $request)
    {
        $storeId = $this->resolvedStoreId($request) ?? $request->user()->store_id;
        $result = $this->service->complianceSummary($storeId);

        return $this->success($result, __('zatca.summary_retrieved'));
    }

    public function vatReport(InvoiceFilterRequest $request)
    {
        $storeId = $this->resolvedStoreId($request) ?? $request->user()->store_id;
        $result = $this->service->vatReport($storeId, $request->validated());

        return $this->success($result, __('zatca.vat_report_retrieved'));
    }

    // ─── Devices ───────────────────────────────────────────

    public function listDevices(Request $request)
    {
        $storeId = $this->resolvedStoreId($request) ?? $request->user()->store_id;
        $devices = ZatcaDevice::where('store_id', $storeId)
            ->orderByDesc('created_at')
            ->get();
        return $this->success(['devices' => $devices], __('zatca.devices_retrieved'));
    }

    public function provisionDevice(Request $request)
    {
        $storeId = $this->resolvedStoreId($request) ?? $request->user()->store_id;
        $env = (string) $request->input('environment', 'sandbox');
        $device = $this->devices->provision($storeId, $env);
        return $this->created([
            'device_id' => $device->id,
            'device_uuid' => $device->device_uuid,
            'activation_code' => $device->activation_code,
            'status' => $device->status->value,
            'environment' => $device->environment,
        ], __('zatca.device_provisioned'));
    }

    public function activateDevice(Request $request)
    {
        $storeId = $this->resolvedStoreId($request) ?? $request->user()->store_id;
        $data = $request->validate([
            'activation_code' => ['required', 'string', 'max:32'],
            'hardware_serial' => ['nullable', 'string', 'max:128'],
        ]);
        try {
            $device = $this->devices->activate($storeId, $data['activation_code'], $data['hardware_serial'] ?? null);
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }
        return $this->success([
            'device_id' => $device->id,
            'device_uuid' => $device->device_uuid,
            'status' => $device->status->value,
            'activated_at' => $device->activated_at?->toIso8601String(),
        ], __('zatca.device_activated'));
    }

    public function resetDeviceTamper(Request $request, string $deviceId)
    {
        $storeId = $this->resolvedStoreId($request) ?? $request->user()->store_id;
        $device = ZatcaDevice::where('store_id', $storeId)->where('id', $deviceId)->first();
        if (! $device) {
            return $this->notFound(__('zatca.device_not_found'));
        }
        $device = $this->devices->resetTamper($device);
        return $this->success([
            'device_id' => $device->id,
            'status' => $device->status->value,
            'is_tampered' => false,
        ], __('zatca.device_tamper_reset'));
    }

    public function verifyChain(Request $request, string $deviceId)
    {
        $storeId = $this->resolvedStoreId($request) ?? $request->user()->store_id;
        $device = ZatcaDevice::where('store_id', $storeId)->where('id', $deviceId)->first();
        if (! $device) {
            return $this->notFound(__('zatca.device_not_found'));
        }
        $offending = $this->chain->verifyChain($device->id);
        return $this->success([
            'device_id' => $device->id,
            'chain_intact' => $offending === null,
            'broken_at_invoice_id' => $offending?->id,
            'current_icv' => (int) $device->current_icv,
        ], __('zatca.chain_verified'));
    }

    public function dashboard(Request $request)
    {
        $storeId = $this->resolvedStoreId($request) ?? $request->user()->store_id;
        $summary = $this->service->complianceSummary($storeId);
        $recent = $this->service->listInvoices($storeId, ['per_page' => 10]);
        return $this->success([
            'summary' => $summary,
            'recent_invoices' => $recent['data'],
        ], __('zatca.dashboard_retrieved'));
    }

    public function invoiceDetail(Request $request, string $invoiceId)
    {
        $storeId = $this->resolvedStoreId($request) ?? $request->user()->store_id;
        $detail = $this->service->getInvoiceFull($storeId, $invoiceId);
        if (! $detail) {
            return $this->notFound(__('zatca.invoice_not_found'));
        }
        return $this->success($detail, __('zatca.invoice_retrieved'));
    }

    public function retrySubmission(Request $request, string $invoiceId)
    {
        $storeId = $this->resolvedStoreId($request) ?? $request->user()->store_id;
        $result = $this->service->retrySubmission($storeId, $invoiceId);
        if ($result === null) {
            return $this->notFound(__('zatca.invoice_not_found'));
        }
        return $this->success($result, __('zatca.invoice_retry_attempted'));
    }

    public function connectionStatus(Request $request)
    {
        $storeId = $this->resolvedStoreId($request) ?? $request->user()->store_id;
        $status = $this->service->connectionStatus($storeId);
        return $this->success($status, __('zatca.connection_retrieved'));
    }

    public function adminOverview(Request $request)
    {
        $user = $request->user();
        $storeIds = null;
        if (! $user->hasRole(['super-admin', 'platform-admin'])) {
            // Restrict to stores within the caller's organization.
            $storeIds = \App\Domain\Core\Models\Store::query()
                ->when(
                    $user->organization_id ?? null,
                    fn ($q, $orgId) => $q->where('organization_id', $orgId),
                )
                ->pluck('id')
                ->all();
        }
        $overview = $this->service->adminOverview($storeIds);
        return $this->success($overview, __('zatca.admin_overview_retrieved'));
    }
}
