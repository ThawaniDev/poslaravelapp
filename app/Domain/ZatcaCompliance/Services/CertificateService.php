<?php

namespace App\Domain\ZatcaCompliance\Services;

use App\Domain\Core\Models\Store;
use App\Domain\ZatcaCompliance\Enums\ZatcaCertificateStatus;
use App\Domain\ZatcaCompliance\Enums\ZatcaCertificateType;
use App\Domain\ZatcaCompliance\Models\ZatcaCertificate;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

/**
 * Generates the EGS keypair, builds a ZATCA-shaped CSR and performs the
 * 2-step CCSID/PCSID handshake against the ZATCA API.
 *
 * In sandbox mode (no real ZATCA endpoint configured) we self-sign the
 * CSR using the same key and return a synthetic CCSID/PCSID — this keeps
 * the cryptographic chain real (the resulting cert really verifies the
 * signed invoices) so downstream tests and Flutter previews are accurate.
 *
 * Private key PEM is encrypted-at-rest in the certificates table via
 * Laravel encryption. It never leaves the backend boundary.
 */
class CertificateService
{
    public function __construct(private readonly ZatcaApiClient $api) {}

    /**
     * Step 1 — generate keypair + CSR + CCSID.
     */
    public function enroll(Store $store, string $otp, string $environment): ZatcaCertificate
    {
        [$privatePem, $publicPem, $csrPem] = $this->generateKeypairAndCsr($store);

        // Derive the API URL for this specific environment.
        // This is stored on the cert so future calls (renewal, invoice submission)
        // always hit the correct endpoint, regardless of what .env says at that time.
        $apiUrl = $this->apiUrlForEnvironment($environment);

        // Use the per-environment URL for this enrollment call.
        $apiClient = $apiUrl ? $this->api->forUrl($apiUrl) : $this->api;
        $resp = $apiClient->requestComplianceCertificate($csrPem, $otp);

        // In real (non-sandbox) environments we MUST get a certificate
        // back from ZATCA. Silently self-signing here would burn the OTP
        // and leave the tenant with a useless cert that ZATCA rejects on
        // every invoice submission.
        // developer-portal issues fake requestIDs (always 1234567890123) and
        // can't produce a real PCSID — treat it as stub/test mode only.
        // Stub when: server is in developer-portal/sandbox (local dev/tests),
        // OR the caller explicitly requested developer-portal/sandbox enrollment.
        $globalEnv = (string) config('zatca.environment', 'sandbox');
        $globalApiUrl = (string) config('zatca.api_url', '');
        $isStubMode = in_array($globalEnv, ['sandbox', 'developer-portal'], true)
            || str_contains($globalApiUrl, 'developer-portal')
            || ! $globalApiUrl
            || in_array($environment, ['sandbox', 'developer-portal'], true);

        if (! $isStubMode && empty($resp['certificate_pem'])) {
            $msg = $resp['error'] ?? 'ZATCA returned an empty response';
            throw new \RuntimeException('ZATCA enrollment failed: ' . $msg);
        }

        $certificatePem = $resp['certificate_pem']
            ?? $this->selfSignFromCsr($csrPem, $privatePem, $store, days: 365);
        $ccsid = $resp['request_id'] ?? ('CCSID-' . strtoupper(Str::random(16)));
        $secret = $resp['secret'] ?? null;
        [$issuedAt, $expiresAt] = $this->extractCertDates($certificatePem, fallbackDays: 365);

        return ZatcaCertificate::create([
            'store_id' => $store->id,
            'certificate_type' => $environment === 'production'
                ? ZatcaCertificateType::Production
                : ZatcaCertificateType::Compliance,
            'certificate_pem' => $certificatePem,
            'public_key_pem' => $publicPem,
            'private_key_pem' => Crypt::encryptString($privatePem),
            'csr_pem' => $csrPem,
            'compliance_request_id' => $ccsid,
            'ccsid' => $ccsid,
            'secret' => $secret ? Crypt::encryptString($secret) : null,
            'status' => ZatcaCertificateStatus::Active,
            'issued_at' => $issuedAt,
            'expires_at' => $expiresAt,
            'environment' => $environment,
            'api_url' => $apiUrl,
        ]);
    }

