<?php

declare(strict_types=1);

namespace Fayne\Promotion\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Fayne\Promotion\Enums\PromotionType;
use Fayne\Promotion\Services\TenantContext;

final class Coupon extends Model
{
    use HasUuids;

    protected $fillable = ['tenant_id', 'currency', 'promotion_id', 'code', 'is_active', 'starts_at', 'ends_at', 'usage_limit'];

    protected static function booted(): void
    {
        self::saving(function (self $coupon): void {
            $tenantId = app(TenantContext::class)->tenantId();
            $promotion = Promotion::query()->where('tenant_id', $tenantId)->whereKey($coupon->promotion_id)->firstOrFail();
            if ($promotion->type !== PromotionType::COUPON) {
                throw new \InvalidArgumentException('Coupons can only be assigned to coupon promotions.');
            }
            if ($coupon->currency !== null && $coupon->currency !== $promotion->currency) {
                throw new \InvalidArgumentException('Coupon currency must match its promotion currency.');
            }
            if ($promotion->currency !== app(TenantContext::class)->currency()) {
                throw new \InvalidArgumentException('Promotion currency must match the tenant transaction currency.');
            }
            $coupon->tenant_id = $tenantId;
            $coupon->currency = $promotion->currency;
            $coupon->code = strtoupper(trim($coupon->code));
            if ($coupon->starts_at && $coupon->ends_at && $coupon->ends_at->lt($coupon->starts_at)) {
                throw new \InvalidArgumentException('Coupon end time must not be before its start time.');
            }
        });
    }

    protected function casts(): array
    {
        return ['tenant_id' => 'integer', 'is_active' => 'boolean', 'starts_at' => 'immutable_datetime', 'ends_at' => 'immutable_datetime', 'usage_limit' => 'integer', 'used_count' => 'integer'];
    }

    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class);
    }
}
