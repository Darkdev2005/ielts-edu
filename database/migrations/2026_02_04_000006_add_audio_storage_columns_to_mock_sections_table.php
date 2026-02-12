<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mock_sections', function (Blueprint $table) {
            $table->string('audio_disk', 50)->nullable()->after('audio_url');
            $table->string('audio_path')->nullable()->after('audio_disk');
        });
    }

    public function down(): void
    {
        Schema::table('mock_sections', function (Blueprint $table) {
            $table->dropColumn(['audio_disk', 'audio_path']);
        });
    }
};
