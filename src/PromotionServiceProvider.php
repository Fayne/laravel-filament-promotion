<?php

declare(strict_types=1);

namespace Fayne\Promotion;

use Fayne\Promotion\Engine\PromotionEngine;
use Fayne\Promotion\Services\CouponService;
use Fayne\Promotion\Services\TenantContext;
use Fayne\Promotion\Models\Coupon;
use Fayne\Promotion\Models\Promotion;
use Fayne\Promotion\Policies\CouponPolicy;
use Fayne\Promotion\Policies\PromotionPolicy;
use Fayne\Promotion\Validation\RuleValidator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

final class PromotionServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/promotion.php', 'promotion');
        $this->app->scoped(TenantContext::class);
        $this->app->singleton(CouponService::class, fn ($app) => new CouponService);
        $this->app->singleton(PromotionEngine::class, fn ($app) => new PromotionEngine($app->make(CouponService::class)));
        $this->app->singleton(RuleValidator::class, fn () => new RuleValidator);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        $this->loadTranslationsFrom(__DIR__ . '/../resources/lang', 'promotion');
        $this->callAfterResolving(\Fayne\CMS\Support\Authorization\PermissionRegistry::class, function ($registry): void {
            $registry->register('promotion', require __DIR__ . '/../resources/permissions.php');
        });
        Gate::policy(Promotion::class, PromotionPolicy::class);
        Gate::policy(Coupon::class, CouponPolicy::class);
        if ($this->app->runningInConsole()) {
            $this->commands([\Fayne\Promotion\Console\InstallCommand::class]);
            $this->publishes([__DIR__ . '/../config/promotion.php' => config_path('promotion.php')], 'promotion-config');
        }
    }
}
