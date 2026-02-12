<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mock_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mock_test_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('section_number');
            $table->string('title')->nullable();
            $table->string('audio_url')->nullable();
            $table->longText('passage_text')->nullable();
            $table->unsignedSmallInteger('question_count')->default(0);
            $table->timestamps();

            $table->unique(['mock_test_id', 'section_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mock_sections');
    }
};
