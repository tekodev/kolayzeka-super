<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('generations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ai_model_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ai_model_provider_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('pending')->comment('pending, processing, completed, failed');
            $table->json('input_data')->nullable();
            $table->json('output_data')->nullable();
            $table->decimal('provider_cost_usd', 12, 6)->nullable();
            $table->integer('user_credit_cost')->nullable();
            $table->decimal('profit_usd', 12, 6)->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('generations');
    }
};
