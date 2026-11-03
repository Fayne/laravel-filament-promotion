<?php

declare(strict_types=1);

namespace Fayne\Promotion\Filament\Resources\Coupons\Pages;

use Fayne\Promotion\Filament\Resources\CouponResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

final class ListCoupons extends ListRecords
{
    protected static string $resource = CouponResource::class;
    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
