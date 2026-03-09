<?php

namespace App\Domain\ContentOnboarding\Enums;

enum ThankYouAnimation: string
{
    case Confetti = 'confetti';
    case Check = 'check';
    case None = 'none';
}
