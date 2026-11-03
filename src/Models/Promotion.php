<?php

declare(strict_types=1);

namespace Fayne\Promotion\Models;

use Fayne\Promotion\Enums\PromotionType;
use Fayne\Promotion\Services\TenantContext;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Promotion extends Model
{
    use HasUuids;

    protected $fillable = ['tenant_id', 'currency', 'name', 'type', 'priority', 'is_active', 'stop_processing', 'starts_at', 'ends_at', 'conditions', 'actions'];

    protected static function booted(): void
    {
        self::saving(function (self $promotion): void {
            $promotion->tenant_id = app(TenantContext::class)->tenantId();
            $promotion->currency = app(TenantContext::class)->currency();
            if ($promotion->starts_at && $promotion->ends_at && $promotion->ends_at->lt($promotion->starts_at)) {
                throw new \InvalidArgumentException('Promotion end time must not be before its start time.');
            }
        });
    }

    protected function casts(): array
    {
        return ['tenant_id' => 'integer', 'type' => PromotionType::class, 'priority' => 'integer', 'is_active' => 'boolean', 'stop_processing' => 'boolean', 'starts_at' => 'immutable_datetime', 'ends_at' => 'immutable_datetime', 'conditions' => 'array', 'actions' => 'array'];
    }

    public function coupons(): HasMany
    {
        return $this->hasMany(Coupon::class);
    }
}
