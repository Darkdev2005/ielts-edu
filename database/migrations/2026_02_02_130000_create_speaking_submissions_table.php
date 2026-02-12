<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('speaking_submissions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('speaking_prompt_id');
            $table->longText('response_text');
            $table->unsignedInteger('word_count')->default(0);
            $table->string('status', 20)->default('queued');
            $table->decimal('band_score', 3, 1)->nullable();
            $table->text('ai_feedback')->nullable();
            $table->json('ai_feedback_json')->nullable();
            $table->text('ai_error')->nullable();
            $table->char('ai_request_id', 36)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'speaking_prompt_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('speaking_submissions');
    }
};
