<?php

declare(strict_types=1);

namespace Fayne\Promotion\Conditions;

use Fayne\Promotion\Contracts\ConditionInterface;
use InvalidArgumentException;

final class ConditionFactory
{
    public static function make(array $node): ConditionInterface
    {
        $type = $node['type'] ?? '';
        $config = $node['config'] ?? [];
        if (! is_array($config)) {
            throw new InvalidArgumentException('Condition config must be an array.');
        }

        return match ($type) {
            'cart_total' => new CartTotalCondition($config),
            'user_group', 'product_sku', 'category' => new SetCondition($type, $config),
            'datetime' => new DateTimeCondition($config),
            'composite' => new CompositeCondition($config),
            default => throw new InvalidArgumentException("Unknown condition type: {$type}"),
        };
    }
}
