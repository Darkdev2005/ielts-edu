<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('writing_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('writing_task_id')->constrained()->cascadeOnDelete();
            $table->longText('response_text');
            $table->unsignedInteger('word_count')->default(0);
            $table->string('status', 20)->default('queued');
            $table->decimal('band_score', 3, 1)->nullable();
            $table->text('ai_feedback')->nullable();
            $table->json('ai_feedback_json')->nullable();
            $table->text('ai_error')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'writing_task_id']);
            $table->index(['status', 'submitted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('writing_submissions');
    }
};
