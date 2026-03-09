<?php

namespace App\Domain\ZatcaCompliance\Enums;

enum ZatcaSubmissionStatus: string
{
    case Pending = 'pending';
    case Submitted = 'submitted';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Warning = 'warning';
}
