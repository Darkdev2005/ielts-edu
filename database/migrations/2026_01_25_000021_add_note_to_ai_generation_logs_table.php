<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_generation_logs', function (Blueprint $table) {
            if (!Schema::hasColumn('ai_generation_logs', 'note')) {
                $table->text('note')->nullable()->after('error_message');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ai_generation_logs', function (Blueprint $table) {
            if (Schema::hasColumn('ai_generation_logs', 'note')) {
                $table->dropColumn('note');
            }
        });
    }
};
