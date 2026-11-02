<?php

declare(strict_types=1);

namespace Fayne\Promotion\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class Coupon extends Model
{
    use HasUuids;
    protected $fillable = ['promotion_id', 'code', 'is_active', 'starts_at', 'ends_at', 'usage_limit', 'used_count'];
    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'starts_at' => 'immutable_datetime', 'ends_at' => 'immutable_datetime', 'usage_limit' => 'integer', 'used_count' => 'integer'];
    }
    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class);
    }
}
