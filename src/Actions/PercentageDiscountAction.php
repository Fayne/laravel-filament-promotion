<?php

declare(strict_types=1);

namespace Fayne\Promotion\Actions;

use Fayne\Promotion\Contracts\ActionInterface;
use Fayne\Promotion\DTO\Context;
use Fayne\Promotion\DTO\DiscountQuote;

final readonly class PercentageDiscountAction implements ActionInterface
{
    public function __construct(private array $config) {}
    public function quote(Context $context, array $lineBudgets, int $shippingBudget): DiscountQuote
    {
        $total = Allocation::sum($lineBudgets);
        $amount = Allocation::percentage($context->subtotal, $this->config['rate']);
        $amount = min($amount, $this->config['max_discount'] ?? PHP_INT_MAX, $total);

        return Allocation::byWeight($amount, $lineBudgets);
    }
    public function type(): string
    {
        return 'percentage_discount';
    }
}
