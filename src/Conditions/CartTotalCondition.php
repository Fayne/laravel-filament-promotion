<?php

declare(strict_types=1);

namespace Fayne\Promotion\Conditions;

use Fayne\Promotion\Contracts\ConditionInterface;
use Fayne\Promotion\DTO\Context;

final readonly class CartTotalCondition implements ConditionInterface
{
    public function __construct(private array $config) {}
    public function matches(Context $context): bool
    {
        return Operator::compare($context->subtotal, $this->config['operator'], $this->config['value']);
    }
}
