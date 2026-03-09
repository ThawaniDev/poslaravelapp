<?php

namespace App\Domain\ProviderRegistration\Enums;

enum ProviderRegistrationStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
}
