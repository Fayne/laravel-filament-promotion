<p align="center"><img src="https://res.cloudinary.com/fayne/image/upload/v1787643269/fayne_logo.png" width="300"></p>

# Laravel Filament Promotion

A Laravel package for managing promotion rules in Filament and calculating discounts in application code.

> This package is under initial development. The package skeleton, persistence layer, rule engine, coupon workflow, and Filament resource are being built in stages. Do not treat planned features as available until implemented and released.

## Requirements

- PHP 8.4+
- Laravel 13+
- Filament 5+

## Installation

```bash
composer require fayne/laravel-filament-promotion
php artisan migrate
```

Laravel discovers the package provider automatically. Its migrations create the `promotions` and `coupons` tables.

## Register the Filament plugin

Add the plugin to the panel where promotions should be managed:

```php
use Fayne\Promotion\PromotionPlugin;

->plugins([
    PromotionPlugin::make(),
])
```

The plugin registers the promotion resource only on that panel. Define and register a Laravel `PromotionPolicy` in the host application to control access. The package does not grant permissions automatically.

## Current implementation status

The initial package skeleton includes promotion and coupon models, migrations, the service provider, and the panel plugin entry point. The promotion resource, validation, calculation engine, coupon validation/redemption service, translations, and user-facing examples are not implemented yet.

## License

Proprietary. See [LICENSE.md](LICENSE.md). Use and distribution require authorization from the copyright holder.
