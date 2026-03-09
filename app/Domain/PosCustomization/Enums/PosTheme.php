<?php

namespace App\Domain\PosCustomization\Enums;

enum PosTheme: string
{
    case Light = 'light';
    case Dark = 'dark';
    case Custom = 'custom';
}
