<?php

declare(strict_types=1);

use Fayne\Promotion\Validation\RuleValidator;

test('accepts valid nested conditions and actions', function (): void {
    expect(fn () => app(RuleValidator::class)->validate([
        ['type' => 'cart_total', 'config' => ['operator' => 'gte', 'value' => 1000]],
        ['type' => 'composite', 'config' => ['logic' => 'or', 'conditions' => [
            ['type' => 'user_group', 'config' => ['operator' => 'in', 'values' => ['vip']]],
        ]]],
    ], [['type' => 'fixed_discount', 'config' => ['amount' => 100]]]))->not->toThrow(Throwable::class);
});

test('rejects unsupported condition types and excessive nesting', function (): void {
    expect(fn () => app(RuleValidator::class)->validate([['type' => 'unknown', 'config' => []]], [['type' => 'free_shipping', 'config' => []]]))->toThrow(InvalidArgumentException::class);
    $nested = ['type' => 'composite', 'config' => ['logic' => 'and', 'conditions' => [
        ['type' => 'composite', 'config' => ['logic' => 'and', 'conditions' => [
            ['type' => 'composite', 'config' => ['logic' => 'and', 'conditions' => []]],
        ]]],
    ]]];
    expect(fn () => app(RuleValidator::class)->validate([$nested], [['type' => 'free_shipping', 'config' => []]]))->toThrow(InvalidArgumentException::class);
});

test('rejects missing date values, malformed set members and duplicate target SKUs', function (): void {
    $validator = app(RuleValidator::class);
    $actions = [['type' => 'free_shipping', 'config' => []]];

    expect(fn () => $validator->validate([['type' => 'datetime', 'config' => ['operator' => 'before']]], $actions))
        ->toThrow(InvalidArgumentException::class)
        ->and(fn () => $validator->validate([['type' => 'product_sku', 'config' => ['operator' => 'in', 'values' => ['SKU-A', 1]]]], $actions))
        ->toThrow(InvalidArgumentException::class)
        ->and(fn () => $validator->validate([], [['type' => 'quantity_discount', 'config' => ['skus' => ['SKU-A', 'SKU-A'], 'buy_quantity' => 2, 'rate' => 1000]]]))
        ->toThrow(InvalidArgumentException::class);
});
