<?php

declare(strict_types=1);

namespace Fayne\Promotion\Actions;

use Fayne\Promotion\Contracts\ActionInterface;
use InvalidArgumentException;

final class ActionFactory
{
    public static function make(array $node): ActionInterface
    {
        $type = $node['type'] ?? '';
        $config = $node['config'] ?? [];

        return match ($type) {
            'fixed_discount' => new FixedDiscountAction($config),
            'percentage_discount' => new PercentageDiscountAction($config),
            'free_shipping' => new FreeShippingAction($config),
            'quantity_discount' => new QuantityDiscountAction($config),
            default => throw new InvalidArgumentException("Unknown action type: {$type}"),
        };
    }
}
