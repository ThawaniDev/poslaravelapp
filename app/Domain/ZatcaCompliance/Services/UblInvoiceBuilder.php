<?php

namespace App\Domain\ZatcaCompliance\Services;

use App\Domain\Core\Models\Store;
use App\Domain\ZatcaCompliance\Enums\ZatcaInvoiceType;
use DOMDocument;
use Illuminate\Support\Carbon;

/**
 * Builds UBL 2.1 XML invoices in ZATCA Phase 2 shape.
 *
 * The output is a fully namespaced UBL 2.1 Invoice document with all
 * mandatory ZATCA elements:
 *   - cbc:UUID, cbc:ID, cbc:IssueDate, cbc:IssueTime
 *   - cbc:InvoiceTypeCode (388/381/383)
 *   - cbc:DocumentCurrencyCode = SAR
 *   - AdditionalDocumentReference (ICV + PIH)
 *   - AccountingSupplierParty / AccountingCustomerParty
 *   - InvoiceLine[] with Item.Name + Item.Name (Arabic)
 *   - TaxTotal at line and document level
 *   - LegalMonetaryTotal (line/tax/payable/inclusive)
 *
 * Numbers follow ZATCA's two-decimal formatting. The XML is namespaced
 * so the downstream XAdES signer can canonicalize and sign predictably.
 *
 * @phpstan-type LineInput array{
 *   name:string,
 *   name_ar?:?string,
 *   quantity:float,
 *   unit_price:float,
 *   tax_percent?:float,
 *   tax_category?:string,
 *   tax_exemption_reason?:?string,
 * }
 *
 * @phpstan-type Input array{
 *   uuid:string,
 *   invoice_number:string,
 *   issue_at:\DateTimeInterface,
 *   invoice_type:ZatcaInvoiceType,
 *   icv:int,
 *   pih:string,
 *   is_b2b:bool,
 *   reference_invoice_uuid?:?string,
 *   adjustment_reason?:?string,
 *   buyer_name?:?string,
 *   buyer_vat?:?string,
 *   lines: array<int, LineInput>,
 *   currency?:string,
 * }
 */
class UblInvoiceBuilder
{
    private const NS_INVOICE = 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2';
    private const NS_CAC = 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2';
    private const NS_CBC = 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2';
    private const NS_EXT = 'urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2';

    public function build(Store $store, array $input): string
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = false;

        $root = $dom->createElementNS(self::NS_INVOICE, 'Invoice');
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:cac', self::NS_CAC);
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:cbc', self::NS_CBC);
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:ext', self::NS_EXT);
        $dom->appendChild($root);

        $issuedAt = $input['issue_at'] instanceof \DateTimeInterface
            ? Carbon::instance($input['issue_at'])
            : Carbon::parse((string) $input['issue_at']);

        $currency = $input['currency'] ?? 'SAR';
        $type = $input['invoice_type'];
        $typeCode = $this->typeCode($type);
        $typeName = $input['is_b2b'] ? '0100000' : '0200000'; // ZATCA invoice subtype: B2B / B2C

        $this->cbc($dom, $root, 'ProfileID', 'reporting:1.0');
        $this->cbc($dom, $root, 'ID', $input['invoice_number']);
        $this->cbc($dom, $root, 'UUID', $input['uuid']);
        $this->cbc($dom, $root, 'IssueDate', $issuedAt->toDateString());
        $this->cbc($dom, $root, 'IssueTime', $issuedAt->format('H:i:s'));
        $invoiceType = $this->cbc($dom, $root, 'InvoiceTypeCode', (string) $typeCode);
        $invoiceType->setAttribute('name', $typeName);
        $this->cbc($dom, $root, 'DocumentCurrencyCode', $currency);
        $this->cbc($dom, $root, 'TaxCurrencyCode', $currency);

        // Reference invoice for credit / debit notes.
        if (in_array($type, [ZatcaInvoiceType::CreditNote, ZatcaInvoiceType::DebitNote], true)
            && ! empty($input['reference_invoice_uuid'])
        ) {
            $billingRef = $dom->createElementNS(self::NS_CAC, 'cac:BillingReference');
            $invoiceDocRef = $dom->createElementNS(self::NS_CAC, 'cac:InvoiceDocumentReference');
            $this->cbc($dom, $invoiceDocRef, 'ID', $input['reference_invoice_uuid']);
            $billingRef->appendChild($invoiceDocRef);
            $root->appendChild($billingRef);

            if (! empty($input['adjustment_reason'])) {
                $note = $this->cbc($dom, $root, 'Note', $input['adjustment_reason']);
                $note->setAttribute('languageID', 'en');
            }
        }

        // ICV
        $icvRef = $dom->createElementNS(self::NS_CAC, 'cac:AdditionalDocumentReference');
        $this->cbc($dom, $icvRef, 'ID', 'ICV');
        $this->cbc($dom, $icvRef, 'UUID', (string) $input['icv']);
        $root->appendChild($icvRef);

