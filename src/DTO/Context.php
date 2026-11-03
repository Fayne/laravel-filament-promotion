<?php

declare(strict_types=1);

namespace Fayne\Promotion\DTO;

use DateTimeImmutable;

final readonly class Context
{
    /** @param list<CartItem> $items @param list<string> $userGroups */
    public function __construct(public int $tenantId, public string $currency, public int $subtotal, public int $shipping, public array $items, public array $userGroups = [], public ?string $couponCode = null, public ?DateTimeImmutable $now = null, public ?int $customerId = null)
    {
        if ($tenantId < 0 || $currency === '' || $subtotal < 0 || $shipping < 0) {
            throw new \InvalidArgumentException('Tenant, currency, and non-negative cart amounts are required.');
        }
        if (count(array_unique(array_map(fn (CartItem $item) => $item->lineId, $items))) !== count($items)) {
            throw new \InvalidArgumentException('Cart line IDs must be unique.');
        }
        $itemsTotal = 0;
        foreach ($items as $item) {
            $lineAmount = $item->unitPrice * $item->quantity;
            if ($itemsTotal > PHP_INT_MAX - $lineAmount) {
                throw new \InvalidArgumentException('Cart subtotal exceeds the supported integer range.');
            }
            $itemsTotal += $lineAmount;
        }
        if ($itemsTotal !== $subtotal) {
            throw new \InvalidArgumentException('Subtotal must equal the sum of cart item amounts.');
        }
        if ($subtotal > PHP_INT_MAX - $shipping) {
            throw new \InvalidArgumentException('Cart payable amount exceeds the supported integer range.');
        }
    }

    public function currentTime(): DateTimeImmutable
    {
        return $this->now ?? new DateTimeImmutable;
    }
}
