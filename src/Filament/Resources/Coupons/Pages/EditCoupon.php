<?php

declare(strict_types=1);

namespace Fayne\Promotion\Filament\Resources\Coupons\Pages;

use Fayne\Promotion\Filament\Resources\CouponResource;
use Filament\Resources\Pages\EditRecord;

final class EditCoupon extends EditRecord
{
    protected static string $resource = CouponResource::class;
}
