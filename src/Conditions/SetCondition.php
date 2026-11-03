<?php

declare(strict_types=1);

namespace Fayne\Promotion\Conditions;

use Fayne\Promotion\Contracts\ConditionInterface;
use Fayne\Promotion\DTO\Context;

final readonly class SetCondition implements ConditionInterface
{
    public function __construct(private string $type, private array $config) {}
    public function matches(Context $context): bool
    {
        $actual = match ($this->type) {
            'user_group' => $context->userGroups,
            'product_sku' => array_map(fn ($item) => $item->sku, $context->items),
            'category' => array_merge(...array_map(fn ($item) => $item->categoryIds, $context->items ?: [[]])),
        };
        $intersects = array_intersect($actual, $this->config['values']) !== [];

        return $this->config['operator'] === 'in' ? $intersects : ! $intersects;
    }
}
