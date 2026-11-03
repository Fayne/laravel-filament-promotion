<?php

declare(strict_types=1);

namespace Fayne\Promotion\Engine;

use Fayne\Promotion\Actions\ActionFactory;
use Fayne\Promotion\Conditions\ConditionFactory;
use Fayne\Promotion\DTO\AppliedPromotion;
use Fayne\Promotion\DTO\Context;
use Fayne\Promotion\DTO\LineAllocation;
use Fayne\Promotion\DTO\PromotionResult;
use Fayne\Promotion\Enums\PromotionType;
use Fayne\Promotion\Models\Promotion;
use Fayne\Promotion\Services\CouponService;
use Illuminate\Database\Eloquent\Builder;

final readonly class PromotionEngine
{
    public function __construct(private CouponService $coupons) {}

    public function calculate(Context $context): PromotionResult
    {
        app(\Fayne\Promotion\Services\TenantContext::class)->assertMatches($context->tenantId, $context->currency);
        $coupon = $context->couponCode ? $this->coupons->validate($context->tenantId, $context->currency, $context->couponCode, $context->currentTime()) : null;
        $promotions = Promotion::query()->where('tenant_id', $context->tenantId)->where('currency', $context->currency)->where('is_active', true)
            ->where(fn (Builder $query) => $query->whereNull('starts_at')->orWhere('starts_at', '<=', $context->currentTime()))
            ->where(fn (Builder $query) => $query->whereNull('ends_at')->orWhere('ends_at', '>=', $context->currentTime()))
            ->where(fn (Builder $query) => $query->where('type', PromotionType::AUTO->value)->orWhere(fn (Builder $couponQuery) => $coupon ? $couponQuery->where('type', PromotionType::COUPON->value)->whereKey($coupon->promotion_id) : $couponQuery->whereRaw('1 = 0')))
            ->orderByDesc('priority')->orderBy('id')->get();
        $lineBudgets = [];
        foreach ($context->items as $item) {
            $lineBudgets[$item->lineId] = $item->unitPrice * $item->quantity;
        }
        $lineDiscounts = array_fill_keys(array_keys($lineBudgets), 0);
        $shippingRemaining = $context->shipping;
        $applied = [];
        $itemTotal = 0;
        foreach ($promotions as $promotion) {
            $matches = true;
            foreach ($promotion->conditions ?? [] as $node) {
                if (! ConditionFactory::make($node)->matches($context)) {
                    $matches = false;
                    break;
                }
            }
            if (! $matches) {
                continue;
            }
            $promotionDiscount = 0;
            foreach ($promotion->actions ?? [] as $actionNode) {
                $budgets = [];
                foreach ($lineBudgets as $lineId => $amount) {
                    $budgets[$lineId] = $amount - $lineDiscounts[$lineId];
                }
                $quote = ActionFactory::make($actionNode)->quote($context, $budgets, $shippingRemaining);
                foreach ($quote->lineDiscounts as $lineId => $discount) {
                    $lineDiscounts[$lineId] += $discount;
                }
                $itemTotal += $quote->itemDiscount;
                $shippingRemaining -= $quote->shippingDiscount;
                $promotionDiscount += $quote->itemDiscount + $quote->shippingDiscount;
                if ($quote->itemDiscount + $quote->shippingDiscount > 0) {
                    $applied[] = new AppliedPromotion($promotion->id, $promotion->name, $actionNode['type'], $quote->itemDiscount, $quote->shippingDiscount, $this->allocations($context, $quote->lineDiscounts));
                }
            }
            if ($promotion->stop_processing && $promotionDiscount > 0) {
                break;
            }
        }
        $allocations = $this->allocations($context, $lineDiscounts);
        $total = $itemTotal + ($context->shipping - $shippingRemaining);

        return new PromotionResult($applied, $itemTotal, $context->shipping - $shippingRemaining, $total, $context->subtotal + $context->shipping - $total, $context->currency, $allocations, $context->tenantId);
    }

    private function allocations(Context $context, array $discounts): array
    {
        $allocations = [];
        foreach ($context->items as $item) {
            $discount = $discounts[$item->lineId] ?? 0;
            $base = intdiv($discount, $item->quantity);
            $remainder = $discount % $item->quantity;
            $units = [];
            if ($item->quantity - $remainder > 0) {
                $units[] = ['net_unit_amount' => $item->unitPrice - $base, 'quantity' => $item->quantity - $remainder];
            }
            if ($remainder > 0) {
                $units[] = ['net_unit_amount' => $item->unitPrice - $base - 1, 'quantity' => $remainder];
            }
            $allocations[] = new LineAllocation($item->lineId, $item->quantity, $item->unitPrice * $item->quantity, $discount, $item->unitPrice * $item->quantity - $discount, $units);
        }

        return $allocations;
    }
}
