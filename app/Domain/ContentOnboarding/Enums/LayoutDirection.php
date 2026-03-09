<?php

namespace App\Domain\ContentOnboarding\Enums;

enum LayoutDirection: string
{
    case Ltr = 'ltr';
    case Rtl = 'rtl';
    case Auto = 'auto';
}
