<?php

namespace App\Domain\Hardware\Enums;

enum ImplementationFeeType: string
{
    case Setup = 'setup';
    case Training = 'training';
    case CustomDev = 'custom_dev';
}
