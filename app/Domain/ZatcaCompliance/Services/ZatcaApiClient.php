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
    /** When set, overrides the global ZATCA_API_URL config for this instance. */
    private ?string $overrideUrl = null;

    public function __construct() {}

    /**
     * Return a new instance locked to a specific API base URL.
     * Use this when you have a ZatcaCertificate that knows its own environment,
     * so the global .env setting is ignored for that call.
     */
    public function forUrl(string $url): static
    {
        $clone = clone $this;
        $clone->overrideUrl = rtrim($url, '/');
        return $clone;
    }

    /**
     * Return a new instance locked to the URL stored on a certificate.
     * Falls back to the global config if the cert has no stored URL.
     */
    public function forCertificate(\App\Domain\ZatcaCompliance\Models\ZatcaCertificate $cert): static
    {
        $url = $cert->api_url ?? config('zatca.api_url');
        if ($url) {
            return $this->forUrl($url);
        }
        return $this;
    }

    private function baseUrl(): ?string
    {
        if ($this->overrideUrl !== null) {
            return $this->overrideUrl;
        }
        $url = config('zatca.api_url');
        if (! $url) {
            return null;
        }
        return rtrim($url, '/');
    }

    /**
     * Stub mode = no real HTTP calls.
     * True when the server is configured for developer-portal or sandbox (i.e.
     * a local/test environment). Simulation and production are live environments.
     *
     * The global ZATCA_ENVIRONMENT/ZATCA_API_URL config is the authoritative
     * "is this a live server" gate. Per-cert URL overrides only affect WHICH
     * endpoint to call on live servers, not whether to call at all.
     */
    private function isSandbox(): bool
    {
        $globalEnv = (string) config('zatca.environment', 'sandbox');
        $globalUrl = (string) config('zatca.api_url', '');

        // developer-portal or sandbox = stub mode (local dev / tests).
        if (in_array($globalEnv, ['sandbox', 'developer-portal'], true)
            || str_contains($globalUrl, 'developer-portal')
            || ! $globalUrl) {
            return true;
        }

        // Real environments (simulation / production) make live calls.
        return false;
    }

    /**
     * @return array{certificate_pem?:string, request_id?:string, secret?:string, error?:string}
     */
    public function requestComplianceCertificate(string $csrPem, string $otp): array
    {
        if ($this->isSandbox()) {
            return [];
        }

        try {
            $resp = Http::timeout(15)
                ->withHeaders([
                    'OTP' => $otp,
                    'Accept-Version' => 'V2',
                    'Accept-Language' => 'en',
                    'Accept' => 'application/json',
                ])
                ->post($this->baseUrl() . '/compliance', [
                    'csr' => base64_encode($csrPem),
                ]);
            if (! $resp->successful()) {
                // Dump the full CSR (PEM + decoded subject/SAN) so we can
                // diagnose ZATCA's opaque "Invalid CSR" 400s.
                $decoded = '';
                $tmp = tempnam(sys_get_temp_dir(), 'zatca_csr_dump_');
                file_put_contents($tmp, $csrPem);
                $decoded = (string) shell_exec('openssl req -in ' . escapeshellarg($tmp) . ' -noout -text 2>&1');
                @unlink($tmp);
                Log::warning('ZATCA compliance API rejected CSR', [
                    'status' => $resp->status(),
                    'body' => $resp->body(),
                    'csr_pem' => $csrPem,
                    'csr_decoded' => $decoded,
                ]);
                return ['error' => 'ZATCA ' . $resp->status() . ': ' . $resp->body()];
            }
            $body = $resp->json();
            return [
                'certificate_pem' => isset($body['binarySecurityToken'])
                    ? $this->wrapPem(base64_decode($body['binarySecurityToken'])) : null,
                'request_id' => $body['requestID'] ?? null,
                'secret'     => $body['secret'] ?? null,
            ];
        } catch (\Throwable $e) {
            Log::warning('ZATCA compliance API call failed', ['err' => $e->getMessage()]);
            return ['error' => 'Network: ' . $e->getMessage()];
        }
    }

    /**
     * Exchange a compliance CSID for a production CSID. Per ZATCA spec the
     * call is authenticated with Basic auth using the compliance certificate
     * (base64-of-DER) as the username and the compliance secret as the
     * password. Throws on any non-2xx response so the caller never silently
     * falls back to a self-signed PCSID.
     *
     * @return array{certificate_pem:string, request_id:?string, secret:?string}
     */
    public function requestProductionCertificate(
        string $complianceRequestId,
        string $complianceCertificatePem,
        string $complianceSecret
    ): array {
        if ($this->isSandbox()) {
            return [];
        }
        $resp = Http::timeout(20)
            ->withHeaders([
                'Accept-Version' => 'V2',
                'Accept-Language' => 'en',
                'Accept' => 'application/json',
                'Authorization' => $this->basicAuth($complianceCertificatePem, $complianceSecret),
            ])
            ->post($this->baseUrl() . '/production/csids', [
                'compliance_request_id' => $complianceRequestId,
            ]);

        if (! $resp->successful()) {
            Log::warning('ZATCA production CSID rejected', [
                'status' => $resp->status(),
                'body' => $resp->body(),
            ]);
            throw new \RuntimeException('ZATCA /production/csids ' . $resp->status() . ': ' . $resp->body());
        }

        $body = $resp->json() ?? [];
        if (empty($body['binarySecurityToken'])) {
            throw new \RuntimeException('ZATCA /production/csids returned no binarySecurityToken: ' . $resp->body());
        }

        return [
            'certificate_pem' => $this->wrapPem(base64_decode($body['binarySecurityToken'])),
            'request_id' => $body['requestID'] ?? null,
            'secret'     => $body['secret'] ?? null,
        ];
    }

    /**
     * Build the ZATCA Basic auth value. The ZATCA API expects:
     *   Username = binarySecurityToken = base64(PEM body) = base64(base64(DER))
     *   Password = secret (as-is)
     */
    private function basicAuth(string $certificatePem, ?string $secret): string
    {
        $pemBody = preg_replace('/-----(BEGIN|END) CERTIFICATE-----|\s+/', '', $certificatePem);
        // ZATCA Basic-auth username = binarySecurityToken = base64(PEM-body) = base64(base64(DER))
        // (the value returned verbatim by ZATCA's /compliance enrollment endpoint)
        $bst = base64_encode($pemBody);
        return 'Basic ' . base64_encode($bst . ':' . ($secret ?? ''));
    }

    /**
     * Compliance check endpoint — used during onboarding to validate that
     * the EGS can correctly produce/sign each required invoice type before
     * a Production CSID may be issued. Authenticated with the **compliance**
     * certificate + secret. Returns the same shape as clearance/reporting
     * so callers can branch transparently.
     *
     * @return array{cleared:bool, reported:bool, status_code:int, response_code?:string, message?:string, cleared_xml?:string, errors?:array}
     */
    public function submitComplianceInvoice(
        string $signedXml,
        string $invoiceHash,
        string $uuid,
        string $certificatePem,
        ?string $secret = null,
        int $clearanceStatus = 0
    ): array {
        if ($this->isSandbox()) {
            return [
                'cleared'       => $clearanceStatus === 1,
                'reported'      => $clearanceStatus === 0,
                'status_code'   => 200,
                'response_code' => $clearanceStatus === 1 ? 'CLEARED' : 'REPORTED',
                'message'       => 'sandbox_compliance',
                'cleared_xml'   => $signedXml,
                'errors'        => [],
            ];
        }
        try {
            $resp = Http::timeout(20)
                ->withHeaders([
                    'Accept-Version'   => 'V2',
                    'Accept-Language'  => 'en',
                    'Accept'           => 'application/json',
                    'Clearance-Status' => (string) $clearanceStatus,
                    'Authorization'    => $this->basicAuth($certificatePem, $secret),
                ])
                ->post($this->baseUrl() . '/compliance/invoices', [
                    'invoiceHash' => $invoiceHash,
                    'uuid' => $uuid,
                    'invoice' => base64_encode($signedXml),
                ]);

            $body = $resp->json() ?? [];
            if (! $resp->successful()) {
                Log::warning('ZATCA compliance invoice rejected', [
                    'status' => $resp->status(),
                    'body'   => $resp->body(),
                ]);
            }

            $clearance = $body['clearanceStatus'] ?? null;
            $reporting = $body['reportingStatus'] ?? null;
            $accepted = $resp->successful() && (
                $clearance === 'CLEARED' || $reporting === 'REPORTED'
            );
            $errorMessages = $body['validationResults']['errorMessages'] ?? [];
            $firstError = ! empty($errorMessages)
                ? ($errorMessages[0]['message'] ?? null)
                : null;

            return [
                'cleared' => $accepted && $clearance !== null,
                'reported' => $accepted && $reporting !== null,
                'status_code' => $resp->status(),
                'response_code' => $clearance ?? $reporting ?? (string) $resp->status(),
                'message' => $body['validationResults']['infoMessages'][0]['message'] ?? $firstError,
                'cleared_xml' => isset($body['clearedInvoice'])
                    ? base64_decode($body['clearedInvoice']) : $signedXml,
                'errors' => $errorMessages,
            ];
        } catch (\Throwable $e) {
            Log::warning('ZATCA compliance invoice call failed', ['err' => $e->getMessage()]);
            return [
                'cleared' => false,
                'reported' => false,
                'status_code' => 0,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Real-time clearance for B2B (standard) invoices.
     *
     * @return array{cleared:bool, status_code:int, response_code?:string, message?:string, cleared_xml?:string, errors?:array}
     */
    public function clearInvoice(string $signedXml, string $invoiceHash, string $uuid, string $certificatePem, ?string $secret = null): array
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
                    'Accept-Language' => 'en',
                    'Accept' => 'application/json',
                    'Clearance-Status' => '1',
                    'Authorization' => $this->basicAuth($certificatePem, $secret),
                ])
                ->post($this->baseUrl() . '/invoices/clearance/single', [
                    'invoiceHash' => $invoiceHash,
                    'uuid' => $uuid,
                    'invoice' => base64_encode($signedXml),
                ]);
            $body = $resp->json() ?? [];
            if (! $resp->successful()) {
                Log::warning('ZATCA clearance rejected', [
                    'status' => $resp->status(),
                    'body'   => $resp->body(),
                ]);
            }
            $cleared = $resp->successful() && (($body['clearanceStatus'] ?? '') === 'CLEARED');
            $errorMessages = $body['validationResults']['errorMessages'] ?? [];
            $firstError = ! empty($errorMessages)
                ? ($errorMessages[0]['message'] ?? null)
                : null;
            return [
                'cleared' => $cleared,
                'status_code' => $resp->status(),
                'response_code' => $body['clearanceStatus'] ?? (string) $resp->status(),
                'message' => $body['validationResults']['infoMessages'][0]['message'] ?? $firstError,
                'cleared_xml' => isset($body['clearedInvoice'])
                    ? base64_decode($body['clearedInvoice']) : $signedXml,
                'errors' => $errorMessages,
            ];
        } catch (\Throwable $e) {
            Log::warning('ZATCA clearance call failed', ['err' => $e->getMessage()]);
            return ['cleared' => false, 'status_code' => 0, 'message' => $e->getMessage()];
        }
    }

    /**
     * Near-real-time reporting for B2C (simplified) invoices.
     *
     * @return array{reported:bool, status_code:int, response_code?:string, message?:string, errors?:array}
     */
    public function reportInvoice(string $signedXml, string $invoiceHash, string $uuid, string $certificatePem, ?string $secret = null): array
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
                    'Accept-Language' => 'en',
                    'Accept' => 'application/json',
                    'Clearance-Status' => '0',
                    'Authorization' => $this->basicAuth($certificatePem, $secret),
                ])
                ->post($this->baseUrl() . '/invoices/reporting/single', [
                    'invoiceHash' => $invoiceHash,
                    'uuid' => $uuid,
                    'invoice' => base64_encode($signedXml),
                ]);
            $body = $resp->json() ?? [];
            if (! $resp->successful()) {
                Log::warning('ZATCA reporting rejected', [
                    'status' => $resp->status(),
                    'body'   => $resp->body(),
                ]);
            }
            $reported = $resp->successful() && (($body['reportingStatus'] ?? '') === 'REPORTED');
            $errorMessages = $body['validationResults']['errorMessages'] ?? [];
            $firstError = ! empty($errorMessages)
                ? ($errorMessages[0]['message'] ?? null)
                : null;
            return [
                'reported' => $reported,
                'status_code' => $resp->status(),
                'response_code' => $body['reportingStatus'] ?? (string) $resp->status(),
                'message' => $body['validationResults']['infoMessages'][0]['message'] ?? $firstError,
                'errors' => $errorMessages,
            ];
        } catch (\Throwable $e) {
            Log::warning('ZATCA reporting call failed', ['err' => $e->getMessage()]);
            return ['reported' => false, 'status_code' => 0, 'message' => $e->getMessage()];
        }
    }

    /**
     * Wrap a base64-encoded certificate body in PEM headers. ZATCA's
     * `binarySecurityToken` is base64(base64(DER)); decoding once gives
     * the PEM body text directly, so we don't re-encode it here.
     */
    private function wrapPem(string $body): string
    {
        $body = trim($body);
        // If the caller already gave us a fully-formed PEM, return as-is.
        if (str_contains($body, '-----BEGIN CERTIFICATE-----')) {
            return $body;
        }
        $b64 = chunk_split($body, 64, "\n");
        return "-----BEGIN CERTIFICATE-----\n" . $b64 . "-----END CERTIFICATE-----\n";
    }

    /** Strip PEM headers and decode to DER bytes. */
    private function derFromPem(string $pem): string
    {
        $pem = preg_replace('/-----BEGIN [^-]+-----/', '', $pem);
        $pem = preg_replace('/-----END [^-]+-----/', '', $pem);
        return base64_decode(preg_replace('/\s+/', '', $pem) ?? '');
    }
}
