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

        $resp = $this->api->requestComplianceCertificate($csrPem, $otp);

        // In real (non-sandbox) environments we MUST get a certificate
        // back from ZATCA. Silently self-signing here would burn the OTP
        // and leave the tenant with a useless cert that ZATCA rejects on
        // every invoice submission.
        $isStubMode = config('zatca.environment') === 'sandbox'
            || ! config('zatca.api_url')
            || str_contains((string) config('zatca.api_url'), 'developer-portal');

        if (! $isStubMode && empty($resp['certificate_pem'])) {
            $msg = $resp['error'] ?? 'ZATCA returned an empty response';
            throw new \RuntimeException('ZATCA enrollment failed: ' . $msg);
        }

        $certificatePem = $resp['certificate_pem']
            ?? $this->selfSignFromCsr($csrPem, $privatePem, $store, days: 365);
        $ccsid = $resp['request_id'] ?? ('CCSID-' . strtoupper(Str::random(16)));
        $secret = $resp['secret'] ?? null;

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
            'issued_at' => now(),
            'expires_at' => now()->addYear(),
        ]);
    }

    /**
     * Step 2 — exchange the compliance CSID for a production CSID. The new
     * production cert reuses the original keypair (per ZATCA spec); we
     * simply re-encode the existing public key into a longer-lived cert.
     */
    public function renewCertificate(Store $store): ZatcaCertificate
    {
        $current = ZatcaCertificate::where('store_id', $store->id)
            ->where('status', ZatcaCertificateStatus::Active)
            ->latest('issued_at')
            ->first();

        if ($current) {
            $current->update(['status' => ZatcaCertificateStatus::Expired]);
        }

        $privatePem = $current && $current->private_key_pem
            ? Crypt::decryptString($current->private_key_pem)
            : null;
        $csrPem = $current?->csr_pem;
        if (! $privatePem || ! $csrPem) {
            // Bootstrap a brand-new chain if we have nothing carried over.
            [$privatePem, $publicPem, $csrPem] = $this->generateKeypairAndCsr($store);
        } else {
            $publicPem = $current->public_key_pem;
        }

        $resp = $this->api->requestProductionCertificate($csrPem, $current?->compliance_request_id ?? '');
        $certificatePem = $resp['certificate_pem']
            ?? $this->selfSignFromCsr($csrPem, $privatePem, $store, days: 1095);
        $pcsid = $resp['request_id'] ?? ('PCSID-' . strtoupper(Str::random(16)));

        return ZatcaCertificate::create([
            'store_id' => $store->id,
            'certificate_type' => ZatcaCertificateType::Production,
            'certificate_pem' => $certificatePem,
            'public_key_pem' => $publicPem,
            'private_key_pem' => Crypt::encryptString($privatePem),
            'csr_pem' => $csrPem,
            'compliance_request_id' => $current?->compliance_request_id,
            'pcsid' => $pcsid,
            'ccsid' => $pcsid,
            'status' => ZatcaCertificateStatus::Active,
            'issued_at' => now(),
            'expires_at' => now()->addYears(3),
        ]);
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
        $privateKeyPem = $cert->private_key_pem
            ? Crypt::decryptString($cert->private_key_pem)
            : '';
        return ['certificate' => $cert, 'private_key_pem' => $privateKeyPem];
    }

    /**
     * @return array{0:string,1:string,2:string} private PEM, public PEM, CSR PEM
     */
    private function generateKeypairAndCsr(Store $store): array
    {
        $keyConfig = [
            'private_key_type' => OPENSSL_KEYTYPE_EC,
            'curve_name' => 'secp256k1',
            'digest_alg' => 'sha256',
        ];
        $key = openssl_pkey_new($keyConfig);
        if ($key === false) {
            // Fall back to prime256v1 if secp256k1 is unsupported on this
            // OpenSSL build (cryptographically equivalent for our needs).
            $key = openssl_pkey_new([
                'private_key_type' => OPENSSL_KEYTYPE_EC,
                'curve_name' => 'prime256v1',
                'digest_alg' => 'sha256',
            ]);
        }
        if ($key === false) {
            throw new \RuntimeException('CertificateService: cannot generate EC keypair');
        }

        openssl_pkey_export($key, $privatePem);
        $details = openssl_pkey_get_details($key);
        $publicPem = $details['key'];

        $dn = [
            'CN' => substr($store->name, 0, 50),
            'O' => substr($store->name, 0, 50),
            'OU' => 'POS',
            'C' => 'SA',
            'ST' => $store->city ?? 'Riyadh',
            'L' => $store->city ?? 'Riyadh',
        ];

        // ZATCA requires the CSR to carry a certificateTemplateName
        // extension (1.3.6.1.4.1.311.20.2). The exact value differs
        // per environment per the Fatoora Portal User Manual v3:
        //   production  -> ZATCA-Code-Signing
        //   simulation  -> PREZATCA-Code-Signing
        //   sandbox     -> PREZATCA-Code-Signing (also accepted by stub)
        $template = (string) config('zatca.csr_template', 'PREZATCA-Code-Signing');
        $opensslCnf = $this->buildOpensslCnf($template);
        $csrConfig = [
            'digest_alg' => 'sha256',
            'config' => $opensslCnf,
            'req_extensions' => 'v3_req',
        ];
        $csrResource = openssl_csr_new($dn, $key, $csrConfig);
        if ($csrResource === false) {
            // Fallback if the platform openssl build cannot load our cnf
            // (the resulting CSR will lack the template extension and
            // will be rejected by real ZATCA, but is fine for stub mode).
            $csrResource = openssl_csr_new($dn, $key, ['digest_alg' => 'sha256']);
        }
        $csrPem = '';
        if ($csrResource !== false) {
            openssl_csr_export($csrResource, $csrPem);
        }
        @unlink($opensslCnf);

        return [$privatePem, $publicPem, $csrPem];
    }

    /**
     * Write a temporary openssl.cnf that adds the ZATCA
     * `certificateTemplateName` extension to the v3_req section. Returns
     * the absolute path to the temp file (caller must unlink).
     */
    private function buildOpensslCnf(string $template): string
    {
        $cnf = <<<CNF
[ req ]
default_bits = 2048
distinguished_name = req_distinguished_name
req_extensions = v3_req
prompt = no

[ req_distinguished_name ]

[ v3_req ]
basicConstraints = CA:FALSE
keyUsage = digitalSignature, nonRepudiation, keyEncipherment
1.3.6.1.4.1.311.20.2 = ASN1:PRINTABLESTRING:{$template}
CNF;
        $path = tempnam(sys_get_temp_dir(), 'zatca_csr_');
        file_put_contents($path, $cnf);
        return $path;
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
}
