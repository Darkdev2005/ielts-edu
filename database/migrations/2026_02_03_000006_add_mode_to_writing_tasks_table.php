<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('writing_tasks', function (Blueprint $table) {
            $table->string('mode', 20)->default('practice')->index();
        });
    }

    public function down(): void
    {
        Schema::table('writing_tasks', function (Blueprint $table) {
            $table->dropIndex(['mode']);
            $table->dropColumn('mode');
        });
    }
};
