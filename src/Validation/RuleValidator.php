<?php

declare(strict_types=1);

namespace Fayne\Promotion\Validation;

use InvalidArgumentException;

final class RuleValidator
{
    private const CONDITION_TYPES = ['cart_total', 'user_group', 'product_sku', 'category', 'datetime'];
    private const ACTIONS = ['fixed_discount', 'percentage_discount', 'free_shipping', 'quantity_discount'];

    public function validate(array $conditions, array $actions): void
    {
        if (count($actions) < 1 || count($actions) > 20) {
            throw new InvalidArgumentException('A promotion must have between 1 and 20 actions.');
        }

        $leafCount = 0;
        foreach ($conditions as $condition) {
            $this->validateCondition($condition, 0, $leafCount);
        }
        if ($leafCount > 50) {
            throw new InvalidArgumentException('A promotion cannot have more than 50 conditions.');
        }

        foreach ($actions as $action) {
            $this->validateAction($action);
        }
    }

    private function validateCondition(array $node, int $depth, int &$leafCount): void
    {
        $type = $node['type'] ?? null;
        $config = $node['config'] ?? null;
        if (! is_array($config)) {
            throw new InvalidArgumentException('Condition config must be an array.');
        }
        if ($type === 'composite') {
            if ($depth >= 2) {
                throw new InvalidArgumentException('Condition groups may be nested at most two levels.');
            }
            if (! in_array($config['logic'] ?? null, ['and', 'or'], true) || empty($config['conditions']) || ! is_array($config['conditions'])) {
                throw new InvalidArgumentException('A condition group requires logic and at least one child.');
            }
            $this->assertKeys($config, ['logic', 'conditions']);
            foreach ($config['conditions'] as $child) {
                if (! is_array($child)) {
                    throw new InvalidArgumentException('Each condition must be an object.');
                }
                $this->validateCondition($child, $depth + 1, $leafCount);
            }

            return;
        }
        if (! in_array($type, self::CONDITION_TYPES, true)) {
            throw new InvalidArgumentException('Unknown condition type.');
        }
        $leafCount++;
        $this->validateLeafCondition($type, $config);
    }

    private function validateLeafCondition(string $type, array $config): void
    {
        $operator = $config['operator'] ?? null;
        $allowed = match ($type) {
            'cart_total' => ['eq', 'ne', 'gt', 'gte', 'lt', 'lte'],
            'user_group', 'product_sku', 'category' => ['in', 'not_in'],
            'datetime' => ['before', 'after', 'between'],
        };
        if (! in_array($operator, $allowed, true)) {
            throw new InvalidArgumentException('Invalid condition operator.');
        }
        $this->assertKeys($config, match ($type) {
            'cart_total' => ['operator', 'value'],
            'user_group', 'product_sku', 'category' => ['operator', 'values'],
            'datetime' => $operator === 'between' ? ['operator', 'value', 'end'] : ['operator', 'value'],
        });
        if ($type === 'cart_total' && (! isset($config['value']) || ! is_int($config['value']) || $config['value'] < 0)) {
            throw new InvalidArgumentException('Cart total value must be a non-negative integer.');
        }
        if (in_array($type, ['user_group', 'product_sku', 'category'], true) && (! is_array($config['values'] ?? null) || $config['values'] === [] || array_filter($config['values'], fn ($value): bool => ! is_string($value) || trim($value) === '') !== [])) {
            throw new InvalidArgumentException('Set conditions require at least one value.');
        }
        if ($type === 'datetime') {
            if (! is_string($config['value'] ?? null) || trim($config['value']) === '' || ($operator === 'between' && (! is_string($config['end'] ?? null) || trim($config['end']) === ''))) {
                throw new InvalidArgumentException('Date conditions require valid date values.');
            }
            try {
                $start = new \DateTimeImmutable($config['value']);
                if ($operator === 'between') {
                    $end = new \DateTimeImmutable($config['end']);
                    if ($end < $start) {
                        throw new InvalidArgumentException('Date range end must be after its start.');
                    }
                }
            } catch (\Exception $exception) {
                throw new InvalidArgumentException('Date conditions require valid date values.', previous: $exception);
            }
        }
    }

    private function validateAction(array $action): void
    {
        $type = $action['type'] ?? null;
        $config = $action['config'] ?? null;
        if (! in_array($type, self::ACTIONS, true) || ! is_array($config)) {
            throw new InvalidArgumentException('Unknown action or invalid action config.');
        }
        $this->assertKeys($config, match ($type) {
            'fixed_discount' => ['amount'],
            'percentage_discount' => ['rate', 'max_discount'],
            'free_shipping' => [],
            'quantity_discount' => ['skus', 'buy_quantity', 'rate', 'max_discount'],
        });
        if ($type === 'fixed_discount' && (! is_int($config['amount'] ?? null) || $config['amount'] <= 0)) {
            throw new InvalidArgumentException('Fixed discount amount must be a positive integer.');
        }
        if ($type === 'percentage_discount' && (! is_int($config['rate'] ?? null) || $config['rate'] < 1 || $config['rate'] > 10000)) {
            throw new InvalidArgumentException('Percentage rate must be between 1 and 10000 basis points.');
        }
        if ($type === 'quantity_discount') {
            $skus = $config['skus'] ?? null;
            if (! is_array($skus) || $skus === [] || array_filter($skus, fn ($sku): bool => ! is_string($sku) || trim($sku) === '') !== [] || count($skus) !== count(array_unique($skus)) || ! is_int($config['buy_quantity'] ?? null) || $config['buy_quantity'] < 1 || ! is_int($config['rate'] ?? null) || $config['rate'] < 1 || $config['rate'] > 10000) {
                throw new InvalidArgumentException('Quantity discount requires unique SKUs, a positive quantity, and a valid rate.');
            }
        }
        if (isset($config['max_discount']) && (! is_int($config['max_discount']) || $config['max_discount'] < 1)) {
            throw new InvalidArgumentException('Maximum discount must be a positive integer.');
        }
    }

    private function assertKeys(array $config, array $allowed): void
    {
        if (array_diff(array_keys($config), $allowed) !== []) {
            throw new InvalidArgumentException('Rule config contains unsupported fields.');
        }
    }
}
