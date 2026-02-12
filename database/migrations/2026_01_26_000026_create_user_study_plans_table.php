<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_study_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->date('week_start_date');
            $table->unsignedSmallInteger('lessons_target')->default(3);
            $table->unsignedSmallInteger('grammar_target')->default(2);
            $table->unsignedSmallInteger('vocab_target')->default(1);
            $table->timestamps();

            $table->unique(['user_id', 'week_start_date'], 'user_week_plan_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_study_plans');
    }
};
