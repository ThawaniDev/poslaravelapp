<?php

namespace App\Domain\ZatcaCompliance\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Thin HTTP wrapper around the ZATCA Fatoora APIs.
 *
 * In sandbox mode (the default for tests + tenants without a configured
 * production endpoint) every method short-circuits and returns an empty
 * payload so the caller falls back to local self-signing / acceptance.
 *
 * In production we POST the canonical ZATCA payloads with the right
 * `OTP` / `Clearance-Status` headers and parse the standard response
 * envelope.
 */
class ZatcaApiClient
{
    public function __construct() {}

    private function baseUrl(): ?string
    {
        $url = config('zatca.api_url');
        if (! $url || str_contains($url, 'developer-portal')) {
            return null; // treat developer-portal placeholder as sandbox
        }
        return rtrim($url, '/');
    }

    private function isSandbox(): bool
    {
        return $this->baseUrl() === null
            || in_array(config('zatca.environment'), ['sandbox', 'simulation'], true);
    }

    /**
     * @return array{certificate_pem?:string, request_id?:string}
     */
    public function requestComplianceCertificate(string $csrPem, string $otp): array
    {
        if ($this->isSandbox()) {
            return [];
        }

        try {
            $resp = Http::timeout(15)
                ->withHeaders(['OTP' => $otp, 'Accept-Version' => 'V2'])
                ->post($this->baseUrl() . '/compliance', [
                    'csr' => base64_encode($csrPem),
                ]);
            if (! $resp->successful()) {
                Log::warning('ZATCA compliance API rejected CSR', ['status' => $resp->status(), 'body' => $resp->body()]);
                return [];
            }
            $body = $resp->json();
            return [
                'certificate_pem' => isset($body['binarySecurityToken'])
                    ? $this->wrapPem(base64_decode($body['binarySecurityToken'])) : null,
                'request_id' => $body['requestID'] ?? null,
            ];
        } catch (\Throwable $e) {
            Log::warning('ZATCA compliance API call failed', ['err' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * @return array{certificate_pem?:string, request_id?:string}
     */
    public function requestProductionCertificate(string $csrPem, string $complianceRequestId): array
    {
        if ($this->isSandbox()) {
            return [];
        }
        try {
            $resp = Http::timeout(15)
                ->withHeaders(['Accept-Version' => 'V2'])
                ->post($this->baseUrl() . '/production/csids', [
                    'compliance_request_id' => $complianceRequestId,
                ]);
            if (! $resp->successful()) {
                return [];
            }
            $body = $resp->json();
            return [
                'certificate_pem' => isset($body['binarySecurityToken'])
                    ? $this->wrapPem(base64_decode($body['binarySecurityToken'])) : null,
                'request_id' => $body['requestID'] ?? null,
            ];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Real-time clearance for B2B (standard) invoices.
     *
     * @return array{cleared:bool, status_code:int, response_code?:string, message?:string, cleared_xml?:string, errors?:array}
     */
    public function clearInvoice(string $signedXml, string $invoiceHash, string $uuid, string $certificatePem): array
    {
        if ($this->isSandbox()) {
            return [
                'cleared' => true,
                'status_code' => 200,
                'response_code' => 'CLEARED',
                'message' => 'sandbox_cleared',
                'cleared_xml' => $signedXml,
            ];
        }
        try {
            $resp = Http::timeout(15)
                ->withHeaders([
                    'Accept-Version' => 'V2',
                    'Clearance-Status' => '1',
                ])
                ->post($this->baseUrl() . '/invoices/clearance/single', [
                    'invoiceHash' => $invoiceHash,
                    'uuid' => $uuid,
                    'invoice' => base64_encode($signedXml),
                ]);
            $body = $resp->json() ?? [];
            $cleared = $resp->successful() && (($body['clearanceStatus'] ?? '') === 'CLEARED');
            return [
                'cleared' => $cleared,
                'status_code' => $resp->status(),
                'response_code' => $body['clearanceStatus'] ?? (string) $resp->status(),
                'message' => $body['validationResults']['infoMessages'][0]['message'] ?? null,
                'cleared_xml' => isset($body['clearedInvoice'])
                    ? base64_decode($body['clearedInvoice']) : $signedXml,
                'errors' => $body['validationResults']['errorMessages'] ?? [],
            ];
        } catch (\Throwable $e) {
            return ['cleared' => false, 'status_code' => 0, 'message' => $e->getMessage()];
        }
    }

    /**
     * Near-real-time reporting for B2C (simplified) invoices.
     *
     * @return array{reported:bool, status_code:int, response_code?:string, message?:string, errors?:array}
     */
    public function reportInvoice(string $signedXml, string $invoiceHash, string $uuid, string $certificatePem): array
    {
        if ($this->isSandbox()) {
            return [
                'reported' => true,
                'status_code' => 200,
                'response_code' => 'REPORTED',
                'message' => 'sandbox_reported',
            ];
        }
        try {
            $resp = Http::timeout(15)
                ->withHeaders([
                    'Accept-Version' => 'V2',
                    'Clearance-Status' => '0',
                ])
                ->post($this->baseUrl() . '/invoices/reporting/single', [
                    'invoiceHash' => $invoiceHash,
                    'uuid' => $uuid,
                    'invoice' => base64_encode($signedXml),
                ]);
            $body = $resp->json() ?? [];
            $reported = $resp->successful() && (($body['reportingStatus'] ?? '') === 'REPORTED');
            return [
                'reported' => $reported,
                'status_code' => $resp->status(),
                'response_code' => $body['reportingStatus'] ?? (string) $resp->status(),
                'message' => $body['validationResults']['infoMessages'][0]['message'] ?? null,
                'errors' => $body['validationResults']['errorMessages'] ?? [],
            ];
        } catch (\Throwable $e) {
            return ['reported' => false, 'status_code' => 0, 'message' => $e->getMessage()];
        }
    }

    private function wrapPem(string $der): string
    {
        $b64 = chunk_split(base64_encode($der), 64, "\n");
        return "-----BEGIN CERTIFICATE-----\n" . $b64 . "-----END CERTIFICATE-----\n";
    }
}
