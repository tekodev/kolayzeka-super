<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('app_executions', function (Blueprint $table) {
            $table->json('generation_ids')->nullable()->after('history');
        });
    }

    public function down(): void
    {
        Schema::table('app_executions', function (Blueprint $table) {
            $table->dropColumn('generation_ids');
        });
    }
};
