<?php
declare(strict_types=1);
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration { public function up(): void { Schema::create('coupons', function(Blueprint $table): void { $table->uuid('id')->primary(); $table->unsignedBigInteger('tenant_id')->default(0)->index();
            $table->string('currency', 3)->default('CNY');
            $table->foreignUuid('promotion_id')->constrained()->restrictOnDelete(); $table->string('code');
            $table->unique(['tenant_id', 'code']); $table->boolean('is_active')->default(true)->index(); $table->timestamp('starts_at')->nullable(); $table->timestamp('ends_at')->nullable(); $table->unsignedInteger('usage_limit')->nullable(); $table->unsignedInteger('used_count')->default(0); $table->timestamps(); }); } public function down(): void { Schema::dropIfExists('coupons'); } };
