<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['coupons' => 'promotion_id', 'coupon_reservations' => 'coupon_id'] as $table => $column) {
            Schema::table($table, function (Blueprint $blueprint) use ($column): void {
                $blueprint->dropForeign([$column]);
                $blueprint->foreign($column)
                    ->references('id')
                    ->on($column === 'promotion_id' ? 'promotions' : 'coupons')
                    ->restrictOnDelete();
            });
        }
    }

    public function down(): void
    {
        // Keep the same deletion protection as the base migrations.
    }
};
