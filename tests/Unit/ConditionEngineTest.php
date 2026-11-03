<?php

declare(strict_types=1);

use Fayne\Promotion\Conditions\ConditionFactory;
use Fayne\Promotion\DTO\CartItem;
use Fayne\Promotion\DTO\Context;
use Fayne\Promotion\Validation\RuleValidator;

test('conditions compare totals and evaluate nested groups', function (): void {
    $context = new Context(0, 'CNY', 10000, 1000, [new CartItem('line-a', 'SKU-A', 5000, 2, ['books'])], ['vip'], now: new DateTimeImmutable('2026-01-01T12:00:00Z'));
    $condition = ConditionFactory::make(['type' => 'composite', 'config' => ['logic' => 'or', 'conditions' => [
        ['type' => 'cart_total', 'config' => ['operator' => 'gte', 'value' => 9000]],
        ['type' => 'user_group', 'config' => ['operator' => 'in', 'values' => ['staff']]],
    ]]]);
    expect($condition->matches($context))->toBeTrue();
});

test('rejects more than fifty condition leaves', function (): void {
    $conditions = array_fill(0, 51, ['type' => 'cart_total', 'config' => ['operator' => 'gte', 'value' => 0]]);
    expect(fn () => app(RuleValidator::class)->validate($conditions, [['type' => 'free_shipping', 'config' => []]]))->toThrow(InvalidArgumentException::class);
});
