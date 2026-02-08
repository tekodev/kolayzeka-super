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
        Schema::create('ai_model_schemas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_model_provider_id')->constrained('ai_model_providers')->cascadeOnDelete();
            $table->string('version')->nullable();
            $table->json('input_schema')->nullable();
            $table->json('output_schema')->nullable();
            $table->json('field_mapping')->nullable();
            $table->json('default_values')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_model_schemas');
    }
};
