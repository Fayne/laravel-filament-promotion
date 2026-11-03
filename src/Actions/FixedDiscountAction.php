<?php

declare(strict_types=1);

namespace Fayne\Promotion\Actions;

use Fayne\Promotion\Contracts\ActionInterface;
use Fayne\Promotion\DTO\Context;
use Fayne\Promotion\DTO\DiscountQuote;

final readonly class FixedDiscountAction implements ActionInterface
{
    public function __construct(private array $config) {}
    public function quote(Context $context, array $lineBudgets, int $shippingBudget): DiscountQuote
    {
        return Allocation::byWeight(min($this->config['amount'], Allocation::sum($lineBudgets)), $lineBudgets);
    }
    public function type(): string
    {
        return 'fixed_discount';
    }
}
