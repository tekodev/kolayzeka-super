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
            
            // Merged from separate migrations:
            $table->float('duration')->nullable();
            $table->json('provider_request_body')->nullable();
            $table->text('thumbnail_url')->nullable();
            
            // Video fields
            $table->unsignedBigInteger('parent_generation_id')->nullable();
            $table->text('video_prompt')->nullable();
            $table->json('video_config')->nullable();

            $table->foreignId('app_execution_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('app_step_id')->nullable()->constrained()->nullOnDelete();
            $table->foreign('parent_generation_id')->references('id')->on('generations')->onDelete('cascade');

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
