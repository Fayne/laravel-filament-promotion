<p align="center"><img src="https://res.cloudinary.com/fayne/image/upload/v1787643269/fayne_logo.png" width="300"></p>

# Laravel Filament Promotion

[简体中文](README.md) | English

A Laravel package for promotion rules and coupon management, with a Filament admin panel, discount calculation, and coupon redemption services.

## Features and scope

Manage promotion rules and coupon codes in Filament, preview cart discounts, and reserve, redeem, or release coupon quota during checkout. Line and unit allocations can be stored by Commerce for partial refunds.

## 1. Installation and panel setup

### Requirements

- PHP 8.4+
- Laravel 13+
- Filament 5+
- Fayne CMS Core (tenant context and permission integration)

### Install and register the plugin

```bash
composer require fayne/laravel-filament-promotion
php artisan migrate
```

Register the plugin in the Filament panel that should manage promotions:

```php
use Fayne\Promotion\PromotionPlugin;

->plugins([
    PromotionPlugin::make(),
])
```

The plugin registers the Promotions and Coupons resources on that panel. The package registers Laravel Policies that enforce CMS TenantContext ownership and declares promotion/coupon CRUD permissions with the CMS permission registry. The host must still grant the corresponding permissions. Admin queries and writes reject a missing tenant context, and model saves ignore submitted tenant IDs in favor of the trusted context.

## 2. Admin features

The promotion resource supports name search, type and active-state filters, priority sorting, validity dates, and rule/action configuration. The coupon resource supports create, list, edit, and delete operations, promotion assignment, unique codes, validity dates, and usage limits. Redemption maintains usage counts; they cannot be edited in the admin form.

Conditions use root-level AND and support cart total, user group, SKU, category, datetime, and nested AND/OR groups. Groups may be nested up to two levels. Actions include fixed discount, percentage discount, free shipping, and quantity discount. Quantity discount applies a percentage to each complete buy_quantity set for selected SKUs; it does not add free items.

Amounts are integer minor currency units (USD 12.34 is 1234); percentages use basis points (10% is 1000). Context subtotal must equal the sum of cart items. Each calculation uses one currency.

### Configure your first offer

1. Open **Promotions** and create an automatic promotion or a coupon promotion. Set the validity period, conditions, and discount actions.
2. For a coupon promotion, open **Coupons**, select the promotion, and set a code and optional usage limit.
3. To stop new use, disable the promotion or coupon. A promotion with linked coupons and a coupon with reservation history are protected from deletion.

## 3. Calculate discounts

```php
use Fayne\Promotion\DTO\CartItem;
use Fayne\Promotion\DTO\Context;
use Fayne\Promotion\Engine\PromotionEngine;
use Fayne\Promotion\Services\TenantContext;

$tenant = app(TenantContext::class);
$result = app(PromotionEngine::class)->calculate(new Context(
    tenantId: $tenant->tenantId(),
    currency: $tenant->currency(),
    subtotal: 10000,
    shipping: 1000,
    items: [new CartItem(lineId: 'line-1', sku: 'SKU-A', unitPrice: 10000, quantity: 1)],
    userGroups: ['vip'],
    couponCode: 'WELCOME10',
));

$result->totalDiscount;
$result->payable;
```

Commerce must provide a trusted tenant ID, optional customer ID, and the tenant's configured currency on every call. The first release supports one transaction currency per tenant (CNY by default); tenant IDs must not be trusted from form input. The engine processes eligible automatic promotions and the promotion for the single supplied coupon together, by descending priority and ascending ID. Each rule matches against the original Context, and actions run in configured order. SKU/category conditions determine rule eligibility; fixed and percentage discounts still apply to all merchandise. Quantity discounts apply only to configured SKUs and are capped by those target lines' remaining amounts. `stop_processing` applies only after a promotion produces a positive discount. Calculation is a side-effect-free preview and does not reserve or redeem coupons.