        // PIH
        $pihRef = $dom->createElementNS(self::NS_CAC, 'cac:AdditionalDocumentReference');
        $this->cbc($dom, $pihRef, 'ID', 'PIH');
        $attach = $dom->createElementNS(self::NS_CAC, 'cac:Attachment');
        $embedded = $dom->createElementNS(self::NS_CBC, 'cbc:EmbeddedDocumentBinaryObject', $input['pih']);
        $embedded->setAttribute('mimeCode', 'text/plain');
        $attach->appendChild($embedded);
        $pihRef->appendChild($attach);
        $root->appendChild($pihRef);

        // Supplier
        $root->appendChild($this->buildParty($dom, 'AccountingSupplierParty', [
            'name' => $store->name,
            'vat' => $store->vat_number ?? $store->tax_number ?? '300000000000003',
            'street' => $store->address ?? 'N/A',
            'city' => $store->city ?? 'Riyadh',
            'postal' => $store->postal_code ?? '00000',
            'country' => 'SA',
        ]));

        // Customer (always present per UBL; B2C uses fallback name).
        $root->appendChild($this->buildParty($dom, 'AccountingCustomerParty', [
            'name' => $input['buyer_name'] ?? 'Walk-in Customer',
            'vat' => $input['buyer_vat'] ?? null,
            'street' => null,
            'city' => null,
            'postal' => null,
            'country' => 'SA',
        ]));

        // Compute line + tax totals
        $lineExtension = 0.0;
        $taxAmount = 0.0;
        $taxableAmount = 0.0;
        $linesXml = [];
        $idx = 0;
        foreach ($input['lines'] as $line) {
            $idx++;
            $qty = (float) $line['quantity'];
            $price = (float) $line['unit_price'];
            $lineNet = round($qty * $price, 2);
            $taxPct = (float) ($line['tax_percent'] ?? 15.0);
            $lineTax = round($lineNet * $taxPct / 100, 2);

            $lineExtension += $lineNet;
            $taxAmount += $lineTax;
            $taxableAmount += $lineNet;

            $linesXml[] = [
                'idx' => $idx,
                'qty' => $qty,
                'price' => $price,
                'net' => $lineNet,
                'tax' => $lineTax,
                'tax_pct' => $taxPct,
                'tax_category' => $line['tax_category'] ?? 'S',
                'tax_exemption_reason' => $line['tax_exemption_reason'] ?? null,
                'name' => $line['name'],
                'name_ar' => $line['name_ar'] ?? null,
            ];
        }
        $payable = round($lineExtension + $taxAmount, 2);

        // TaxTotal (document level)
        $docTaxTotal = $dom->createElementNS(self::NS_CAC, 'cac:TaxTotal');
        $this->cbc($dom, $docTaxTotal, 'TaxAmount', $this->money($taxAmount))->setAttribute('currencyID', $currency);
        $taxSubtotal = $dom->createElementNS(self::NS_CAC, 'cac:TaxSubtotal');
        $this->cbc($dom, $taxSubtotal, 'TaxableAmount', $this->money($taxableAmount))->setAttribute('currencyID', $currency);
        $this->cbc($dom, $taxSubtotal, 'TaxAmount', $this->money($taxAmount))->setAttribute('currencyID', $currency);
        $taxCat = $dom->createElementNS(self::NS_CAC, 'cac:TaxCategory');
        $this->cbc($dom, $taxCat, 'ID', 'S');
        $this->cbc($dom, $taxCat, 'Percent', $this->money(15.0));
        $taxScheme = $dom->createElementNS(self::NS_CAC, 'cac:TaxScheme');
        $this->cbc($dom, $taxScheme, 'ID', 'VAT');
        $taxCat->appendChild($taxScheme);
        $taxSubtotal->appendChild($taxCat);
        $docTaxTotal->appendChild($taxSubtotal);
        $root->appendChild($docTaxTotal);

        // LegalMonetaryTotal
        $legal = $dom->createElementNS(self::NS_CAC, 'cac:LegalMonetaryTotal');
        $this->cbc($dom, $legal, 'LineExtensionAmount', $this->money($lineExtension))->setAttribute('currencyID', $currency);
        $this->cbc($dom, $legal, 'TaxExclusiveAmount', $this->money($lineExtension))->setAttribute('currencyID', $currency);
        $this->cbc($dom, $legal, 'TaxInclusiveAmount', $this->money($payable))->setAttribute('currencyID', $currency);
        $this->cbc($dom, $legal, 'PayableAmount', $this->money($payable))->setAttribute('currencyID', $currency);
        $root->appendChild($legal);

