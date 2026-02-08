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
        Schema::create('cost_strategies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('calc_type')->default('per_unit')->comment('fixed, per_unit, per_second, per_token');
            $table->decimal('provider_unit_price', 12, 6);
            $table->decimal('markup_multiplier', 5, 2)->default(1.0);
            $table->integer('credit_conversion_rate')->default(1);
            $table->integer('min_credit_limit')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cost_strategies');
    }
};
