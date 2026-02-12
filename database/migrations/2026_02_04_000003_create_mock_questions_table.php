<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mock_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mock_section_id')->constrained()->cascadeOnDelete();
            $table->string('question_type', 30);
            $table->text('question_text');
            $table->json('options_json')->nullable();
            $table->string('correct_answer', 255)->nullable();
            $table->unsignedSmallInteger('order_index')->default(0);
            $table->timestamps();

            $table->index(['mock_section_id', 'order_index']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mock_questions');
    }
};
