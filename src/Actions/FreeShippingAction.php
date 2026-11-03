<?php

declare(strict_types=1);

namespace Fayne\Promotion\Actions;

use Fayne\Promotion\Contracts\ActionInterface;
use Fayne\Promotion\DTO\Context;
use Fayne\Promotion\DTO\DiscountQuote;

final class FreeShippingAction implements ActionInterface
{
    public function __construct(array $config = []) {}
    public function quote(Context $context, array $lineBudgets, int $shippingBudget): DiscountQuote
    {
        return new DiscountQuote(0, $shippingBudget);
    }
    public function type(): string
    {
        return 'free_shipping';
    }
}
