<?php

declare(strict_types=1);

namespace Fayne\Promotion\Enums;

enum CouponReservationStatus: string
{
    case RESERVED = 'reserved';
    case COMMITTED = 'committed';
    case RELEASED = 'released';
}
