<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ai_cache', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('dedup_hash', 64)->unique();
            $table->json('response_json');
            $table->dateTime('expires_at')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_cache');
    }
};
