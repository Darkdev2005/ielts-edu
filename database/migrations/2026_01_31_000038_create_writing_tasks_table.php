<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('writing_tasks', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('task_type', 20)->default('task1');
            $table->text('prompt');
            $table->string('difficulty', 10)->default('B1');
            $table->unsignedInteger('time_limit_minutes')->nullable();
            $table->unsignedInteger('min_words')->nullable();
            $table->unsignedInteger('max_words')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('writing_tasks');
    }
};
