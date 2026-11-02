<?php
declare(strict_types=1);
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration { public function up(): void { Schema::create('coupons', function(Blueprint $table): void { $table->uuid('id')->primary(); $table->foreignUuid('promotion_id')->constrained()->cascadeOnDelete(); $table->string('code')->unique(); $table->boolean('is_active')->default(true)->index(); $table->timestamp('starts_at')->nullable(); $table->timestamp('ends_at')->nullable(); $table->unsignedInteger('usage_limit')->nullable(); $table->unsignedInteger('used_count')->default(0); $table->timestamps(); }); } public function down(): void { Schema::dropIfExists('coupons'); } };
