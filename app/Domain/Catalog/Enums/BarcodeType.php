<?php

namespace App\Domain\Catalog\Enums;

enum BarcodeType: string
{
    case CODE128 = 'CODE128';
    case EAN13 = 'EAN13';
    case EAN8 = 'EAN8';
    case QR = 'QR';
    case Code39 = 'Code39';

    /**
     * Case-insensitive lookup.
     */
    public static function fromInsensitive(string $value): self
    {
        foreach (self::cases() as $case) {
            if (strcasecmp($case->value, $value) === 0) {
                return $case;
            }
        }

        throw new \ValueError("\"$value\" is not a valid backing value for enum " . self::class);
    }
}
