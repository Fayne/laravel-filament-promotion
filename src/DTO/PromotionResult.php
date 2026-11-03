<?php

declare(strict_types=1);

namespace Fayne\Promotion\DTO;

final readonly class PromotionResult
{
    /** @param list<AppliedPromotion> $appliedPromotions @param list<LineAllocation> $lineAllocations */
    public function __construct(public array $appliedPromotions, public int $itemDiscount, public int $shippingDiscount, public int $totalDiscount, public int $payable, public string $currency, public array $lineAllocations = [], public int $tenantId = 0) {}
}
