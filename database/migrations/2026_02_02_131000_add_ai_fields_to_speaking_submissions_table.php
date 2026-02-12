<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('speaking_submissions', function (Blueprint $table) {
            if (!Schema::hasColumn('speaking_submissions', 'status')) {
                $table->string('status', 20)->default('queued');
            }
            if (!Schema::hasColumn('speaking_submissions', 'band_score')) {
                $table->decimal('band_score', 3, 1)->nullable();
            }
            if (!Schema::hasColumn('speaking_submissions', 'ai_feedback')) {
                $table->text('ai_feedback')->nullable();
            }
            if (!Schema::hasColumn('speaking_submissions', 'ai_feedback_json')) {
                $table->json('ai_feedback_json')->nullable();
            }
            if (!Schema::hasColumn('speaking_submissions', 'ai_error')) {
                $table->text('ai_error')->nullable();
            }
            if (!Schema::hasColumn('speaking_submissions', 'ai_request_id')) {
                $table->char('ai_request_id', 36)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('speaking_submissions', function (Blueprint $table) {
            if (Schema::hasColumn('speaking_submissions', 'ai_request_id')) {
                $table->dropColumn('ai_request_id');
            }
            if (Schema::hasColumn('speaking_submissions', 'ai_error')) {
                $table->dropColumn('ai_error');
            }
            if (Schema::hasColumn('speaking_submissions', 'ai_feedback_json')) {
                $table->dropColumn('ai_feedback_json');
            }
            if (Schema::hasColumn('speaking_submissions', 'ai_feedback')) {
                $table->dropColumn('ai_feedback');
            }
            if (Schema::hasColumn('speaking_submissions', 'band_score')) {
                $table->dropColumn('band_score');
            }
            if (Schema::hasColumn('speaking_submissions', 'status')) {
                $table->dropColumn('status');
            }
        });
    }
};
