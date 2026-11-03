<?php

declare(strict_types=1);

use Fayne\Promotion\DTO\CartItem;
use Fayne\Promotion\DTO\Context;
use Fayne\Promotion\Engine\PromotionEngine;
use Fayne\Promotion\Enums\PromotionType;
use Fayne\Promotion\Models\Promotion;
use Fayne\Promotion\Tests\TestCase;

uses(TestCase::class);
function migratePromotionTables(): void
{
    (require dirname(__DIR__, 2) . '/database/migrations/2026_11_02_000001_create_promotions_table.php')->up();
    (require dirname(__DIR__, 2) . '/database/migrations/2026_11_02_000002_create_coupons_table.php')->up();
    (require dirname(__DIR__, 2) . '/database/migrations/2026_11_02_000003_create_coupon_reservations_table.php')->up();
}
test('calculates sequential item and shipping discounts within budgets', function (): void {
    migratePromotionTables();
    Promotion::query()->create(['tenant_id' => 0, 'currency' => 'CNY', 'name' => 'Fixed', 'type' => PromotionType::AUTO, 'is_active' => true, 'conditions' => [], 'actions' => [['type' => 'fixed_discount', 'config' => ['amount' => 1000]]]]);
    Promotion::query()->create(['tenant_id' => 0, 'currency' => 'CNY', 'name' => 'Ten percent', 'type' => PromotionType::AUTO, 'is_active' => true, 'priority' => 1, 'conditions' => [], 'actions' => [['type' => 'percentage_discount', 'config' => ['rate' => 1000]]]]);
    Promotion::query()->create(['tenant_id' => 0, 'currency' => 'CNY', 'name' => 'Shipping', 'type' => PromotionType::AUTO, 'is_active' => true, 'priority' => 0, 'conditions' => [], 'actions' => [['type' => 'free_shipping', 'config' => []]]]);
    $result = app(PromotionEngine::class)->calculate(new Context(0, 'CNY', 10000, 1000, [new CartItem('line-a', 'A', 10000, 1)]));
    expect($result->itemDiscount)->toBe(2000)->and($result->shippingDiscount)->toBe(1000)->and($result->payable)->toBe(8000);
});
