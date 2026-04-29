<?php

namespace App\Domain\ZatcaCompliance\Services;

use DOMDocument;

/**
 * XAdES-BES signer for ZATCA Phase 2 UBL invoices.
 *
 *   1. Canonicalize the unsigned UBL invoice (C14N 1.1).
 *   2. SHA-256 hash → invoice_hash (base64).
 *   3. ECDSA sign the hash with the EGS private key (PEM).
 *   4. Embed the signature value, signed properties and key info inside
 *      ext:UBLExtensions on the invoice. The result is a fully signed
 *      invoice that can be canonicalized + verified.
 *
 * Output is a structured array including the cleared XML, the base64
 * invoice hash, base64 signature value and base64 DER public key — all
 * the fields the TLV QR encoder needs (tags 6, 7 and 8).
 */
class XadesSigner
{
    /**
     * @return array{xml:string, hash:string, signature:string, public_key:string, certificate_b64:string, certificate_signature:string}
     */
    public function sign(string $unsignedXml, string $privateKeyPem, string $certificatePem): array
    {
        // Per ZATCA Phase-2 spec the invoice hash is computed over the
        // canonicalized invoice with three elements removed:
        //   - ext:UBLExtensions (signature container)
        //   - cac:Signature (signature reference)
        //   - cac:AdditionalDocumentReference whose cbc:ID = "QR"
        // and using inclusive C14N (C14N 1.1, exclusive=false, withComments=false).
        $stripDom = new DOMDocument('1.0', 'UTF-8');
        $stripDom->preserveWhiteSpace = false;
        $stripDom->loadXML($unsignedXml);
        $xp = new \DOMXPath($stripDom);
        $xp->registerNamespace('ext', 'urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2');
        $xp->registerNamespace('cac', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');
        $xp->registerNamespace('cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');
        foreach ($xp->query('//ext:UBLExtensions') as $n) { $n->parentNode->removeChild($n); }
        foreach ($xp->query('//cac:Signature') as $n) { $n->parentNode->removeChild($n); }
        foreach ($xp->query('//cac:AdditionalDocumentReference[cbc:ID="QR"]') as $n) { $n->parentNode->removeChild($n); }
        $canonical = $stripDom->documentElement->C14N(false, false);
        $invoiceHashRaw = hash('sha256', $canonical, true);
        $invoiceHashB64 = base64_encode($invoiceHashRaw);

        // Cert details (issuer/serial/digest) needed for xades:SigningCertificate.
        $certDer = $this->pemToDer($certificatePem);
        $certB64 = base64_encode($certDer);
        // ZATCA's reference SDK hashes the BST (base64 of the cert PEM body),
        // NOT the raw DER. It also wraps the digest as base64(hex(sha256(...))),
        // i.e. the SHA-256 result is first hex-encoded, then base64-encoded.
        // Verified against the official .NET SDK Simplified_Invoice.xml sample.
        $certDigestB64 = base64_encode(hash('sha256', $certB64, false));
        [$issuerName, $serialDecimal] = $this->extractIssuerAndSerial($certificatePem);

        // ZATCA's validator hashes the SignedProperties element AS IF the
        // xades + ds namespace declarations are inlined on it (because the
        // server canonicalizes from the parent context where they're
        // declared). But the embedded copy in the final invoice is the
        // bare element (xmlns inherited from xades:QualifyingProperties /
        // ds:Signature). So we render two variants here.
        $signingTime = (new \DateTimeImmutable('now'))->format('Y-m-d\TH:i:s');
        $signedPropsForHash = $this->renderSignedProperties(
            signingTime: $signingTime,
            certDigestB64: $certDigestB64,
            issuerName: $issuerName,
            serialDecimal: $serialDecimal,
            withInlineNamespaces: true,
        );
        $signedPropsForEmbed = $this->renderSignedProperties(
            signingTime: $signingTime,
            certDigestB64: $certDigestB64,
            issuerName: $issuerName,
            serialDecimal: $serialDecimal,
            withInlineNamespaces: false,
        );
        // SignedProperties digest follows the same ZATCA convention as the
        // certificate digest: base64(hex(sha256(text))).
        $signedPropsHashB64 = base64_encode(hash('sha256', $signedPropsForHash, false));

        // Build SignedInfo (the bytes that are actually ECDSA-signed).
        $signedInfoXml = $this->renderSignedInfo(
            invoiceHashB64: $invoiceHashB64,
            signedPropsHashB64: $signedPropsHashB64,
        );

        $key = openssl_pkey_get_private($privateKeyPem);
        if ($key === false) {
            throw new \RuntimeException('XAdES signer: invalid private key PEM');
        }
        $signatureRaw = '';
        // Sign the canonicalized SignedInfo per XML-DSig spec.
        $ok = openssl_sign($signedInfoXml, $signatureRaw, $key, OPENSSL_ALGO_SHA256);
        if (! $ok) {
            throw new \RuntimeException('XAdES signer: openssl_sign failed');
        }
        $signatureB64 = base64_encode($signatureRaw);

        $details = openssl_pkey_get_details($key);
        $publicKeyDer = $this->pemToDer($details['key'] ?? '');
        $publicKeyB64 = base64_encode($publicKeyDer);

        // Extract just the X.509 signature value (~70 bytes) for QR tag 9.
        $certSignatureRaw = $this->extractCertificateSignature($certDer);

        $signedXml = $this->embedSignature(
            unsignedXml: $unsignedXml,
            signedInfoXml: $signedInfoXml,
            signatureB64: $signatureB64,
            certB64: $certB64,
            signedPropsXml: $signedPropsForEmbed,
        );

        return [
            'xml' => $signedXml,
            'hash' => $invoiceHashB64,
            'signature' => $signatureB64,
            'public_key' => $publicKeyB64,
            'certificate_b64' => $certB64,
            'certificate_signature' => $certSignatureRaw,
        ];
    }

    /**
     * Verify a signed invoice's signature against the provided public key.
     *
     * Re-builds the SignedInfo block from the embedded values and checks
     * the ECDSA-SHA256 signature against it (matching what was actually
     * signed in sign()).
     */
    public function verify(string $signedXml, string $publicKeyPem): bool
    {
        $sig = $this->extractSignatureValue($signedXml);
        if ($sig === null) {
            return false;
        }
        $signedInfo = $this->extractSignedInfo($signedXml);
        if ($signedInfo === null) {
            return false;
        }
        $key = openssl_pkey_get_public($publicKeyPem);
        if ($key === false) {
            return false;
        }
        $sigRaw = base64_decode($sig, true);
        if ($sigRaw === false) {
            return false;
        }
        return openssl_verify($signedInfo, $sigRaw, $key, OPENSSL_ALGO_SHA256) === 1;
    }

    /**
     * Render the SignedProperties XML using the exact byte layout that
     * ZATCA's reference SDK uses. The same string is hashed AND embedded
     * into the final invoice so verifiers recompute an identical digest.
     */
    private function renderSignedProperties(
        string $signingTime,
        string $certDigestB64,
        string $issuerName,
        string $serialDecimal,
        bool $withInlineNamespaces,
    ): string {
        $xadesAttr = $withInlineNamespaces ? ' xmlns:xades="http://uri.etsi.org/01903/v1.3.2#"' : '';
        $dsAttr = $withInlineNamespaces ? ' xmlns:ds="http://www.w3.org/2000/09/xmldsig#"' : '';
        return <<<XML
<xades:SignedProperties{$xadesAttr} Id="xadesSignedProperties">
                                    <xades:SignedSignatureProperties>
                                        <xades:SigningTime>{$signingTime}</xades:SigningTime>
                                        <xades:SigningCertificate>
                                            <xades:Cert>
                                                <xades:CertDigest>
                                                    <ds:DigestMethod{$dsAttr} Algorithm="http://www.w3.org/2001/04/xmlenc#sha256"/>
                                                    <ds:DigestValue{$dsAttr}>{$certDigestB64}</ds:DigestValue>
                                                </xades:CertDigest>
                                                <xades:IssuerSerial>
                                                    <ds:X509IssuerName{$dsAttr}>{$issuerName}</ds:X509IssuerName>
                                                    <ds:X509SerialNumber{$dsAttr}>{$serialDecimal}</ds:X509SerialNumber>
                                                </xades:IssuerSerial>
                                            </xades:Cert>
                                        </xades:SigningCertificate>
                                    </xades:SignedSignatureProperties>
                                </xades:SignedProperties>
XML;
    }

    /**
     * Render the SignedInfo block. ECDSA-SHA256 signs the canonical bytes
     * of this exact element; the same bytes are embedded into the final
     * XML so verifiers can re-canonicalize and verify against SignatureValue.
     *
     * Two references are present:
     *   - The invoice itself (URI=""), with the XPath transform that
     *     strips ext:UBLExtensions, plus the C14N 1.1 transform.
     *   - The xades:SignedProperties block (URI="#xadesSignedProperties").
     */
    private function renderSignedInfo(string $invoiceHashB64, string $signedPropsHashB64): string
    {
        return <<<XML
<ds:SignedInfo xmlns:ds="http://www.w3.org/2000/09/xmldsig#">
                            <ds:CanonicalizationMethod Algorithm="http://www.w3.org/2006/12/xml-c14n11"/>
                            <ds:SignatureMethod Algorithm="http://www.w3.org/2001/04/xmldsig-more#ecdsa-sha256"/>
                            <ds:Reference Id="invoiceSignedData" URI="">
                                <ds:Transforms>
                                    <ds:Transform Algorithm="http://www.w3.org/TR/1999/REC-xpath-19991116">
                                        <ds:XPath>not(//ancestor-or-self::ext:UBLExtensions)</ds:XPath>
                                    </ds:Transform>
                                    <ds:Transform Algorithm="http://www.w3.org/TR/1999/REC-xpath-19991116">
                                        <ds:XPath>not(//ancestor-or-self::cac:Signature)</ds:XPath>
                                    </ds:Transform>
                                    <ds:Transform Algorithm="http://www.w3.org/TR/1999/REC-xpath-19991116">
                                        <ds:XPath>not(//ancestor-or-self::cac:AdditionalDocumentReference[cbc:ID='QR'])</ds:XPath>
                                    </ds:Transform>
                                    <ds:Transform Algorithm="http://www.w3.org/2006/12/xml-c14n11"/>
                                </ds:Transforms>
                                <ds:DigestMethod Algorithm="http://www.w3.org/2001/04/xmlenc#sha256"/>
                                <ds:DigestValue>{$invoiceHashB64}</ds:DigestValue>
                            </ds:Reference>
                            <ds:Reference Type="http://www.w3.org/2000/09/xmldsig#SignatureProperties" URI="#xadesSignedProperties">
                                <ds:DigestMethod Algorithm="http://www.w3.org/2001/04/xmlenc#sha256"/>
                                <ds:DigestValue>{$signedPropsHashB64}</ds:DigestValue>
                            </ds:Reference>
                        </ds:SignedInfo>
XML;
    }

    /**
     * Extract the certificate's issuer DN (in OpenSSL's "/CN=...,O=..."
     * form) and decimal serial number for embedding in xades:IssuerSerial.
     *
     * @return array{0:string, 1:string}
     */
    private function extractIssuerAndSerial(string $certificatePem): array
    {
        $parsed = openssl_x509_parse($certificatePem);
        $issuer = '';
        if (is_array($parsed) && isset($parsed['issuer']) && is_array($parsed['issuer'])) {
            // Render as "CN=eInvoicing" → "CN=eInvoicing" (single value)
            // or comma-joined for multi-component issuer DNs.
            $parts = [];
            foreach ($parsed['issuer'] as $key => $val) {
                $parts[] = $key . '=' . (is_array($val) ? implode(',', $val) : $val);
            }
            $issuer = implode(', ', $parts);
        }

        $serial = '';
        if (is_array($parsed)) {
            // Newer OpenSSL exposes serialNumberHex; convert to decimal.
            if (! empty($parsed['serialNumberHex'])) {
                $serial = $this->hexToDecimal((string) $parsed['serialNumberHex']);
            } elseif (! empty($parsed['serialNumber'])) {
                $serial = (string) $parsed['serialNumber'];
            }
        }

        return [$issuer, $serial];
    }

    private function hexToDecimal(string $hex): string
    {
        if ($hex === '') {
            return '';
        }
        if (function_exists('gmp_strval')) {
            return gmp_strval(gmp_init($hex, 16), 10);
        }
        // Fallback (no GMP) — manual base-16 → base-10 for arbitrary length.
        $dec = '0';
        $len = strlen($hex);
        for ($i = 0; $i < $len; $i++) {
            $dec = bcmul($dec, '16');
            $dec = bcadd($dec, (string) hexdec($hex[$i]));
        }
        return $dec;
    }

    private function embedSignature(
        string $unsignedXml,
        string $signedInfoXml,
        string $signatureB64,
        string $certB64,
        string $signedPropsXml,
    ): string {
        $extension = <<<XML
<ext:UBLExtensions xmlns:ext="urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2">
    <ext:UBLExtension>
        <ext:ExtensionURI>urn:oasis:names:specification:ubl:dsig:enveloped:xades</ext:ExtensionURI>
        <ext:ExtensionContent>
            <sig:UBLDocumentSignatures xmlns:sig="urn:oasis:names:specification:ubl:schema:xsd:CommonSignatureComponents-2" xmlns:sac="urn:oasis:names:specification:ubl:schema:xsd:SignatureAggregateComponents-2" xmlns:sbc="urn:oasis:names:specification:ubl:schema:xsd:SignatureBasicComponents-2">
                <sac:SignatureInformation>
                    <cbc:ID xmlns:cbc="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2">urn:oasis:names:specification:ubl:signature:1</cbc:ID>
                    <sbc:ReferencedSignatureID>urn:oasis:names:specification:ubl:signature:Invoice</sbc:ReferencedSignatureID>
                    <ds:Signature xmlns:ds="http://www.w3.org/2000/09/xmldsig#" Id="signature">
                        {$signedInfoXml}
                        <ds:SignatureValue>{$signatureB64}</ds:SignatureValue>
                        <ds:KeyInfo>
                            <ds:X509Data>
                                <ds:X509Certificate>{$certB64}</ds:X509Certificate>
                            </ds:X509Data>
                        </ds:KeyInfo>
                        <ds:Object>
                            <xades:QualifyingProperties xmlns:xades="http://uri.etsi.org/01903/v1.3.2#" Target="signature">
                                {$signedPropsXml}
                            </xades:QualifyingProperties>
                        </ds:Object>
                    </ds:Signature>
                </sac:SignatureInformation>
            </sig:UBLDocumentSignatures>
        </ext:ExtensionContent>
    </ext:UBLExtension>
</ext:UBLExtensions>
XML;

        // Inject the extensions block as the first child of <Invoice>.
        return preg_replace(
            '/(<Invoice\b[^>]*>)/',
            '$1' . $extension,
            $unsignedXml,
            1
        );
    }

    private function extractSignatureValue(string $xml): ?string
    {
        if (preg_match('#<ds:SignatureValue>([A-Za-z0-9+/=\s]+)</ds:SignatureValue>#', $xml, $m)) {
            return preg_replace('/\s+/', '', $m[1]);
        }
        return null;
    }

    private function extractInvoiceHash(string $xml): ?string
    {
        if (preg_match('#<ds:DigestValue>([A-Za-z0-9+/=\s]+)</ds:DigestValue>#', $xml, $m)) {
            return preg_replace('/\s+/', '', $m[1]);
        }
        return null;
    }

    private function extractSignedInfo(string $xml): ?string
    {
        if (preg_match('#<ds:SignedInfo[^>]*>.*?</ds:SignedInfo>#s', $xml, $m)) {
            return $m[0];
        }
        return null;
    }

    private function pemToDer(string $pem): string
    {
        $clean = preg_replace('/-----(BEGIN|END)[^-]+-----/', '', $pem);
        $clean = preg_replace('/\s+/', '', (string) $clean);
        $der = base64_decode((string) $clean, true);
        return $der === false ? '' : $der;
    }

    /**
     * Extract the raw X.509 signatureValue (a BIT STRING) from a DER-encoded
     * certificate. An X.509 cert has the structure:
     *   SEQUENCE { tbsCertificate, signatureAlgorithm, signatureValue }
     * The signatureValue is the trailing BIT STRING. For ZATCA QR tag 9 we
     * return the raw bit-string contents (with the leading "unused bits"
     * byte stripped — yielding the bare ECDSA DER signature, ~70 bytes).
     */
    private function extractCertificateSignature(string $der): string
    {
        if ($der === '' || ord($der[0]) !== 0x30) {
            return '';
        }
        // Skip outer SEQUENCE header (tag + length).
        $i = 1;
        $len = ord($der[$i++]);
        if ($len & 0x80) {
            $i += $len & 0x7F;
        }
        $end = strlen($der);
        $lastBitString = '';
        while ($i < $end) {
            $tag = ord($der[$i++]);
            $l = ord($der[$i++]);
            if ($l & 0x80) {
                $n = $l & 0x7F;
                $l = 0;
                for ($k = 0; $k < $n; $k++) {
                    $l = ($l << 8) | ord($der[$i++]);
                }
            }
            $value = substr($der, $i, $l);
            $i += $l;
            if ($tag === 0x03) { // BIT STRING
                $lastBitString = $value;
            }
        }
        // BIT STRING value starts with one byte indicating "unused bits".
        return $lastBitString === '' ? '' : substr($lastBitString, 1);
    }
}
