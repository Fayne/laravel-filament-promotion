<?php

declare(strict_types=1);

namespace Fayne\Promotion\DTO;

final readonly class DiscountQuote
{
    /** @param array<string, int> $lineDiscounts */
    public function __construct(public int $itemDiscount, public int $shippingDiscount, public array $lineDiscounts = []) {}
}
