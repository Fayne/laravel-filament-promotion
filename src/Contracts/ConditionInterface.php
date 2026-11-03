<?php

declare(strict_types=1);

namespace Fayne\Promotion\Contracts;

use Fayne\Promotion\DTO\Context;

interface ConditionInterface
{
    public function matches(Context $context): bool;
}
