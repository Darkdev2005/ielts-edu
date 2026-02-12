<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vocab_items', function (Blueprint $table) {
            $table->string('pronunciation', 120)->nullable()->after('term');
        });
    }

    public function down(): void
    {
        Schema::table('vocab_items', function (Blueprint $table) {
            $table->dropColumn('pronunciation');
        });
    }
};
