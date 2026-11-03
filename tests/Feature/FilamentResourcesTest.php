<?php

declare(strict_types=1);

use Fayne\Promotion\Filament\Resources\CouponResource;
use Fayne\Promotion\Filament\Resources\PromotionResource;
use Filament\Panel;
use Fayne\Promotion\Tests\TestCase;

uses(TestCase::class);

test('promotion plugin registers both resources on the test panel', function (): void {
    $panel = Panel::make()->id('standalone');
    \Fayne\Promotion\PromotionPlugin::make()->register($panel);
    expect($panel->getResources())->toContain(PromotionResource::class)->toContain(CouponResource::class);
});

test('package registers tenant scoped policies and CMS permission resources', function (): void {
    $permissions = app(\Fayne\CMS\Support\Authorization\PermissionRegistry::class)->tenantPermissions();

    expect(\Illuminate\Support\Facades\Gate::getPolicyFor(\Fayne\Promotion\Models\Promotion::class))
        ->toBeInstanceOf(\Fayne\Promotion\Policies\PromotionPolicy::class)
        ->and(\Illuminate\Support\Facades\Gate::getPolicyFor(\Fayne\Promotion\Models\Coupon::class))
        ->toBeInstanceOf(\Fayne\Promotion\Policies\CouponPolicy::class)
        ->and($permissions)->toContain('tenant.viewAnyPromotion', 'tenant.createPromotion', 'tenant.viewAnyCoupon', 'tenant.createCoupon');
});

test('resource queries are scoped to the trusted tenant context', function (): void {
    migratePromotionTablesForResourceTest();
    config(['filament-cms.tenancy.enabled' => true]);
    \Illuminate\Support\Facades\Context::add('filament-cms.tenant_id', 0);
    \Fayne\Promotion\Models\Promotion::query()->create(['name' => 'Tenant zero', 'type' => \Fayne\Promotion\Enums\PromotionType::AUTO, 'conditions' => [], 'actions' => []]);
    \Illuminate\Support\Facades\Context::add('filament-cms.tenant_id', 1);
    expect($this->app->make(\Fayne\Promotion\Services\TenantContext::class)->tenantId())->toBe(1)
        ->and(PromotionResource::getEloquentQuery()->count())->toBe(0);
});

function migratePromotionTablesForResourceTest(): void
{
    (require dirname(__DIR__, 2) . '/database/migrations/2026_11_02_000001_create_promotions_table.php')->up();
    (require dirname(__DIR__, 2) . '/database/migrations/2026_11_02_000002_create_coupons_table.php')->up();
}

