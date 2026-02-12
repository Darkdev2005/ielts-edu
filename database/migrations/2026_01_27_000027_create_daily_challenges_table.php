<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_challenges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->date('challenge_date');
            $table->json('question_ids')->nullable();
            $table->foreignId('grammar_exercise_id')->nullable()->constrained('grammar_exercises')->nullOnDelete();
            $table->unsignedSmallInteger('score')->default(0);
            $table->unsignedSmallInteger('total')->default(0);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'challenge_date'], 'user_daily_challenge_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_challenges');
    }
};
