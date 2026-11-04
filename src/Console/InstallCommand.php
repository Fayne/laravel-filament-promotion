<?php

declare(strict_types=1);

namespace Fayne\Promotion\Console;

use Fayne\CMS\Models\Tenant;
use Fayne\CMS\Support\TenantContext;
use Fayne\Promotion\Enums\PromotionType;
use Fayne\Promotion\Models\Coupon;
use Fayne\Promotion\Models\Promotion;
use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;

final class InstallCommand extends Command
{
    use ConfirmableTrait;

    protected $signature = 'ec:promotion:install
        {--demo : Create disabled demo promotion and coupon}
        {--tenant= : Active tenant ID or slug for demo data}
        {--force : Allow installation in production}';

    protected $description = 'Install the Promotion module and optionally create demo data';

    public function handle(TenantContext $context): int
    {
        if (! $this->confirmToProceed()) {
            return self::FAILURE;
        }
        if ($this->call('vendor:publish', ['--tag' => ['promotion-config', 'promotion-migrations'], '--no-interaction' => true]) !== self::SUCCESS) {
            return self::FAILURE;
        }

        $demo = $this->option('demo') || ($this->input->isInteractive() && $this->confirm('Create disabled demo data?', false));
        $target = $this->option('tenant');
        $tenant = null;

        if ($target !== null && (! $demo || ! config('filament-cms.tenancy.enabled'))) {
            $this->error('--tenant requires demo data and enabled CMS tenancy.');

            return self::FAILURE;
        }

        if ($demo && config('filament-cms.tenancy.enabled')) {
            $target ??= $context->id() ?? ($this->input->isInteractive() ? $this->ask('Active tenant ID or slug') : null);
            if ($target !== null && (new Tenant)->getConnection()->getSchemaBuilder()->hasTable((new Tenant)->getTable())) {
                $query = Tenant::query()->where('is_active', true);
                $tenant = ctype_digit((string) $target) ? $query->find($target) : $query->where('slug', $target)->first();
            }

            if (! $tenant) {
                $this->error('Install CMS Core and specify an active tenant with --tenant.');

                return self::FAILURE;
            }
        }

        $status = $this->call('migrate', ['--force' => true]);
        if ($status !== self::SUCCESS) {
            return $status;
        }

        if ($demo) {
            if ($tenant) {
                $context->run($tenant, fn () => $this->createDemo((int) $tenant->getKey()));
            } else {
                $this->createDemo(0);
            }
        }

        $this->info('Promotion installed. Register PromotionPlugin on your Filament panel and grant management permissions.');

        return self::SUCCESS;
    }

    private function createDemo(int $tenantId): void
    {
        (new Promotion)->getConnection()->transaction(function () use ($tenantId): void {
            if (Coupon::query()->where('tenant_id', $tenantId)->where('code', 'PROMOTION-DEMO')->exists()) {
                $this->warn('PROMOTION-DEMO already exists. Existing data was preserved.');

                return;
            }

            $promotion = Promotion::query()->create([
                'name' => 'Demo promotion',
                'type' => PromotionType::COUPON,
                'is_active' => false,
                'conditions' => [],
                'actions' => [['type' => 'percentage_discount', 'config' => ['rate' => 1000]]],
            ]);

            $promotion->coupons()->create(['code' => 'PROMOTION-DEMO', 'is_active' => false, 'usage_limit' => 10]);
            $this->info('Created a disabled 10% promotion and coupon PROMOTION-DEMO.');
        });
    }
}
