<?php

namespace App\Domain\ContentOnboarding\Enums;

enum MarketplaceListingStatus: string
{
    case Draft = 'draft';
    case PendingReview = 'pending_review';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Suspended = 'suspended';
}
