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
        Schema::create('ai_model_providers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_model_id')->constrained()->cascadeOnDelete();
            $table->foreignId('provider_id')->constrained()->cascadeOnDelete();
            $table->string('provider_model_id');
            $table->boolean('is_primary')->default(false);
            $table->string('price_mode')->default('strategy')->comment('fixed, strategy');
            $table->foreignId('cost_strategy_id')->nullable()->constrained('cost_strategies')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_model_providers');
    }
};
