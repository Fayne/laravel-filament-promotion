<?php

declare(strict_types=1);

namespace Fayne\Promotion\Conditions;

final class Operator
{
    public static function compare(int|float|string $left, string $operator, int|float|string $right): bool
    {
        return match ($operator) {
            'eq' => $left == $right, 'ne' => $left != $right, 'gt', 'after' => $left > $right,
            'gte' => $left >= $right, 'lt', 'before' => $left < $right, 'lte' => $left <= $right,
            default => false,
        };
    }
}
