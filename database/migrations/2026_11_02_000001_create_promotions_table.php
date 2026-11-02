<?php
declare(strict_types=1);
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration { public function up(): void { Schema::create('promotions', function(Blueprint $table): void { $table->uuid('id')->primary(); $table->string('name'); $table->string('type',16)->index(); $table->unsignedInteger('priority')->default(0)->index(); $table->boolean('is_active')->default(true)->index(); $table->boolean('stop_processing')->default(false); $table->timestamp('starts_at')->nullable(); $table->timestamp('ends_at')->nullable(); $table->json('conditions'); $table->json('actions'); $table->timestamps(); }); } public function down(): void { Schema::dropIfExists('promotions'); } };
