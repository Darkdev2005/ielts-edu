<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('daily_challenge_answers')) {
            $indexes = collect(DB::select("SHOW INDEX FROM daily_challenge_answers"))->pluck('Key_name')->unique();
            if (!$indexes->contains('dca_challenge_question_idx')) {
                Schema::table('daily_challenge_answers', function (Blueprint $table) {
                    $table->index(['daily_challenge_id', 'question_id'], 'dca_challenge_question_idx');
                });
            }
            if (!$indexes->contains('dca_challenge_grammar_idx')) {
                Schema::table('daily_challenge_answers', function (Blueprint $table) {
                    $table->index(['daily_challenge_id', 'grammar_exercise_id'], 'dca_challenge_grammar_idx');
                });
            }
            return;
        }

        Schema::create('daily_challenge_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('daily_challenge_id')->constrained('daily_challenges')->cascadeOnDelete();
            $table->foreignId('question_id')->nullable()->constrained('questions')->nullOnDelete();
            $table->foreignId('grammar_exercise_id')->nullable()->constrained('grammar_exercises')->nullOnDelete();
            $table->string('selected_answer', 1)->nullable();
            $table->boolean('is_correct')->default(false);
            $table->timestamps();

            $table->index(['daily_challenge_id', 'question_id'], 'dca_challenge_question_idx');
            $table->index(['daily_challenge_id', 'grammar_exercise_id'], 'dca_challenge_grammar_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_challenge_answers');
    }
};
