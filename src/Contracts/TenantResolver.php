<?php

declare(strict_types=1);

namespace Fayne\Promotion\Contracts;

interface TenantResolver
{
    public function tenantId(): int;
}
