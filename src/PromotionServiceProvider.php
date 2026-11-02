<?php

declare(strict_types=1);

namespace Fayne\Promotion;

use Illuminate\Support\ServiceProvider;

final class PromotionServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/promotion.php', 'promotion');
    }
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        $this->loadTranslationsFrom(__DIR__ . '/../lang', 'promotion');
        if ($this->app->runningInConsole()) {
            $this->publishes([__DIR__ . '/../config/promotion.php' => config_path('promotion.php')], 'promotion-config');
        }
    }
}
