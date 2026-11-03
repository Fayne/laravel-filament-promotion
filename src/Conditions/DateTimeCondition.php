<?php

declare(strict_types=1);

namespace Fayne\Promotion\Conditions;

use DateTimeImmutable;
use Fayne\Promotion\Contracts\ConditionInterface;
use Fayne\Promotion\DTO\Context;

final readonly class DateTimeCondition implements ConditionInterface
{
    public function __construct(private array $config) {}
    public function matches(Context $context): bool
    {
        $now = $context->currentTime();
        $start = new DateTimeImmutable($this->config['value']);

        return match ($this->config['operator']) {
            'before' => $now < $start,
            'after' => $now > $start,
            'between' => $now >= $start && $now <= new DateTimeImmutable($this->config['end']),
        };
    }
}
