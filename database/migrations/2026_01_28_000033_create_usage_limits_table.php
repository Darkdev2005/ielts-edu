<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usage_limits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('limit_key');
            $table->date('date');
            $table->unsignedInteger('count')->default(0);
            $table->timestamps();
            $table->unique(['user_id', 'limit_key', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usage_limits');
    }
};
