<?php

declare(strict_types=1);

namespace Fayne\Promotion;

use Fayne\Promotion\Filament\Resources\PromotionResource;
use Filament\Contracts\Plugin;
use Filament\Panel;

final class PromotionPlugin implements Plugin
{
    public static function make(): static
    {
        return new self;
    }
    public function getId(): string
    {
        return 'promotion';
    }
    public function register(Panel $panel): void
    {
        $panel->resources([PromotionResource::class]);
    }
    public function boot(Panel $panel): void {}
}
