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
        Schema::table('ai_model_providers', function (Blueprint $table) {
            $table->dropColumn('provider_name');
            $table->foreignId('provider_id')->after('ai_model_id')->constrained()->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ai_model_providers', function (Blueprint $table) {
            $table->string('provider_name')->nullable();
            $table->dropForeign(['provider_id']);
            $table->dropColumn('provider_id');
        });
    }
};
