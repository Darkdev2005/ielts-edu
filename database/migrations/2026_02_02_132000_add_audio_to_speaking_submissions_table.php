<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('speaking_submissions', function (Blueprint $table) {
            if (!Schema::hasColumn('speaking_submissions', 'audio_path')) {
                $table->string('audio_path')->nullable();
            }
            if (!Schema::hasColumn('speaking_submissions', 'transcript_text')) {
                $table->longText('transcript_text')->nullable();
            }
            if (!Schema::hasColumn('speaking_submissions', 'has_audio')) {
                $table->boolean('has_audio')->default(false);
            }
        });
    }

    public function down(): void
    {
        Schema::table('speaking_submissions', function (Blueprint $table) {
            if (Schema::hasColumn('speaking_submissions', 'has_audio')) {
                $table->dropColumn('has_audio');
            }
            if (Schema::hasColumn('speaking_submissions', 'transcript_text')) {
                $table->dropColumn('transcript_text');
            }
            if (Schema::hasColumn('speaking_submissions', 'audio_path')) {
                $table->dropColumn('audio_path');
            }
        });
    }
};
