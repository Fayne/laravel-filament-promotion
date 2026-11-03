<?php

declare(strict_types=1);

namespace Fayne\Promotion\Contracts;

use Fayne\Promotion\DTO\Context;
use Fayne\Promotion\DTO\DiscountQuote;

interface ActionInterface
{
    public function quote(Context $context, array $lineBudgets, int $shippingBudget): DiscountQuote;
    public function type(): string;
}
