<?php

declare(strict_types=1);

namespace Fayne\Promotion\Services;

use Fayne\Promotion\Contracts\TenantResolver;
use Fayne\CMS\Support\TenantContext as CmsTenantContext;
use InvalidArgumentException;

final readonly class TenantContext implements TenantResolver
{
    public function __construct(private CmsTenantContext $context) {}

    public function tenantId(): int
    {
        $tenantId = $this->context->requireId();
        if (! is_numeric($tenantId) || (int) $tenantId < 0) {
            throw new InvalidArgumentException('A numeric trusted tenant ID is required.');
        }

        return (int) $tenantId;
    }

    public function assertMatches(int $tenantId, ?string $currency = null): void
    {
        if ($tenantId !== $this->tenantId()) {
            throw new InvalidArgumentException('Promotion tenant does not match the trusted tenant context.');
        }

        if ($currency !== null && $currency !== $this->currency()) {
            throw new InvalidArgumentException('Promotion currency does not match the tenant transaction currency.');
        }
    }

    public function currency(): string
    {
        if (class_exists(\Fayne\Commerce\Tenancy\Actions\TenantCurrency::class)) {
            return app(\Fayne\Commerce\Tenancy\Actions\TenantCurrency::class)->current();
        }

        return strtoupper((string) config('promotion.currency', 'CNY'));
    }
}
