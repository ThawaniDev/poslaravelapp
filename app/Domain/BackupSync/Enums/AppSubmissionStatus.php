<?php

namespace App\Domain\BackupSync\Enums;

enum AppSubmissionStatus: string
{
    case NotApplicable = 'not_applicable';
    case Submitted = 'submitted';
    case InReview = 'in_review';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Live = 'live';
}
