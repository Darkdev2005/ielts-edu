<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grammar_attempt_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grammar_attempt_id')->constrained('grammar_attempts')->cascadeOnDelete();
            $table->foreignId('grammar_exercise_id')->constrained('grammar_exercises')->cascadeOnDelete();
            $table->string('selected_answer', 1)->nullable();
            $table->boolean('is_correct')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grammar_attempt_answers');
    }
};
