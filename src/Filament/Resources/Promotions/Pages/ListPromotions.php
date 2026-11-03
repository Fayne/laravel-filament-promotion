<?php

declare(strict_types=1);

namespace Fayne\Promotion\Filament\Resources\Promotions\Pages;

use Fayne\Promotion\Filament\Resources\PromotionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

final class ListPromotions extends ListRecords
{
    protected static string $resource = PromotionResource::class;
    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
