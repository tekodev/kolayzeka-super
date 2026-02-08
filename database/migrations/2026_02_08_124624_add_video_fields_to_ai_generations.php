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
        Schema::table('generations', function (Blueprint $table) {
            $table->unsignedBigInteger('parent_generation_id')->nullable();
            $table->text('video_prompt')->nullable();
            $table->json('video_config')->nullable();
            
            $table->foreign('parent_generation_id')
                  ->references('id')
                  ->on('generations')
                  ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('generations', function (Blueprint $table) {
            $table->dropForeign(['parent_generation_id']);
            $table->dropColumn(['parent_generation_id', 'video_prompt', 'video_config']);
        });
    }
};
