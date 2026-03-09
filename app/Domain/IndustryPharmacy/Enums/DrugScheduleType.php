<?php

namespace App\Domain\IndustryPharmacy\Enums;

enum DrugScheduleType: string
{
    case Otc = 'otc';
    case PrescriptionOnly = 'prescription_only';
    case Controlled = 'controlled';
}
