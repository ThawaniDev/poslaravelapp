<?php

namespace App\Domain\ContentOnboarding\Enums;

enum SignageTemplateType: string
{
    case MenuBoard = 'menu_board';
    case PromoSlideshow = 'promo_slideshow';
    case QueueDisplay = 'queue_display';
    case InfoBoard = 'info_board';
}
