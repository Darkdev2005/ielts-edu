<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mock_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mock_attempt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('mock_question_id')->constrained()->cascadeOnDelete();
            $table->string('user_answer', 255)->nullable();
            $table->boolean('is_correct')->default(false);
            $table->timestamps();

            $table->unique(['mock_attempt_id', 'mock_question_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mock_answers');
    }
};
