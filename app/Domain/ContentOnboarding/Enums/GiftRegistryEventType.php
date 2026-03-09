<?php

namespace App\Domain\ContentOnboarding\Enums;

enum GiftRegistryEventType: string
{
    case Wedding = 'wedding';
    case Birthday = 'birthday';
    case BabyShower = 'baby_shower';
    case Other = 'other';
}
