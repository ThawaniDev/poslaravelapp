<?php

namespace App\Domain\ZatcaCompliance\Services;

/**
 * ZATCA Phase 2 TLV (Tag-Length-Value) QR encoder.
 *
 * Packs the 9 mandatory tags per ZATCA Fatoora spec:
 *   1  Seller name
 *   2  Seller VAT number
 *   3  Invoice timestamp (ISO 8601)
 *   4  Invoice total (with VAT)
 *   5  VAT total
 *   6  Invoice hash (base64 SHA-256 of canonicalized XML)
 *   7  ECDSA signature (base64)
 *   8  EGS public key (DER, base64)
 *   9  ZATCA stamp / certificate signature (base64)
 *
 * Output is a base64-encoded TLV byte string ready to embed in a QR code.
 */
class TlvQrEncoder
{
    /**
     * @param  array{seller_name:string,vat_number:string,timestamp:string,total:string|float,vat:string|float,invoice_hash:string,signature:string,public_key:string,certificate_signature:string}  $fields
     */
    public function encode(array $fields): string
    {
        $tlv = '';
        $tlv .= $this->tag(1, (string) $fields['seller_name']);
        $tlv .= $this->tag(2, (string) $fields['vat_number']);
        $tlv .= $this->tag(3, (string) $fields['timestamp']);
        $tlv .= $this->tag(4, (string) $fields['total']);
        $tlv .= $this->tag(5, (string) $fields['vat']);
        $tlv .= $this->tag(6, (string) $fields['invoice_hash']);
        $tlv .= $this->tag(7, (string) $fields['signature']);
        $tlv .= $this->tag(8, (string) $fields['public_key']);
        $tlv .= $this->tag(9, (string) $fields['certificate_signature']);

        return base64_encode($tlv);
    }

    /**
     * Decode a base64 TLV blob back into a tag => value map. Provided
     * primarily for tests and verification tooling.
     *
     * @return array<int,string>
     */
    public function decode(string $base64): array
    {
        $bytes = base64_decode($base64, true);
        if ($bytes === false) {
            return [];
        }
        $out = [];
        $i = 0;
        $len = strlen($bytes);
        while ($i + 2 <= $len) {
            $tag = ord($bytes[$i]);
            $valueLen = ord($bytes[$i + 1]);
            $i += 2;
            if ($i + $valueLen > $len) {
                break;
            }
            $out[$tag] = substr($bytes, $i, $valueLen);
            $i += $valueLen;
        }
        return $out;
    }

    private function tag(int $tag, string $value): string
    {
        $bytes = $value;
        $len = strlen($bytes);
        // ZATCA TLV uses single-byte length up to 255. Longer values must
        // be split, but the 9 mandatory tags never exceed 255 bytes in
        // practice (signatures + key are within bounds).
        if ($len > 255) {
            $bytes = substr($bytes, 0, 255);
            $len = 255;
        }
        return chr($tag) . chr($len) . $bytes;
    }
}
