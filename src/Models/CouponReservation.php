<?php

declare(strict_types=1);

namespace Fayne\Promotion\Models;

use Fayne\Promotion\Enums\CouponReservationStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class CouponReservation extends Model
{
    use HasUuids;

    protected $fillable = ['tenant_id', 'coupon_id', 'customer_id', 'source_type', 'source_id', 'idempotency_key', 'request_fingerprint', 'status', 'expires_at', 'committed_at', 'released_at'];

    protected function casts(): array
    {
        return ['tenant_id' => 'integer', 'customer_id' => 'integer', 'status' => CouponReservationStatus::class, 'expires_at' => 'immutable_datetime', 'committed_at' => 'immutable_datetime', 'released_at' => 'immutable_datetime'];
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }
}
