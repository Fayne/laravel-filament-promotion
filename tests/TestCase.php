<?php

declare(strict_types=1);

namespace Fayne\Promotion\Tests;

use Fayne\Promotion\PromotionPlugin;
use Fayne\Promotion\PromotionServiceProvider;
use Filament\FilamentServiceProvider;
use Filament\Panel;
use Filament\PanelProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected $enablesPackageDiscoveries = true;
    protected function getPackageProviders($app): array
    {
        return [FilamentServiceProvider::class, PromotionServiceProvider::class, TestPanelProvider::class];
    }

    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('cache.default', 'array');
        $app['config']->set('database.default', 'testing');
        $mysqlConfig = getenv('CMS_TEST_MYSQL_CONFIG');
        $databaseConfig = $mysqlConfig
            ? json_decode(base64_decode($mysqlConfig, true), true, flags: JSON_THROW_ON_ERROR)
            : ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true];
        $connectionName = $mysqlConfig ? 'mysql' : 'testing';
        $app['config']->set('database.default', $connectionName);
        $app['config']->set("database.connections.{$connectionName}", $databaseConfig);
        $app['config']->set('auth.defaults.guard', 'web');
        $app['config']->set('app.key', 'base64:' . base64_encode(str_repeat('a', 32)));
        $app['config']->set('logging.default', 'single');
        $app['config']->set('filament-cms.tenancy.enabled', false);
    }
}

final class TestPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel->default()->id('admin')->path('admin')->login()->plugins([PromotionPlugin::make()]);
    }
}
