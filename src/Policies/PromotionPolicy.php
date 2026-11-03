<?php

declare(strict_types=1);

namespace Fayne\Promotion\Policies;

use Fayne\Promotion\Models\Promotion;
use Fayne\CMS\Support\TenantContext;
use Illuminate\Contracts\Auth\Authenticatable;

final readonly class PromotionPolicy
{
    public function __construct(private TenantContext $tenant) {}

    public function before(Authenticatable $user, string $ability, mixed $record = null): ?bool
    {
        $tenantId = $this->tenant->id();
        if ($tenantId === null || ($record instanceof Promotion && (string) $record->tenant_id !== (string) $tenantId)) {
            return false;
        }

        return null;
    }

    public function viewAny(Authenticatable $user): bool
    {
        return $this->allows($user, 'tenant.viewAnyPromotion');
    }
    public function view(Authenticatable $user, Promotion $promotion): bool
    {
        return $this->allows($user, 'tenant.viewPromotion');
    }
    public function create(Authenticatable $user): bool
    {
        return $this->allows($user, 'tenant.createPromotion');
    }
    public function update(Authenticatable $user, Promotion $promotion): bool
    {
        return $this->allows($user, 'tenant.updatePromotion');
    }
    public function delete(Authenticatable $user, Promotion $promotion): bool
    {
        return $this->allows($user, 'tenant.deletePromotion')
            && ! $promotion->coupons()->exists();
    }
    public function deleteAny(Authenticatable $user): bool
    {
        return $this->allows($user, 'tenant.deletePromotion');
    }

    private function allows(Authenticatable $user, string $permission): bool
    {
        return method_exists($user, 'can') && $user->can($permission);
    }
}
