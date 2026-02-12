<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vocab_items', function (Blueprint $table) {
            $table->text('definition_uz')->nullable()->after('definition');
            $table->text('definition_ru')->nullable()->after('definition_uz');
        });
    }

    public function down(): void
    {
        Schema::table('vocab_items', function (Blueprint $table) {
            $table->dropColumn(['definition_uz', 'definition_ru']);
        });
    }
};