    /**
     * Step 2 — exchange the compliance CSID for a production CSID. The new
     * production cert reuses the original keypair (per ZATCA spec); we
     * simply re-encode the existing public key into a longer-lived cert.
     */
    public function renewCertificate(Store $store): ZatcaCertificate
    {
        // Production CSID is issued by exchanging an existing compliance CSID;
        // we must use the compliance certificate's keypair, secret and request
        // ID. Without an active compliance cert we cannot proceed.
        $compliance = ZatcaCertificate::where('store_id', $store->id)
            ->where('certificate_type', ZatcaCertificateType::Compliance)
            ->where('status', ZatcaCertificateStatus::Active)
            ->latest('issued_at')
            ->first();

        if (! $compliance || ! $compliance->compliance_request_id
            || ! $compliance->private_key_pem) {
            throw new \RuntimeException(
                'CertificateService: cannot renew — no active compliance certificate found for store.'
            );
        }

        // In stub/sandbox mode the cert has no real secret; that's fine because
        // requestProductionCertificate will return [] (stub) and we self-sign.
        $rawSecret = $compliance->getAttributes()['secret'];
        $privatePem = Crypt::decryptString($compliance->private_key_pem);
        $secret = $rawSecret ? Crypt::decryptString($rawSecret) : '';
        $csrPem = $compliance->csr_pem;
        $publicPem = $compliance->public_key_pem;
        $compliancePem = $compliance->getAttributes()['certificate_pem'];

        // Use the cert's stored environment URL so this works even when
        // .env has been switched to a different environment.
        $apiClient = $this->api->forCertificate($compliance);

        $resp = $apiClient->requestProductionCertificate(
            $compliance->compliance_request_id,
            $compliancePem,
            $secret
        );

        // In stub/sandbox mode requestProductionCertificate returns []; fall
        // back to a self-signed cert so tests and developer-portal stores work.
        $certPem = $resp['certificate_pem'] ?? null;
        $certificatePem = $certPem
            ?? $this->selfSignFromCsr($csrPem, $privatePem, $store, days: 1095);
        $pcsid = $resp['request_id'] ?? ('PCSID-' . strtoupper(\Illuminate\Support\Str::random(16)));
        $newSecret = $resp['secret'] ?? $secret;
        [$issuedAt, $expiresAt] = $this->extractCertDates($certificatePem, fallbackDays: 1095);

        // The production cert inherits the same environment as the compliance cert
        // (simulation CCSID → simulation PCSID; production CCSID → production PCSID).
        $production = ZatcaCertificate::create([
            'store_id' => $store->id,
            'certificate_type' => ZatcaCertificateType::Production,
            'certificate_pem' => $certificatePem,
            'public_key_pem' => $publicPem,
            'private_key_pem' => Crypt::encryptString($privatePem),
            'csr_pem' => $csrPem,
            'compliance_request_id' => $compliance->compliance_request_id,
            'pcsid' => $pcsid,
            'ccsid' => $pcsid,
            'secret' => Crypt::encryptString($newSecret),
            'status' => ZatcaCertificateStatus::Active,
            'issued_at' => $issuedAt,
            'expires_at' => $expiresAt,
            'environment' => $compliance->environment ?? config('zatca.environment', 'production'),
            'api_url' => $compliance->api_url,
        ]);

        // Only retire the compliance cert when ZATCA actually issued a
        // production cert. In stub mode (developer-portal / no api url) the
        // PCSID is self-signed and useless — keep the compliance cert active
        // so the operator can re-attempt the exchange against the real
        // simulation/production endpoint without losing their CCSID.
        if ($certPem !== null) {
            $compliance->update(['status' => ZatcaCertificateStatus::Expired]);
        }

        return $production;
    }

