<?php

namespace App\Domain\StaffManagement\Enums;

enum StaffDocumentType: string
{
    case NationalId = 'national_id';
    case Contract = 'contract';
    case Certificate = 'certificate';
    case Visa = 'visa';
}
