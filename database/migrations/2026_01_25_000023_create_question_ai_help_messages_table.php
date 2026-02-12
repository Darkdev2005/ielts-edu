<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('question_ai_help_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_ai_help_id')
                ->constrained('question_ai_helps')
                ->cascadeOnDelete();
            $table->string('role', 20);
            $table->text('content');
            $table->timestamps();

            $table->index(['question_ai_help_id', 'role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('question_ai_help_messages');
    }
};
