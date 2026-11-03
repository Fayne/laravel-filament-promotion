<?php

declare(strict_types=1);

namespace Fayne\Promotion\Services;

use Fayne\Promotion\Enums\CouponReservationStatus;
use Fayne\Promotion\Enums\PromotionType;
use Fayne\Promotion\Exceptions\InvalidCouponException;
use Fayne\Promotion\Exceptions\ReservationConflictException;
use Fayne\Promotion\Models\Coupon;
use Fayne\Promotion\Models\CouponReservation;
use Fayne\Promotion\Models\Promotion;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class CouponService
{
    public function findValid(int $tenantId, string $currency, string $code, ?\DateTimeInterface $now = null): ?Coupon
    {
        app(TenantContext::class)->assertMatches($tenantId, $currency);

        $coupon = Coupon::query()->with('promotion')->where('tenant_id', $tenantId)->where('code', strtoupper(trim($code)))->first();
        if (! $coupon || ! $coupon->is_active || ! $coupon->promotion?->is_active || $coupon->promotion->type !== PromotionType::COUPON) {
            return null;
        }
        $time = Carbon::instance($now ?? now());
        if ($coupon->currency !== $currency || $coupon->promotion->currency !== $currency || $coupon->tenant_id !== $coupon->promotion->tenant_id || ! $this->withinDates($coupon, $time) || ! $this->withinDates($coupon->promotion, $time)) {
            return null;
        }
        // Expired reservations still consume quota until explicitly transitioned.
        $reserved = CouponReservation::query()->where('tenant_id', $tenantId)->where('coupon_id', $coupon->id)->where('status', CouponReservationStatus::RESERVED)->count();

        return $coupon->usage_limit === null || $coupon->used_count + $reserved < $coupon->usage_limit ? $coupon : null;
    }

    public function validate(int $tenantId, string $currency, string $code, ?\DateTimeInterface $now = null): Coupon
    {
        return $this->findValid($tenantId, $currency, $code, $now) ?? throw new InvalidCouponException('The coupon code is invalid or unavailable.');
    }

    public function reserve(int $tenantId, string $currency, string $code, string $sourceType, string $sourceId, string $idempotencyKey, Carbon $expiresAt, ?int $customerId = null): CouponReservation
    {
        app(TenantContext::class)->assertMatches($tenantId, $currency);

        if ($tenantId < 0 || trim($sourceType) === '' || trim($sourceId) === '' || trim($idempotencyKey) === '' || $expiresAt->lte(now())) {
            throw new \InvalidArgumentException('Tenant, business reference, idempotency key, and a future expiration are required.');
        }

        return DB::transaction(function () use ($tenantId, $currency, $code, $sourceType, $sourceId, $idempotencyKey, $expiresAt, $customerId): CouponReservation {
            $fingerprint = hash('sha256', json_encode([$tenantId, $currency, strtoupper(trim($code)), $sourceType, $sourceId, $idempotencyKey, $customerId, $expiresAt->toIso8601String()], JSON_THROW_ON_ERROR));
            $coupon = Coupon::query()->where('tenant_id', $tenantId)->where('code', strtoupper(trim($code)))->lockForUpdate()->first();
            $existing = CouponReservation::query()->where('tenant_id', $tenantId)->where(fn ($query) => $query->where('idempotency_key', $idempotencyKey)->orWhere(fn ($q) => $q->where('source_type', $sourceType)->where('source_id', $sourceId)))->lockForUpdate()->first();
            if ($existing) {
                if ($existing->request_fingerprint !== $fingerprint) {
                    throw new ReservationConflictException('The business reference or idempotency key was already used with different inputs.');
                }

                return $existing;
            }
            if (! $coupon || ! $this->isValidCoupon($coupon, $tenantId, $currency, now())) {
                throw new InvalidCouponException('The coupon code is invalid or unavailable.');
            }
            $reserved = CouponReservation::query()->where('tenant_id', $tenantId)->where('coupon_id', $coupon->id)->where('status', CouponReservationStatus::RESERVED)->count();
            if ($coupon->usage_limit !== null && $coupon->used_count + $reserved >= $coupon->usage_limit) {
                throw new InvalidCouponException('The coupon code is invalid or unavailable.');
            }

            return CouponReservation::query()->create(['tenant_id' => $tenantId, 'coupon_id' => $coupon->id, 'customer_id' => $customerId, 'source_type' => $sourceType, 'source_id' => $sourceId, 'idempotency_key' => $idempotencyKey, 'request_fingerprint' => $fingerprint, 'status' => CouponReservationStatus::RESERVED, 'expires_at' => $expiresAt]);
        }, attempts: 3);
    }

    public function commit(int $tenantId, string $reservationId): CouponReservation
    {
        app(TenantContext::class)->assertMatches($tenantId);

        return DB::transaction(function () use ($tenantId, $reservationId): CouponReservation {
            $couponId = CouponReservation::query()->where('tenant_id', $tenantId)->whereKey($reservationId)->value('coupon_id');
            if (! $couponId) {
                CouponReservation::query()->where('tenant_id', $tenantId)->whereKey($reservationId)->firstOrFail();
            }
            $coupon = Coupon::query()->where('tenant_id', $tenantId)->whereKey($couponId)->lockForUpdate()->firstOrFail();
            $reservation = CouponReservation::query()->where('tenant_id', $tenantId)->whereKey($reservationId)->lockForUpdate()->firstOrFail();
            if ($reservation->status === CouponReservationStatus::COMMITTED) {
                return $reservation;
            }
            if ($reservation->status !== CouponReservationStatus::RESERVED || $reservation->expires_at->lte(now())) {
                throw new ReservationConflictException('This reservation has expired or has already been released.');
            }
            if (! $coupon->is_active || ($coupon->usage_limit !== null && $coupon->used_count >= $coupon->usage_limit)) {
                throw new ReservationConflictException('Coupon availability changed before payment confirmation.');
            }
            $reservation->update(['status' => CouponReservationStatus::COMMITTED, 'committed_at' => now()]);
            $coupon->increment('used_count');

            return $reservation->fresh();
        }, attempts: 3);
    }

    public function release(int $tenantId, string $reservationId): CouponReservation
    {
        app(TenantContext::class)->assertMatches($tenantId);

        return DB::transaction(function () use ($tenantId, $reservationId): CouponReservation {
            $couponId = CouponReservation::query()->where('tenant_id', $tenantId)->whereKey($reservationId)->value('coupon_id');
            if (! $couponId) {
                CouponReservation::query()->where('tenant_id', $tenantId)->whereKey($reservationId)->firstOrFail();
            }
            $coupon = Coupon::query()->where('tenant_id', $tenantId)->whereKey($couponId)->lockForUpdate()->firstOrFail();
            $reservation = CouponReservation::query()->where('tenant_id', $tenantId)->whereKey($reservationId)->lockForUpdate()->firstOrFail();
            if ($reservation->status === CouponReservationStatus::RELEASED) {
                return $reservation;
            }
            if ($reservation->status !== CouponReservationStatus::RESERVED) {
                throw new ReservationConflictException('A committed reservation cannot be released.');
            }
            $reservation->update(['status' => CouponReservationStatus::RELEASED, 'released_at' => now()]);

            return $reservation->fresh();
        }, attempts: 3);
    }

    public function releaseExpired(int $tenantId, ?Carbon $now = null, int $limit = 100): int
    {
        app(TenantContext::class)->assertMatches($tenantId);

        if ($tenantId < 0 || $limit < 1) {
            throw new \InvalidArgumentException('A valid tenant and positive batch limit are required.');
        }

        $time = $now ?? now();
        $released = 0;
        $couponIds = CouponReservation::query()->where('tenant_id', $tenantId)->where('status', CouponReservationStatus::RESERVED)->where('expires_at', '<=', $time)->distinct()->orderBy('coupon_id')->limit($limit)->pluck('coupon_id');

        foreach ($couponIds as $couponId) {
            if ($released >= $limit) {
                break;
            }
            $released += DB::transaction(function () use ($tenantId, $couponId, $time, $limit, &$released): int {
                Coupon::query()->where('tenant_id', $tenantId)->whereKey($couponId)->lockForUpdate()->firstOrFail();
                $reservations = CouponReservation::query()->where('tenant_id', $tenantId)->where('coupon_id', $couponId)->where('status', CouponReservationStatus::RESERVED)->where('expires_at', '<=', $time)->orderBy('id')->limit($limit - $released)->lockForUpdate()->get();
                foreach ($reservations as $reservation) {
                    $reservation->update(['status' => CouponReservationStatus::RELEASED, 'released_at' => $time]);
                }

                return $reservations->count();
            }, attempts: 3);
        }

        return $released;
    }

    private function withinDates(Coupon|Promotion $model, Carbon $time): bool
    {
        return (! $model->starts_at || $time->gte($model->starts_at)) && (! $model->ends_at || $time->lte($model->ends_at));
    }

    private function isValidCoupon(Coupon $coupon, int $tenantId, string $currency, Carbon $time): bool
    {
        $coupon->loadMissing('promotion');
        $promotion = $coupon->promotion;

        return $coupon->is_active && $promotion?->is_active && $promotion->type === PromotionType::COUPON
            && $coupon->currency === $currency && $promotion->currency === $currency
            && $coupon->tenant_id === $tenantId && $promotion->tenant_id === $tenantId
            && $this->withinDates($coupon, $time) && $this->withinDates($promotion, $time);
    }
}
