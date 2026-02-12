<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_generation_logs', function (Blueprint $table) {
            if (!Schema::hasColumn('ai_generation_logs', 'retry_count')) {
                $table->unsignedInteger('retry_count')->default(0)->after('duration_ms');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ai_generation_logs', function (Blueprint $table) {
            if (Schema::hasColumn('ai_generation_logs', 'retry_count')) {
                $table->dropColumn('retry_count');
            }
        });
    }
};
