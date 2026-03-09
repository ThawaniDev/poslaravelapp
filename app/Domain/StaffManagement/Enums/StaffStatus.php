<?php

namespace App\Domain\StaffManagement\Enums;

enum StaffStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case OnLeave = 'on_leave';
}
