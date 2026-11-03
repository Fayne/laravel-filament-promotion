<?php

declare(strict_types=1);

namespace Fayne\Promotion\Actions;

use Fayne\Promotion\DTO\DiscountQuote;
use InvalidArgumentException;

final class Allocation
{
    public static function sum(array $values): int
    {
        $sum = 0;
        foreach ($values as $value) {
            if (! is_int($value) || $value < 0 || $sum > PHP_INT_MAX - $value) {
                throw new InvalidArgumentException('Amount exceeds the supported integer range.');
            }
            $sum += $value;
        }

        return $sum;
    }

    public static function percentage(int $amount, int $rate): int
    {
        if ($amount < 0 || $rate < 0 || $amount > intdiv(PHP_INT_MAX - 5000, max(1, $rate))) {
            throw new InvalidArgumentException('Discount calculation exceeds supported integer range.');
        }

        return intdiv($amount * $rate + 5000, 10000);
    }

    public static function byWeight(int $amount, array $weights): DiscountQuote
    {
        if ($amount < 0) {
            throw new InvalidArgumentException('Allocation amount must be non-negative.');
        }
        $totalWeight = 0;
        foreach ($weights as $weight) {
            if (! is_int($weight) || $weight < 0 || $totalWeight > PHP_INT_MAX - $weight) {
                throw new InvalidArgumentException('Allocation weights exceed the supported integer range.');
            }
            $totalWeight += $weight;
        }
        $amount = min($amount, $totalWeight);
        if ($amount <= 0) {
            return new DiscountQuote(0, 0);
        }
        $allocated = [];
        $remainders = [];
        $assigned = 0;
        foreach ($weights as $lineId => $weight) {
            if ($weight > 0 && $amount > intdiv(PHP_INT_MAX, $weight)) {
                throw new InvalidArgumentException('Discount allocation exceeds supported integer range.');
            }
            $numerator = $amount * $weight;
            $base = intdiv($numerator, $totalWeight);
            $allocated[$lineId] = $base;
            $remainders[$lineId] = $numerator % $totalWeight;
            $assigned += $base;
        }
        uksort($remainders, fn ($a, $b) => $remainders[$b] <=> $remainders[$a] ?: strcmp((string) $a, (string) $b));
        foreach (array_keys($remainders) as $lineId) {
            if ($assigned >= $amount) {
                break;
            }
            if ($allocated[$lineId] < $weights[$lineId]) {
                $allocated[$lineId]++;
                $assigned++;
            }
        }

        return new DiscountQuote(array_sum($allocated), 0, array_filter($allocated, fn ($value) => $value > 0));
    }
}
