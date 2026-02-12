<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('question_ai_helps', function (Blueprint $table) {
            $table->uuid('ai_request_id')->nullable()->after('id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('question_ai_helps', function (Blueprint $table) {
            $table->dropIndex(['ai_request_id']);
            $table->dropColumn('ai_request_id');
        });
    }
};
