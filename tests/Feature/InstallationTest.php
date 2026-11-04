<?php

declare(strict_types=1);
use Illuminate\Support\Facades\Schema;
use Fayne\Promotion\Tests\TestCase;

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
        'tenant_id' => 99, 'name' => 'Welcome', 'type' => \Fayne\Promotion\Enums\PromotionType::AUTO,
        'conditions' => [], 'actions' => [], 'is_active' => true,
    ]);
    $couponPromotion = \Fayne\Promotion\Models\Promotion::query()->create([
        'name' => 'Coupon promotion', 'type' => \Fayne\Promotion\Enums\PromotionType::COUPON,
        'conditions' => [], 'actions' => [['type' => 'free_shipping', 'config' => []]],
    ]);
    $coupon = $couponPromotion->coupons()->create(['tenant_id' => 99, 'code' => 'WELCOME', 'used_count' => 0]);

    expect($promotion->id)->toBeString()
        ->and($promotion->is_active)->toBeTrue()
        ->and($promotion->type)->toBe(\Fayne\Promotion\Enums\PromotionType::AUTO)
        ->and($coupon->id)->toBeString()
        ->and($promotion->tenant_id)->toBe(0)
        ->and($promotion->currency)->toBe('CNY')
        ->and($coupon->tenant_id)->toBe(0)
        ->and($coupon->currency)->toBe('CNY')
        ->and($coupon->fresh()->used_count)->toBe(0);
});

test('tenant context is shared within a scope and cleared for the next scope', function (): void {
    config(['filament-cms.tenancy.enabled' => true]);
    \Illuminate\Support\Facades\Context::add('filament-cms.tenant_id', 7);
    expect(app(\Fayne\Promotion\Services\TenantContext::class)->tenantId())->toBe(7);

    \Illuminate\Support\Facades\Context::forget('filament-cms.tenant_id');
    expect(fn () => app(\Fayne\Promotion\Services\TenantContext::class)->tenantId())
        ->toThrow(\Illuminate\Validation\ValidationException::class);
});

test('installer publishes missing config and preserves host settings', function (): void {
    $directory = sys_get_temp_dir() . '/promotion-config-' . bin2hex(random_bytes(6));
    mkdir($directory);
    $original = app()->configPath();
    app()->useConfigPath($directory);
    try {
        (new ReflectionMethod(\Illuminate\Support\ServiceProvider::class, 'publishes'))->invoke(app()->getProvider(\Fayne\Promotion\PromotionServiceProvider::class), [
            dirname(__DIR__, 2) . '/config/promotion.php' => $directory . '/promotion.php',
        ], 'promotion-config');
        $this->artisan('ec:promotion:install', ['--no-interaction' => true])->assertSuccessful();
        expect(is_file($directory . '/promotion.php'))->toBeTrue();
        file_put_contents($directory . '/promotion.php', '<?php return ["host_setting" => true];');
        $this->artisan('ec:promotion:install', ['--no-interaction' => true])->assertSuccessful();
        expect(require $directory . '/promotion.php')->toBe(['host_setting' => true]);
    } finally {
        app()->useConfigPath($original);
        \Illuminate\Support\Facades\File::deleteDirectory($directory);
    }
});
