<?php

namespace App\Domain\ZatcaCompliance\Controllers\Api;

use App\Domain\ZatcaCompliance\Requests\EnrollRequest;
use App\Domain\ZatcaCompliance\Requests\InvoiceFilterRequest;
use App\Domain\ZatcaCompliance\Requests\SubmitBatchRequest;
use App\Domain\ZatcaCompliance\Requests\SubmitInvoiceRequest;
use App\Domain\ZatcaCompliance\Services\ZatcaComplianceService;
use App\Http\Controllers\Api\BaseApiController;

class ZatcaComplianceController extends BaseApiController
{
    public function __construct(private readonly ZatcaComplianceService $service) {}

    public function enroll(EnrollRequest $request)
    {
        $storeId = $request->user()->store_id;
        $result = $this->service->enroll(
            $storeId,
            $request->validated('otp'),
            $request->validated('environment'),
        );

        return $this->created($result, __('zatca.enrolled'));
    }

    public function renew()
    {
        $storeId = request()->user()->store_id;
        $result = $this->service->renewCertificate($storeId);

        return $this->success($result, __('zatca.renewed'));
    }

    public function submitInvoice(SubmitInvoiceRequest $request)
    {
        $storeId = $request->user()->store_id;
        $result = $this->service->submitInvoice($storeId, $request->validated());

        return $this->created($result, __('zatca.invoice_submitted'));
    }

    public function submitBatch(SubmitBatchRequest $request)
    {
        $storeId = $request->user()->store_id;
        $result = $this->service->submitBatch($storeId, $request->validated('invoices'));

        return $this->success($result, __('zatca.batch_submitted'));
    }

    public function invoices(InvoiceFilterRequest $request)
    {
        $storeId = $request->user()->store_id;
        $result = $this->service->listInvoices($storeId, $request->validated());

        return $this->success($result, __('zatca.invoices_retrieved'));
    }

    public function invoiceXml(string $invoiceId)
    {
        $storeId = request()->user()->store_id;
        $xml = $this->service->getInvoiceXml($storeId, $invoiceId);

        if (! $xml) {
            return $this->notFound(__('zatca.invoice_not_found'));
        }

        return response($xml, 200, ['Content-Type' => 'application/xml']);
    }

    public function complianceSummary()
    {
        $storeId = request()->user()->store_id;
        $result = $this->service->complianceSummary($storeId);

        return $this->success($result, __('zatca.summary_retrieved'));
    }

    public function vatReport(InvoiceFilterRequest $request)
    {
        $storeId = $request->user()->store_id;
        $result = $this->service->vatReport($storeId, $request->validated());

        return $this->success($result, __('zatca.vat_report_retrieved'));
    }
}
