<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('writing_ai_helps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('writing_submission_id')->constrained()->cascadeOnDelete();
            $table->text('user_prompt')->nullable();
            $table->text('ai_response')->nullable();
            $table->string('status', 20)->default('queued');
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'writing_submission_id'], 'writing_ai_help_user_submission_idx');
            $table->index(['writing_submission_id', 'status'], 'writing_ai_help_submission_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('writing_ai_helps');
    }
};
