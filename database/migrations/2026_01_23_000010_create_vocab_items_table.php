<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vocab_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vocab_list_id')->constrained()->cascadeOnDelete();
            $table->string('term');
            $table->text('definition')->nullable();
            $table->text('example')->nullable();
            $table->string('part_of_speech', 30)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vocab_items');
    }
};
