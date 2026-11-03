<?php

declare(strict_types=1);

namespace Fayne\Promotion\Conditions;

use Fayne\Promotion\Contracts\ConditionInterface;
use Fayne\Promotion\DTO\Context;

final readonly class CompositeCondition implements ConditionInterface
{
    public function __construct(private array $config) {}
    public function matches(Context $context): bool
    {
        $children = array_map(fn (array $node) => ConditionFactory::make($node), $this->config['conditions']);

        foreach ($children as $condition) {
            $matches = $condition->matches($context);
            if ($this->config['logic'] === 'or' && $matches) {
                return true;
            }
            if ($this->config['logic'] === 'and' && ! $matches) {
                return false;
            }
        }

        return $this->config['logic'] === 'and';
    }
}
