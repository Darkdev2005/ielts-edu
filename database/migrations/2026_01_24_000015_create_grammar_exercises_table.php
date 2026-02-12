<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grammar_exercises', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grammar_topic_id')->constrained('grammar_topics')->cascadeOnDelete();
            $table->text('prompt');
            $table->json('options');
            $table->string('correct_answer', 1);
            $table->text('explanation')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grammar_exercises');
    }
};
