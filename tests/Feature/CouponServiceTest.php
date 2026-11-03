<?php

declare(strict_types=1);

use Fayne\Promotion\Enums\PromotionType;
use Fayne\Promotion\Exceptions\InvalidCouponException;
use Fayne\Promotion\Models\Promotion;
use Fayne\Promotion\Services\CouponService;
use Fayne\Promotion\Tests\TestCase;

uses(TestCase::class);
function migrateCoupons(): void
{
    (require dirname(__DIR__, 2) . '/database/migrations/2026_11_02_000001_create_promotions_table.php')->up();
    (require dirname(__DIR__, 2) . '/database/migrations/2026_11_02_000002_create_coupons_table.php')->up();
    (require dirname(__DIR__, 2) . '/database/migrations/2026_11_02_000003_create_coupon_reservations_table.php')->up();
}
test('normalizes coupon codes and redeems within usage limit', function (): void {
    migrateCoupons();
    $promotion = Promotion::query()->create(['tenant_id' => 0, 'currency' => 'CNY', 'name' => 'Coupon', 'type' => PromotionType::COUPON, 'is_active' => true, 'conditions' => [], 'actions' => [['type' => 'free_shipping', 'config' => []]]]);
    $coupon = $promotion->coupons()->create(['tenant_id' => 0, 'currency' => 'CNY', 'code' => 'save10', 'is_active' => true, 'usage_limit' => 1, 'used_count' => 0]);
    $service = app(CouponService::class);
    expect($coupon->fresh()->code)->toBe('SAVE10')->and($service->validate(0, 'CNY', ' save10 ')->id)->toBe($coupon->id);
    $reservation = $service->reserve(0, 'CNY', 'save10', 'order', 'order-1', 'key-1', now()->addMinutes(10));
    $service->commit(0, $reservation->id);
    expect($coupon->fresh()->used_count)->toBe(1)->and(fn () => $service->validate(0, 'CNY', 'SAVE10'))->toThrow(InvalidCouponException::class);
});

test('reservation requests are idempotent and conflicting reuse is rejected', function (): void {
    migrateCoupons();
    $promotion = Promotion::query()->create(['tenant_id' => 0, 'currency' => 'CNY', 'name' => 'Reserved', 'type' => PromotionType::COUPON, 'is_active' => true, 'conditions' => [], 'actions' => [['type' => 'free_shipping', 'config' => []]]]);
    $promotion->coupons()->create(['tenant_id' => 0, 'currency' => 'CNY', 'code' => 'LIMITED', 'is_active' => true, 'usage_limit' => 1]);
    $service = app(CouponService::class);
    $expiresAt = now()->addMinutes(5);
    $first = $service->reserve(0, 'CNY', 'LIMITED', 'checkout', 'cart-10', 'idempotency-10', $expiresAt, 11);
    expect($service->reserve(0, 'CNY', 'LIMITED', 'checkout', 'cart-10', 'idempotency-10', $expiresAt, 11)->id)->toBe($first->id)
        ->and(fn () => $service->reserve(0, 'CNY', 'LIMITED', 'checkout', 'cart-10', 'another-key', $expiresAt, 11))
        ->toThrow(\Fayne\Promotion\Exceptions\ReservationConflictException::class);
    $service->release(0, $first->id);
});

test('reservation operations require the trusted tenant and currency', function (): void {
    migrateCoupons();
    $promotion = Promotion::query()->create(['name' => 'Scoped', 'type' => PromotionType::COUPON, 'conditions' => [], 'actions' => []]);
    $firstCoupon = $promotion->coupons()->create(['code' => 'SAME']);
    config(['filament-cms.tenancy.enabled' => true]);
    Illuminate\Support\Facades\Context::add('filament-cms.tenant_id', 1);
    $promotion = Promotion::query()->create(['name' => 'Scoped', 'type' => PromotionType::COUPON, 'conditions' => [], 'actions' => []]);
    $secondCoupon = $promotion->coupons()->create(['code' => 'SAME']);
    $service = app(CouponService::class);
    $contextKey = 'filament-cms.tenant_id';
    Illuminate\Support\Facades\Context::add($contextKey, 0);
    $first = $service->reserve(0, 'CNY', 'SAME', 'checkout', 'tenant-0-cart', 'tenant-0-key', now()->addMinutes(5));
    expect($service->findValid(0, 'CNY', 'SAME')?->id)->toBe($firstCoupon->id)
        ->and(fn () => $service->findValid(0, 'USD', 'SAME'))->toThrow(InvalidArgumentException::class);

    Illuminate\Support\Facades\Context::add($contextKey, 1);
    $service->reserve(1, 'CNY', 'SAME', 'checkout', 'tenant-1-cart', 'tenant-1-key', now()->addMinutes(5));
    expect($service->findValid(1, 'CNY', 'SAME')?->id)->toBe($secondCoupon->id)
        ->and(fn () => $service->commit(0, $first->id))->toThrow(InvalidArgumentException::class)
        ->and(fn () => $service->release(0, $first->id))->toThrow(InvalidArgumentException::class)
        ->and(fn () => $service->commit(1, $first->id))->toThrow(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

    Illuminate\Support\Facades\Context::add($contextKey, 0);
    $service->commit(0, $first->id);
    $service->commit(0, $first->id);
    expect($firstCoupon->fresh()->used_count)->toBe(1)
        ->and(fn () => $service->release(0, $first->id))
        ->toThrow(\Fayne\Promotion\Exceptions\ReservationConflictException::class);
});

test('deletion cannot remove a coupon with reservation history or its promotion', function (): void {
    migrateCoupons();
    $promotion = Promotion::query()->create([
        'name' => 'Protected', 'type' => PromotionType::COUPON,
        'conditions' => [], 'actions' => [['type' => 'free_shipping', 'config' => []]],
    ]);
    $coupon = $promotion->coupons()->create(['code' => 'PROTECTED', 'is_active' => true]);
    $service = app(CouponService::class);
    $reservation = $service->reserve(0, 'CNY', 'PROTECTED', 'order', 'protected', 'protected', now()->addMinutes(10));

    (require dirname(__DIR__, 2) . '/database/migrations/2026_11_03_000001_protect_coupon_history_foreign_keys.php')->up();

    expect(fn () => $coupon->delete())->toThrow(\Illuminate\Database\QueryException::class)
        ->and(fn () => $promotion->delete())->toThrow(\Illuminate\Database\QueryException::class);

    $service->commit(0, $reservation->id);
    expect(fn () => $coupon->delete())->toThrow(\Illuminate\Database\QueryException::class)
        ->and($coupon->fresh()->used_count)->toBe(1);
});

test('expired reservations consume quota until explicitly released', function (): void {
    migrateCoupons();
    $promotion = Promotion::query()->create(['name' => 'Expiring', 'type' => PromotionType::COUPON, 'conditions' => [], 'actions' => []]);
    $promotion->coupons()->create(['code' => 'EXPIRING', 'usage_limit' => 1]);
    $service = app(CouponService::class);
    $expires = now()->addMinute();
    $service->reserve(0, 'CNY', 'EXPIRING', 'checkout', 'first', 'first', $expires);
    expect($service->findValid(0, 'CNY', 'EXPIRING', $expires->copy()->addSecond()))->toBeNull()
        ->and($service->releaseExpired(0, $expires->copy()->addSecond()))->toBe(1)
        ->and($service->findValid(0, 'CNY', 'EXPIRING'))->not->toBeNull();
});
