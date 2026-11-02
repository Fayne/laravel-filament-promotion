<?php

declare(strict_types=1);
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

uses(TestCase::class);
test('package migrations create promotion and coupon tables', function (): void {
    (require dirname(__DIR__, 2) . '/database/migrations/2026_11_02_000001_create_promotions_table.php')->up();
    (require dirname(__DIR__, 2) . '/database/migrations/2026_11_02_000002_create_coupons_table.php')->up();

    expect(Schema::hasTable('promotions'))->toBeTrue()
        ->and(Schema::hasTable('coupons'))->toBeTrue()
        ->and(Schema::hasColumn('promotions', 'conditions'))->toBeTrue()
        ->and(Schema::hasColumn('coupons', 'used_count'))->toBeTrue();
});

test('models persist uuids and casts', function (): void {
    (require dirname(__DIR__, 2) . '/database/migrations/2026_11_02_000001_create_promotions_table.php')->up();
    (require dirname(__DIR__, 2) . '/database/migrations/2026_11_02_000002_create_coupons_table.php')->up();

    $promotion = \Fayne\Promotion\Models\Promotion::query()->create([
        'name' => 'Welcome', 'type' => \Fayne\Promotion\Enums\PromotionType::AUTO,
        'conditions' => [], 'actions' => [], 'is_active' => true,
    ]);
    $coupon = $promotion->coupons()->create(['code' => 'WELCOME', 'used_count' => 0]);

    expect($promotion->id)->toBeString()
        ->and($promotion->is_active)->toBeTrue()
        ->and($promotion->type)->toBe(\Fayne\Promotion\Enums\PromotionType::AUTO)
        ->and($coupon->id)->toBeString()
        ->and($coupon->used_count)->toBe(0);
});
