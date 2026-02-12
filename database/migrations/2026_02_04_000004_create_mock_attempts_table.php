<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mock_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('mock_test_id')->constrained()->cascadeOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->unsignedSmallInteger('score_raw')->default(0);
            $table->decimal('band_score', 3, 1)->nullable();
            $table->string('mode', 20)->default('mock');
            $table->string('status', 20)->default('in_progress');
            $table->timestamps();

            $table->index(['user_id', 'mock_test_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mock_attempts');
    }
};