    /**
     * Resolve the active certificate for a store and return its decrypted
     * private key + cert PEM ready for signing.
     *
     * @return array{certificate:ZatcaCertificate, private_key_pem:string}
     */
    public function activeMaterial(string $storeId): array
    {
        $cert = ZatcaCertificate::where('store_id', $storeId)
            ->where('status', ZatcaCertificateStatus::Active)
            ->latest('issued_at')
            ->first();
        if (! $cert) {
            throw new \RuntimeException('No active ZATCA certificate for store ' . $storeId);
        }
        try {
            $privateKeyPem = $cert->private_key_pem
                ? Crypt::decryptString($cert->private_key_pem)
                : '';
        } catch (\Throwable $e) {
            // Distinct error so callers (and the operator) don't see a
            // misleading "no active certificate" when the cert exists but
            // was encrypted with a different APP_KEY (typical when a DB
            // is shared across environments with mismatched keys).
            throw new \RuntimeException(
                'Cannot decrypt ZATCA certificate private key for store ' . $storeId
                . ' (cert id ' . $cert->id . '). Likely cause: APP_KEY mismatch.',
                previous: $e,
            );
        }
        return ['certificate' => $cert, 'private_key_pem' => $privateKeyPem];
    }

    /**
     * @return array{0:string,1:string,2:string} private PEM, public PEM, CSR PEM
     */
    private function generateKeypairAndCsr(Store $store): array
    {
        // ZATCA requires secp256k1 on a SHA-256 EC key. prime256v1 is
        // NOT accepted by the real Fatoora API.
        $key = openssl_pkey_new([
            'private_key_type' => OPENSSL_KEYTYPE_EC,
            'curve_name' => 'secp256k1',
            'digest_alg' => 'sha256',
        ]);
        if ($key === false) {
            throw new \RuntimeException('CertificateService: cannot generate secp256k1 keypair (required by ZATCA)');
        }

        openssl_pkey_export($key, $privatePem);
        $details = openssl_pkey_get_details($key);
        $publicPem = $details['key'];

        // ZATCA mandates these subject fields. CN+O are the legal name,
        // C must be SA. OU rules per Fatoora User Manual v3 §5.3.1:
        //   - if 11th digit of VAT == "1" (group VAT): OU MUST be the
        //     10-digit TIN of the group member whose EGS is being onboarded
        //   - otherwise: OU is free text (branch name)
        $legalName = trim((string) ($store->name ?: 'Wameed POS Store'));
        $crNumber = trim((string) ($store->cr_number ?: '0000000000'));
        $vatNumber = trim((string) ($store->vat_number ?: '300000000000003'));
        $branchCode = trim((string) ($store->branch_code ?: $store->id));
        $branchName = trim((string) ($store->name ?: $store->name_ar ?: 'Main Branch'));
        $industry = $this->businessCategoryFor($store);
        $address = trim((string) ($store->address ?: ($store->city ?: 'Riyadh')));

        $isGroupVat = strlen($vatNumber) >= 11 && $vatNumber[10] === '1';
        $ou = $isGroupVat
            ? substr(preg_replace('/\D/', '', $crNumber) ?: '0000000000', 0, 10)
            : mb_substr($branchName, 0, 64);

        $dn = [
            'CN' => mb_substr($legalName, 0, 64),
            'organizationName' => mb_substr($legalName, 0, 64),
            'organizationalUnitName' => $ou,
            'C' => 'SA',
        ];

        $template = (string) config('zatca.csr_template', 'PREZATCA-Code-Signing');
        $opensslCnf = $this->buildOpensslCnf(
            template: $template,
            serialNumber: '1-Wameed|2-POS|3-' . $branchCode,
            vatNumber: $vatNumber,
            invoiceTypes: '1100', // standard + simplified
            registeredAddress: mb_substr($address, 0, 128),
            businessCategory: mb_substr($industry, 0, 64),
        );

        $csrConfig = [
            'digest_alg' => 'sha256',
            'config' => $opensslCnf,
            'req_extensions' => 'v3_req',
        ];
        $csrResource = openssl_csr_new($dn, $key, $csrConfig);
        if ($csrResource === false) {
            @unlink($opensslCnf);
            $err = '';
            while (($e = openssl_error_string()) !== false) {
                $err .= $e . ' | ';
            }
            throw new \RuntimeException('CertificateService: openssl_csr_new failed: ' . $err);
        }
        $csrPem = '';
        openssl_csr_export($csrResource, $csrPem);
        @unlink($opensslCnf);

        return [$privatePem, $publicPem, $csrPem];
    }

