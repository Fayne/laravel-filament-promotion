<?php

declare(strict_types=1);

namespace Fayne\Promotion\DTO;

final readonly class LineAllocation
{
    /** @param list<array{net_unit_amount: int, quantity: int}> $units */
    public function __construct(public string $lineId, public int $quantity, public int $originalAmount, public int $discount, public int $netAmount, public array $units) {}
}
