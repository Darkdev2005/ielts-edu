<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mock_tests', function (Blueprint $table) {
            $table->id();
            $table->string('module', 20);
            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedInteger('time_limit')->default(0);
            $table->unsignedInteger('total_questions')->default(40);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mock_tests');
    }
};