test('promotion and coupon forms create and edit records through Livewire', function (): void {
    config(['filament-cms.tenancy.enabled' => true]);
    \Illuminate\Support\Facades\Context::add('filament-cms.tenant_id', 0);
    (require dirname(__DIR__, 2) . '/database/migrations/2026_11_02_000001_create_promotions_table.php')->up();
    (require dirname(__DIR__, 2) . '/database/migrations/2026_11_02_000002_create_coupons_table.php')->up();
    \Illuminate\Support\Facades\Gate::before(fn () => true);
    $user = new class extends \Illuminate\Foundation\Auth\User {};
    auth('web')->setUser($user);

    \Livewire\Livewire::test(\Fayne\Promotion\Filament\Resources\Promotions\Pages\CreatePromotion::class)
        ->fillForm([
            'name' => 'Welcome campaign', 'type' => 'auto', 'priority' => 5,
            'is_active' => true, 'stop_processing' => false,
            'conditions' => [
                ['type' => 'cart_total', 'config' => ['operator' => 'gte', 'value' => '1000', 'stale' => 'removed']],
                ['type' => 'composite', 'config' => ['logic' => 'or', 'conditions' => [
                    ['type' => 'composite', 'config' => ['logic' => 'and', 'conditions' => [
                        ['type' => 'category', 'config' => ['operator' => 'in', 'values' => 'books, stationery']],
                    ]]],
                ]]],
            ],
            'actions' => [['type' => 'fixed_discount', 'config' => ['amount' => '100', 'stale' => 'removed']]],
        ])
        ->call('create')->assertHasNoFormErrors();

    $promotion = \Fayne\Promotion\Models\Promotion::query()->where('name', 'Welcome campaign')->firstOrFail();
    \Livewire\Livewire::test(\Fayne\Promotion\Filament\Resources\Promotions\Pages\EditPromotion::class, ['record' => $promotion->id])
        ->call('save')->assertHasNoFormErrors();
    expect($promotion->conditions)->toBe([
        ['type' => 'cart_total', 'config' => ['operator' => 'gte', 'value' => 1000]],
        ['type' => 'composite', 'config' => ['logic' => 'or', 'conditions' => [
            ['type' => 'composite', 'config' => ['logic' => 'and', 'conditions' => [
                ['type' => 'category', 'config' => ['operator' => 'in', 'values' => ['books', 'stationery']]],
            ]]],
        ]]],
    ])
        ->and($promotion->actions)->toBe([['type' => 'fixed_discount', 'config' => ['amount' => 100]]]);

    $result = app(\Fayne\Promotion\Engine\PromotionEngine::class)->calculate(
        new \Fayne\Promotion\DTO\Context(0, 'CNY', 1000, 0, [
            new \Fayne\Promotion\DTO\CartItem('book', 'BOOK', 1000, 1, ['books']),
        ]),
    );
    expect($result->itemDiscount)->toBe(100)->and($result->payable)->toBe(900);

    $couponPromotion = \Fayne\Promotion\Models\Promotion::query()->create([
        'name' => 'Coupon campaign', 'type' => \Fayne\Promotion\Enums\PromotionType::COUPON,
        'conditions' => [], 'actions' => [['type' => 'free_shipping', 'config' => []]],
    ]);
    \Livewire\Livewire::test(\Fayne\Promotion\Filament\Resources\Coupons\Pages\CreateCoupon::class)
        ->fillForm(['promotion_id' => $couponPromotion->id, 'code' => 'welcome10', 'is_active' => true, 'usage_limit' => '2'])
        ->call('create')->assertHasNoFormErrors();

    $coupon = \Fayne\Promotion\Models\Coupon::query()->firstOrFail();
    \Livewire\Livewire::test(\Fayne\Promotion\Filament\Resources\Coupons\Pages\EditCoupon::class, ['record' => $coupon->id])
        ->fillForm(['code' => 'welcome20', 'is_active' => false, 'usage_limit' => '3'])
        ->call('save')->assertHasNoFormErrors();

    expect($coupon->fresh()->code)->toBe('WELCOME20')
        ->and($coupon->fresh()->is_active)->toBeFalse()
        ->and($coupon->fresh()->usage_limit)->toBe(3);
});

test('promotion form normalization drops stale fields and preserves invalid decimal input for validation', function (): void {
    $data = [
        'conditions' => [
            'temp-condition-a' => ['type' => 'cart_total', 'config' => ['operator' => 'gte', 'value' => '1200', 'values' => 'stale']],
            'temp-condition-b' => ['type' => 'composite', 'config' => ['logic' => 'or', 'conditions' => [
                'temp-condition-c' => ['type' => 'product_sku', 'config' => ['operator' => 'in', 'values' => 'SKU-A, SKU-B', 'value' => 'stale']],
            ]]],
        ],
        'actions' => [
            'temp-action-a' => ['type' => 'percentage_discount', 'config' => ['rate' => '10.5', 'amount' => 'stale', 'max_discount' => '500']],
        ],
    ];

    \Fayne\Promotion\Filament\Resources\Promotions\Pages\CreatePromotion::normalizePromotionData($data);

    expect($data['conditions'])->toBe([
        ['type' => 'cart_total', 'config' => ['operator' => 'gte', 'value' => 1200]],
        ['type' => 'composite', 'config' => ['logic' => 'or', 'conditions' => [
            ['type' => 'product_sku', 'config' => ['operator' => 'in', 'values' => ['SKU-A', 'SKU-B']]],
        ]]],
    ])->and($data['actions'][0]['config'])->toBe(['rate' => '10.5', 'max_discount' => 500]);

    expect(fn () => app(\Fayne\Promotion\Validation\RuleValidator::class)->validate($data['conditions'], $data['actions']))
        ->toThrow(InvalidArgumentException::class);
});

test('promotion and coupon policies deny users without management permissions', function (): void {
    config(['filament-cms.tenancy.enabled' => true]);
    \Illuminate\Support\Facades\Context::add('filament-cms.tenant_id', 1);
    $user = new class extends \Illuminate\Foundation\Auth\User {};
    $gate = \Illuminate\Support\Facades\Gate::forUser($user);

    foreach ([new \Fayne\Promotion\Models\Promotion, new \Fayne\Promotion\Models\Coupon] as $record) {
        $record->tenant_id = 1;
        foreach (['viewAny', 'create'] as $ability) {
            expect($gate->denies($ability, $record::class))->toBeTrue();
        }
        foreach (['view', 'update', 'delete'] as $ability) {
            expect($gate->denies($ability, $record))->toBeTrue();
        }
    }
});
