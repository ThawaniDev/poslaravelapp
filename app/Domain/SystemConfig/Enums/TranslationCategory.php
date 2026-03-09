<?php

namespace App\Domain\SystemConfig\Enums;

enum TranslationCategory: string
{
    case Ui = 'ui';
    case Receipt = 'receipt';
    case Notification = 'notification';
    case Report = 'report';
}
