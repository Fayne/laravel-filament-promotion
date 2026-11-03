<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupon_reservations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->foreignUuid('coupon_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('customer_id')->nullable()->index();
            $table->string('source_type', 100);
            $table->string('source_id', 191);
            $table->string('idempotency_key', 191);
            $table->char('request_fingerprint', 64);
            $table->string('status', 16)->index();
            $table->timestamp('expires_at')->index();
            $table->timestamp('committed_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'idempotency_key']);
            $table->unique(['tenant_id', 'source_type', 'source_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupon_reservations');
    }
};
