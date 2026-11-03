<?php

declare(strict_types=1);

namespace Fayne\Promotion\DTO;

final readonly class CartItem
{
    /** @param list<string> $categoryIds */
    public function __construct(public string $lineId, public string $sku, public int $unitPrice, public int $quantity, public array $categoryIds = [])
    {
        if ($lineId === '' || $unitPrice < 0 || $quantity < 1 || $unitPrice > intdiv(PHP_INT_MAX, $quantity)) {
            throw new \InvalidArgumentException('Cart line ID is required, price must be non-negative, and quantity positive.');
        }
    }
}
