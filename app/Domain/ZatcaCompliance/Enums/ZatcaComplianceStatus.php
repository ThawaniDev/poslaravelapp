<?php

namespace App\Domain\ZatcaCompliance\Enums;

enum ZatcaComplianceStatus: string
{
    case Pending = 'pending';
    case Submitted = 'submitted';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
}
