<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('grammar_attempt_answers', function (Blueprint $table) {
            if (!Schema::hasColumn('grammar_attempt_answers', 'ai_explanation')) {
                $table->text('ai_explanation')->nullable()->after('is_correct');
            }
        });
    }

    public function down(): void
    {
        Schema::table('grammar_attempt_answers', function (Blueprint $table) {
            if (Schema::hasColumn('grammar_attempt_answers', 'ai_explanation')) {
                $table->dropColumn('ai_explanation');
            }
        });
    }
};
