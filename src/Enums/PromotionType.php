<?php

declare(strict_types=1);

namespace Fayne\Promotion\Enums;

enum PromotionType: string
{
    case AUTO = 'auto';
    case COUPON = 'coupon';
}
