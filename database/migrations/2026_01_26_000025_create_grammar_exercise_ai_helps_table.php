<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('grammar_exercise_ai_helps')) {
            $hasIndex = collect(DB::select("SHOW INDEX FROM grammar_exercise_ai_helps WHERE Key_name = 'geah_user_ex_status_idx'"))->isNotEmpty();
            if (!$hasIndex) {
                Schema::table('grammar_exercise_ai_helps', function (Blueprint $table) {
                    $table->index(['user_id', 'grammar_exercise_id', 'status'], 'geah_user_ex_status_idx');
                });
            }
            return;
        }

        Schema::create('grammar_exercise_ai_helps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('grammar_exercise_id')->constrained('grammar_exercises')->cascadeOnDelete();
            $table->text('user_prompt')->nullable();
            $table->text('ai_response')->nullable();
            $table->string('status', 20)->default('queued');
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'grammar_exercise_id', 'status'], 'geah_user_ex_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grammar_exercise_ai_helps');
    }
};
