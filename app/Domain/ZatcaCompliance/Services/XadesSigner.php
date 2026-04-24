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
     * @return array{xml:string, hash:string, signature:string, public_key:string, certificate_b64:string}
     */
    public function sign(string $unsignedXml, string $privateKeyPem, string $certificatePem): array
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = false;
        $dom->loadXML($unsignedXml);

        $canonical = $dom->C14N(true, false);
        $hashRaw = hash('sha256', $canonical, true);
        $hashB64 = base64_encode($hashRaw);

        $signatureRaw = '';
        $key = openssl_pkey_get_private($privateKeyPem);
        if ($key === false) {
            throw new \RuntimeException('XAdES signer: invalid private key PEM');
        }
        $ok = openssl_sign($hashRaw, $signatureRaw, $key, OPENSSL_ALGO_SHA256);
        if (! $ok) {
            throw new \RuntimeException('XAdES signer: openssl_sign failed');
        }
        $signatureB64 = base64_encode($signatureRaw);

        $details = openssl_pkey_get_details($key);
        $publicKeyDer = $this->pemToDer($details['key'] ?? '');
        $publicKeyB64 = base64_encode($publicKeyDer);

        $certB64 = base64_encode($this->pemToDer($certificatePem));

        $signedXml = $this->embedSignature($unsignedXml, $hashB64, $signatureB64, $certB64);

        return [
            'xml' => $signedXml,
            'hash' => $hashB64,
            'signature' => $signatureB64,
            'public_key' => $publicKeyB64,
            'certificate_b64' => $certB64,
        ];
    }

    /**
     * Verify a signed invoice's signature against the provided public key.
     */
    public function verify(string $signedXml, string $publicKeyPem): bool
    {
        $sig = $this->extractSignatureValue($signedXml);
        $hashB64 = $this->extractInvoiceHash($signedXml);
        if ($sig === null || $hashB64 === null) {
            return false;
        }
        $hashRaw = base64_decode($hashB64, true);
        if ($hashRaw === false) {
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
        return openssl_verify($hashRaw, $sigRaw, $key, OPENSSL_ALGO_SHA256) === 1;
    }

    private function embedSignature(string $unsignedXml, string $hashB64, string $signatureB64, string $certB64): string
    {
        $extension = <<<XML
<ext:UBLExtensions xmlns:ext="urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2">
  <ext:UBLExtension>
    <ext:ExtensionURI>urn:oasis:names:specification:ubl:dsig:enveloped:xades</ext:ExtensionURI>
    <ext:ExtensionContent>
      <sig:UBLDocumentSignatures xmlns:sig="urn:oasis:names:specification:ubl:schema:xsd:CommonSignatureComponents-2"
                                  xmlns:sac="urn:oasis:names:specification:ubl:schema:xsd:SignatureAggregateComponents-2"
                                  xmlns:sbc="urn:oasis:names:specification:ubl:schema:xsd:SignatureBasicComponents-2">
        <sac:SignatureInformation>
          <cbc:ID xmlns:cbc="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2">urn:oasis:names:specification:ubl:signature:1</cbc:ID>
          <sbc:ReferencedSignatureID>urn:oasis:names:specification:ubl:signature:Invoice</sbc:ReferencedSignatureID>
          <ds:Signature xmlns:ds="http://www.w3.org/2000/09/xmldsig#" Id="signature">
            <ds:SignedInfo>
              <ds:CanonicalizationMethod Algorithm="http://www.w3.org/2006/12/xml-c14n11"/>
              <ds:SignatureMethod Algorithm="http://www.w3.org/2001/04/xmldsig-more#ecdsa-sha256"/>
              <ds:Reference Id="invoiceSignedData" URI="">
                <ds:Transforms>
                  <ds:Transform Algorithm="http://www.w3.org/TR/1999/REC-xpath-19991116">
                    <ds:XPath>not(//ancestor-or-self::ext:UBLExtensions)</ds:XPath>
                  </ds:Transform>
                  <ds:Transform Algorithm="http://www.w3.org/2006/12/xml-c14n11"/>
                </ds:Transforms>
                <ds:DigestMethod Algorithm="http://www.w3.org/2001/04/xmlenc#sha256"/>
                <ds:DigestValue>{$hashB64}</ds:DigestValue>
              </ds:Reference>
            </ds:SignedInfo>
            <ds:SignatureValue>{$signatureB64}</ds:SignatureValue>
            <ds:KeyInfo>
              <ds:X509Data>
                <ds:X509Certificate>{$certB64}</ds:X509Certificate>
              </ds:X509Data>
            </ds:KeyInfo>
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

    private function pemToDer(string $pem): string
    {
        $clean = preg_replace('/-----(BEGIN|END)[^-]+-----/', '', $pem);
        $clean = preg_replace('/\s+/', '', (string) $clean);
        $der = base64_decode((string) $clean, true);
        return $der === false ? '' : $der;
    }
}
