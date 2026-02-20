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
        Schema::create('app_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('app_id')->constrained()->cascadeOnDelete();
            $table->integer('order')->default(1);
            $table->foreignId('ai_model_id')->constrained()->cascadeOnDelete();
            $table->text('prompt_template')->nullable();
            $table->json('ui_schema')->nullable();
            $table->string('name')->nullable();
            $table->text('description')->nullable();
            $table->json('config')->nullable();
            $table->json('options')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('app_steps');
    }
};
