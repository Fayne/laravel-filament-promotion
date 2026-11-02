<?php

declare(strict_types=1);

namespace Fayne\Promotion\Models;

use Fayne\Promotion\Enums\PromotionType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Promotion extends Model
{
    use HasUuids;
    protected $fillable = ['name', 'type', 'priority', 'is_active', 'stop_processing', 'starts_at', 'ends_at', 'conditions', 'actions'];
    protected function casts(): array
    {
        return ['type' => PromotionType::class, 'priority' => 'integer', 'is_active' => 'boolean', 'stop_processing' => 'boolean', 'starts_at' => 'immutable_datetime', 'ends_at' => 'immutable_datetime', 'conditions' => 'array', 'actions' => 'array'];
    }
    public function coupons(): HasMany
    {
        return $this->hasMany(Coupon::class);
    }
}
