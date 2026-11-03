<?php

declare(strict_types=1);

namespace Fayne\Promotion\DTO;

final readonly class AppliedPromotion
{
    /** @param list<LineAllocation> $lineAllocations */
    public function __construct(public string $promotionId, public string $name, public string $actionType, public int $itemDiscount, public int $shippingDiscount, public array $lineAllocations = []) {}
}
