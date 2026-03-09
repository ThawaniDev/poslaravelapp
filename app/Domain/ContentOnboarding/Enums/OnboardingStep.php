<?php

namespace App\Domain\ContentOnboarding\Enums;

enum OnboardingStep: string
{
    case Welcome = 'welcome';
    case BusinessInfo = 'business_info';
    case BusinessType = 'business_type';
    case Tax = 'tax';
    case Hardware = 'hardware';
    case Products = 'products';
    case Staff = 'staff';
    case Review = 'review';
}
