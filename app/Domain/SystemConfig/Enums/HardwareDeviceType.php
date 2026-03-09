<?php

namespace App\Domain\SystemConfig\Enums;

enum HardwareDeviceType: string
{
    case ReceiptPrinter = 'receipt_printer';
    case BarcodeScanner = 'barcode_scanner';
    case WeighingScale = 'weighing_scale';
    case LabelPrinter = 'label_printer';
    case CashDrawer = 'cash_drawer';
    case CardTerminal = 'card_terminal';
    case NfcReader = 'nfc_reader';
    case CustomerDisplay = 'customer_display';
}
