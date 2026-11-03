<?php

declare(strict_types=1);

namespace Fayne\Promotion\Policies;

use Fayne\Promotion\Models\Coupon;
use Fayne\CMS\Support\TenantContext;
use Illuminate\Contracts\Auth\Authenticatable;

final readonly class CouponPolicy
{
    public function __construct(private TenantContext $tenant) {}

    public function before(Authenticatable $user, string $ability, mixed $record = null): ?bool
    {
        $tenantId = $this->tenant->id();
        if ($tenantId === null || ($record instanceof Coupon && (string) $record->tenant_id !== (string) $tenantId)) {
            return false;
        }

        return null;
    }

    public function viewAny(Authenticatable $user): bool
    {
        return $this->allows($user, 'tenant.viewAnyCoupon');
    }
    public function view(Authenticatable $user, Coupon $coupon): bool
    {
        return $this->allows($user, 'tenant.viewCoupon');
    }
    public function create(Authenticatable $user): bool
    {
        return $this->allows($user, 'tenant.createCoupon');
    }
    public function update(Authenticatable $user, Coupon $coupon): bool
    {
        return $this->allows($user, 'tenant.updateCoupon');
    }
    public function delete(Authenticatable $user, Coupon $coupon): bool
    {
        return $this->allows($user, 'tenant.deleteCoupon')
            && ! \Fayne\Promotion\Models\CouponReservation::query()->where('coupon_id', $coupon->getKey())->exists();
    }
    public function deleteAny(Authenticatable $user): bool
    {
        return $this->allows($user, 'tenant.deleteCoupon');
    }

    private function allows(Authenticatable $user, string $permission): bool
    {
        return method_exists($user, 'can') && $user->can($permission);
    }
}
