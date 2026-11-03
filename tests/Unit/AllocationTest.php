<?php

declare(strict_types=1);

use Fayne\Promotion\Actions\Allocation;
use Fayne\Promotion\Actions\QuantityDiscountAction;
use Fayne\Promotion\DTO\CartItem;
use Fayne\Promotion\DTO\Context;

test('allocates remainder deterministically by line id', function (): void {
    $quote = Allocation::byWeight(10, ['line-c' => 100, 'line-a' => 100, 'line-b' => 100]);
    expect($quote->lineDiscounts)->toEqual(['line-a' => 4, 'line-b' => 3, 'line-c' => 3])
        ->and($quote->itemDiscount)->toBe(10);
    $reordered = Allocation::byWeight(10, ['line-b' => 100, 'line-a' => 100, 'line-c' => 100]);
    expect($reordered->lineDiscounts)->toEqual($quote->lineDiscounts);
});

test('quantity discount combines matching sku quantities across lines and stays within target line budgets', function (): void {
    $context = new Context(0, 'CNY', 10000, 0, [
        new CartItem('line-a', 'SKU', 1000, 1),
        new CartItem('line-b', 'SKU', 1000, 2),
        new CartItem('line-c', 'OTHER', 7000, 1),
    ]);
    $action = new QuantityDiscountAction(['skus' => ['SKU'], 'buy_quantity' => 3, 'rate' => 1000]);
    $quote = $action->quote($context, ['line-a' => 1000, 'line-b' => 1000, 'line-c' => 7000], 0);
    expect($quote->itemDiscount)->toBe(300)
        ->and(array_sum($quote->lineDiscounts))->toBe(300)
        ->and($quote->lineDiscounts)->toEqual(['line-a' => 150, 'line-b' => 150]);
    $capped = $action->quote($context, ['line-a' => 20, 'line-b' => 30, 'line-c' => 7000], 0);
    expect($capped->itemDiscount)->toBe(50)
        ->and($capped->lineDiscounts)->toEqual(['line-a' => 20, 'line-b' => 30]);
});

test('percentage uses original subtotal then caps discount to remaining item budgets', function (): void {
    $context = new Context(0, 'CNY', 10000, 0, [new CartItem('line-a', 'SKU', 10000, 1)]);
    $action = new \Fayne\Promotion\Actions\PercentageDiscountAction(['rate' => 1000]);
    expect($action->quote($context, ['line-a' => 9000], 0)->itemDiscount)->toBe(1000)
        ->and($action->quote($context, ['line-a' => 500], 0)->itemDiscount)->toBe(500);
});

test('rejects allocations and cart lines whose integer multiplication would overflow', function (): void {
    expect(fn () => Allocation::byWeight(PHP_INT_MAX, ['line-a' => PHP_INT_MAX]))
        ->toThrow(InvalidArgumentException::class, 'Discount allocation exceeds supported integer range.')
        ->and(fn () => new CartItem('line-a', 'SKU', PHP_INT_MAX, 2))
        ->toThrow(InvalidArgumentException::class);
});
