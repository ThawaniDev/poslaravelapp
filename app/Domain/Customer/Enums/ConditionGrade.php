<?php

namespace App\Domain\Customer\Enums;

enum ConditionGrade: string
{
    case BrandNew = 'new';
    case A = 'A';
    case B = 'B';
    case C = 'C';
    case D = 'D';
}
