<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grammar_topic_ai_helps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('grammar_topic_id')->constrained('grammar_topics')->cascadeOnDelete();
            $table->string('status')->default('idle');
            $table->text('user_prompt')->nullable();
            $table->text('ai_response')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'grammar_topic_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grammar_topic_ai_helps');
    }
};
