<?php

declare(strict_types=1);

namespace Fayne\Promotion\Actions;

use Fayne\Promotion\Contracts\ActionInterface;
use Fayne\Promotion\DTO\Context;
use Fayne\Promotion\DTO\DiscountQuote;
use InvalidArgumentException;

final readonly class QuantityDiscountAction implements ActionInterface
{
    public function __construct(private array $config) {}
    public function quote(Context $context, array $lineBudgets, int $shippingBudget): DiscountQuote
    {
        $selected = [];
        $base = 0;
        foreach (array_unique($this->config['skus']) as $sku) {
            $lines = array_values(array_filter($context->items, fn ($item) => $item->sku === $sku));
            if (count(array_unique(array_map(fn ($item) => $item->unitPrice, $lines))) > 1) {
                throw new InvalidArgumentException('A SKU cannot have multiple unit prices in the same cart.');
            }
            $sets = intdiv(Allocation::sum(array_map(fn ($item) => $item->quantity, $lines)), $this->config['buy_quantity']);
            $eligibleUnits = $sets * $this->config['buy_quantity'];
            foreach ($lines as $line) {
                if ($eligibleUnits <= 0) {
                    break;
                }
                $units = min($eligibleUnits, $line->quantity);
                $eligibleUnits -= $units;
                $eligibleAmount = $line->unitPrice * $units;
                if ($base > PHP_INT_MAX - $eligibleAmount) {
                    throw new InvalidArgumentException('Quantity discount exceeds supported integer range.');
                }
                $base += $eligibleAmount;
                $selected[$line->lineId] = min($lineBudgets[$line->lineId] ?? 0, $eligibleAmount);
            }
        }
        $amount = min(Allocation::percentage($base, $this->config['rate']), $this->config['max_discount'] ?? PHP_INT_MAX, Allocation::sum($selected));

        return Allocation::byWeight($amount, $selected);
    }
    public function type(): string
    {
        return 'quantity_discount';
    }
}
