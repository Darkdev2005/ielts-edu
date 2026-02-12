<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ai_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->enum('status', ['pending', 'processing', 'done', 'failed', 'failed_quota'])->index();
            $table->json('input_json');
            $table->json('output_json')->nullable();
            $table->text('error_text')->nullable();
            $table->string('dedup_hash', 64)->index();
            $table->string('idempotency_key', 64)->nullable()->unique();
            $table->string('provider')->default('gemini');
            $table->string('model');
            $table->unsignedInteger('prompt_tokens')->nullable();
            $table->unsignedInteger('output_tokens')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_requests');
    }
};