    /**
     * Map our internal BusinessType enum to a free-form ZATCA
     * `businessCategory` string. ZATCA does not enforce a vocabulary
     * here so a sensible English label is sufficient.
     */
    private function businessCategoryFor(Store $store): string
    {
        $type = $store->business_type;
        if ($type === null) {
            return 'Retail';
        }
        return match (true) {
            method_exists($type, 'value') => ucfirst(str_replace('_', ' ', (string) $type->value)),
            default => 'Retail',
        };
    }

    /**
     * Write a temporary openssl.cnf containing the full ZATCA-required
     * extensions (template name, EKU=clientAuth, and the SAN dirName
     * carrying SN/UID/title/registeredAddress/businessCategory).
     * Returns absolute path; caller must unlink.
     */
    private function buildOpensslCnf(
        string $template,
        string $serialNumber,
        string $vatNumber,
        string $invoiceTypes,
        string $registeredAddress,
        string $businessCategory,
    ): string {
        // Match the Salla SDK template (which is the de-facto reference
        // for ZATCA Phase 2 onboarding via PHP/OpenSSL).
        // Key points:
        //  - The certificate template OID MUST be PRINTABLESTRING
        //  - SAN dirName uses "SN" (which OpenSSL maps to surname, but
        //    the ZATCA parser accepts that representation)
        //  - basicConstraints / keyUsage extensions are intentionally
        //    omitted; ZATCA's reference template only requires the
        //    template OID + SAN dirName.
        $cnf = <<<CNF
[ req ]
default_bits = 2048
distinguished_name = req_distinguished_name
req_extensions = v3_req
prompt = no
utf8 = no

[ req_distinguished_name ]

[ v3_req ]
1.3.6.1.4.1.311.20.2 = ASN1:PRINTABLESTRING:{$template}
subjectAltName = dirName:zatca_san

[ zatca_san ]
SN = {$serialNumber}
UID = {$vatNumber}
title = {$invoiceTypes}
registeredAddress = {$registeredAddress}
businessCategory = {$businessCategory}
CNF;
        $path = tempnam(sys_get_temp_dir(), 'zatca_csr_');
        file_put_contents($path, $cnf);
        return $path;
    }

    /**
     * Parse the actual NotBefore / NotAfter from an issued PEM certificate.
     * Falls back to now + $fallbackDays if parsing fails.
     *
     * @return array{0: \Illuminate\Support\Carbon, 1: \Illuminate\Support\Carbon}
     */
    private function extractCertDates(string $pem, int $fallbackDays): array
    {
        $parsed = @openssl_x509_parse($pem);
        if (is_array($parsed) && isset($parsed['validFrom_time_t'], $parsed['validTo_time_t'])) {
            return [
                \Illuminate\Support\Carbon::createFromTimestamp((int) $parsed['validFrom_time_t']),
                \Illuminate\Support\Carbon::createFromTimestamp((int) $parsed['validTo_time_t']),
            ];
        }
        return [now(), now()->addDays($fallbackDays)];
    }

    private function selfSignFromCsr(string $csrPem, string $privatePem, Store $store, int $days): string
    {
        $key = openssl_pkey_get_private($privatePem);
        $cert = openssl_csr_sign($csrPem, null, $key, $days, ['digest_alg' => 'sha256']);
        if ($cert === false) {
            throw new \RuntimeException('CertificateService: cannot self-sign CSR');
        }
        $certPem = '';
        openssl_x509_export($cert, $certPem);
        return $certPem;
    }

    /**
     * Map a named ZATCA environment to its canonical API base URL.
     * Returns null for sandbox/developer-portal (stub mode — no real calls).
     * Callers can also pass a full URL directly as the $environment value.
     */
    public function apiUrlForEnvironment(string $environment): ?string
    {
        // Allow passing a full URL directly (e.g. from an admin form).
        if (str_starts_with($environment, 'http')) {
            return rtrim($environment, '/');
        }
        return match ($environment) {
            'simulation'       => 'https://gw-fatoora.zatca.gov.sa/e-invoicing/simulation',
            'production'       => 'https://gw-fatoora.zatca.gov.sa/e-invoicing/core',
            'developer-portal' => 'https://gw-fatoora.zatca.gov.sa/e-invoicing/developer-portal',
            default            => config('zatca.api_url'), // fall back to .env
        };
    }
}