        // Invoice lines
        foreach ($linesXml as $l) {
            $line = $dom->createElementNS(self::NS_CAC, 'cac:InvoiceLine');
            $this->cbc($dom, $line, 'ID', (string) $l['idx']);
            $qtyEl = $this->cbc($dom, $line, 'InvoicedQuantity', $this->money($l['qty']));
            $qtyEl->setAttribute('unitCode', 'PCE');
            $this->cbc($dom, $line, 'LineExtensionAmount', $this->money($l['net']))->setAttribute('currencyID', $currency);

            $lineTax = $dom->createElementNS(self::NS_CAC, 'cac:TaxTotal');
            $this->cbc($dom, $lineTax, 'TaxAmount', $this->money($l['tax']))->setAttribute('currencyID', $currency);
            $this->cbc($dom, $lineTax, 'RoundingAmount', $this->money($l['net'] + $l['tax']))->setAttribute('currencyID', $currency);
            $line->appendChild($lineTax);

            $item = $dom->createElementNS(self::NS_CAC, 'cac:Item');
            $this->cbc($dom, $item, 'Name', $l['name']);
            if (! empty($l['name_ar'])) {
                $arName = $this->cbc($dom, $item, 'Name', $l['name_ar']);
                $arName->setAttribute('languageID', 'ar');
            }
            $itemTaxCat = $dom->createElementNS(self::NS_CAC, 'cac:ClassifiedTaxCategory');
            $this->cbc($dom, $itemTaxCat, 'ID', $l['tax_category']);
            $this->cbc($dom, $itemTaxCat, 'Percent', $this->money($l['tax_pct']));
            $itemScheme = $dom->createElementNS(self::NS_CAC, 'cac:TaxScheme');
            $this->cbc($dom, $itemScheme, 'ID', 'VAT');
            $itemTaxCat->appendChild($itemScheme);
            $item->appendChild($itemTaxCat);
            $line->appendChild($item);

            $price = $dom->createElementNS(self::NS_CAC, 'cac:Price');
            $this->cbc($dom, $price, 'PriceAmount', $this->money($l['price']))->setAttribute('currencyID', $currency);
            $line->appendChild($price);

            $root->appendChild($line);
        }

        return $dom->saveXML();
    }

    private function buildParty(DOMDocument $dom, string $localName, array $cfg): \DOMElement
    {
        $party = $dom->createElementNS(self::NS_CAC, 'cac:' . $localName);
        $partyInner = $dom->createElementNS(self::NS_CAC, 'cac:Party');

        if (! empty($cfg['vat'])) {
            $partyId = $dom->createElementNS(self::NS_CAC, 'cac:PartyIdentification');
            $idEl = $this->cbc($dom, $partyId, 'ID', $cfg['vat']);
            $idEl->setAttribute('schemeID', 'VAT');
            $partyInner->appendChild($partyId);
        }

        $address = $dom->createElementNS(self::NS_CAC, 'cac:PostalAddress');
        if ($cfg['street']) $this->cbc($dom, $address, 'StreetName', $cfg['street']);
        if ($cfg['city']) $this->cbc($dom, $address, 'CityName', $cfg['city']);
        if ($cfg['postal']) $this->cbc($dom, $address, 'PostalZone', $cfg['postal']);
        $country = $dom->createElementNS(self::NS_CAC, 'cac:Country');
        $this->cbc($dom, $country, 'IdentificationCode', $cfg['country']);
        $address->appendChild($country);
        $partyInner->appendChild($address);

        if (! empty($cfg['vat'])) {
            $taxScheme = $dom->createElementNS(self::NS_CAC, 'cac:PartyTaxScheme');
            $this->cbc($dom, $taxScheme, 'CompanyID', $cfg['vat']);
            $scheme = $dom->createElementNS(self::NS_CAC, 'cac:TaxScheme');
            $this->cbc($dom, $scheme, 'ID', 'VAT');
            $taxScheme->appendChild($scheme);
            $partyInner->appendChild($taxScheme);
        }

        $legalEntity = $dom->createElementNS(self::NS_CAC, 'cac:PartyLegalEntity');
        $this->cbc($dom, $legalEntity, 'RegistrationName', $cfg['name']);
        $partyInner->appendChild($legalEntity);

        $party->appendChild($partyInner);
        return $party;
    }

    private function cbc(DOMDocument $dom, \DOMNode $parent, string $local, string $value): \DOMElement
    {
        $el = $dom->createElementNS(self::NS_CBC, 'cbc:' . $local, htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8'));
        $parent->appendChild($el);
        return $el;
    }

    private function money(float $value): string
    {
        return number_format($value, 2, '.', '');
    }

    private function typeCode(ZatcaInvoiceType $type): int
    {
        return match ($type) {
            ZatcaInvoiceType::Standard, ZatcaInvoiceType::Simplified => 388,
            ZatcaInvoiceType::CreditNote => 381,
            ZatcaInvoiceType::DebitNote => 383,
        };
    }
}
