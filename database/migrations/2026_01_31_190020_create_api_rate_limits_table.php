<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('api_rate_limits', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->enum('scope', ['user', 'global']);
            $table->string('scope_key', 64);
            $table->dateTime('window_start');
            $table->unsignedInteger('count')->default(0);
            $table->timestamps();

            $table->unique(['scope', 'scope_key', 'window_start'], 'api_rate_limits_scope_window_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_rate_limits');
    }
};