## 4. Validate and manage coupon usage

```php
use Fayne\Promotion\Services\CouponService;
use Fayne\Promotion\Services\TenantContext;

$coupons = app(CouponService::class);
$tenant = app(TenantContext::class);
$tenantId = $tenant->tenantId();
$currency = $tenant->currency();
$coupon = $coupons->validate($tenantId, $currency, ' welcome10 '); // case-insensitive
$reservation = $coupons->reserve($tenantId, $currency, 'WELCOME10', 'checkout', $checkoutId, $idempotencyKey, now()->addMinutes(15), $customerId);
$coupons->commit($tenantId, $reservation->id); // after payment confirmation
$coupons->release($tenantId, $reservation->id); // on cancellation; repeat calls are safe
$coupons->releaseExpired($tenantId); // schedule this from the host application
```

Reservations are deduplicated by tenant-scoped idempotency key and business reference; reusing either with different inputs raises a conflict. Transitions run in transactions and lock the coupon before its reservation. Expired reservations continue to consume quota until `releaseExpired` explicitly changes their state. A delayed payment against a released reservation makes `commit` fail; the host must handle payment exceptions/compensation, as this package never initiates refunds. Commerce coordinates order and reservation work on the same database connection and persists the promotion, currency, line, and unit allocation snapshot. Partial refunds use the purchased unit net amounts; coupon quota is not automatically restored. SQLite does not provide MySQL/PostgreSQL-equivalent row-lock concurrency guarantees, so production database quota contention must be verified.

Limits: one coupon code per calculation; no gifts, per-customer usage limits, automatic coupon restoration on refund, or repricing of historical orders. The host remains responsible for business permissions and trusted tenant context; the package checks tenant ownership but does not grant business access.

## 5. Rule JSON contract

```php
$conditions = [
    ['type' => 'cart_total', 'config' => ['operator' => 'gte', 'value' => 5000]],
    ['type' => 'composite', 'config' => [
        'logic' => 'or',
        'conditions' => [
            ['type' => 'user_group', 'config' => ['operator' => 'in', 'values' => ['vip']]],
            ['type' => 'product_sku', 'config' => ['operator' => 'in', 'values' => ['SKU-A']]],
        ],
    ]],
];

$actions = [
    ['type' => 'percentage_discount', 'config' => ['rate' => 1000, 'max_discount' => 2500]],
];
```

## 6. Time and language

Start and end times are inclusive. Timestamps use the Laravel application timezone; UTC storage with panel-local display is recommended. Navigation labels are translated into English and Simplified Chinese. Field labels are currently English.

## 7. Tests

```bash
composer install
composer test
composer format
```

MySQL row-lock concurrency acceptance runs in the host repository's `laravel-filament-promotion MySQL concurrency` GitHub Actions job. It covers competing for the last usage and commit/release races; regular SQLite runs skip these integration tests. The test requires a dedicated `cpt_` table prefix and only drops its isolated tables.

### Package test isolation

Tests use the `Fayne\Promotion\Tests` namespace to avoid collisions with the host test suite. The bootstrap prefers package-local Composer dependencies and supports the development host as a fallback. Tenant context is scoped to a Laravel request/job lifecycle; the host must initialize it for every request or job. Missing context is rejected.

Percentage and quantity discounts use original eligible item amounts as their calculation base, then cap the discount by remaining eligible line budgets and max_discount. Quantity discounts allocate only to target SKU lines. Earlier discounts do not change the percentage base. Largest-remainder ties use lineId; mapping insertion order is not part of the business contract.

Calculation, validation, and reservation require the supplied tenant and currency to match the trusted context. Initialize that tenant context before committing, releasing, or sweeping reservations.

See the [production readiness checklist](PRODUCTION-READINESS.md) for remaining deployment acceptance work.

## License

This software is proprietary. Use and distribution require authorization from the copyright holder. See [LICENSE.md](LICENSE.md).
