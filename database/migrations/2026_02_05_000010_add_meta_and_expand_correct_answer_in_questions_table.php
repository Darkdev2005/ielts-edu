<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            if (!Schema::hasColumn('questions', 'meta')) {
                $table->json('meta')->nullable()->after('options');
            }
        });

        $driver = Schema::getConnection()->getDriverName();
        $supportsModify = in_array($driver, ['mysql', 'mariadb'], true);

        if ($supportsModify && Schema::hasColumn('questions', 'correct_answer')) {
            DB::statement('ALTER TABLE questions MODIFY correct_answer TEXT');
        }
    }

    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            if (Schema::hasColumn('questions', 'meta')) {
                $table->dropColumn('meta');
            }
        });

        $driver = Schema::getConnection()->getDriverName();
        $supportsModify = in_array($driver, ['mysql', 'mariadb'], true);

        if ($supportsModify && Schema::hasColumn('questions', 'correct_answer')) {
            DB::statement('ALTER TABLE questions MODIFY correct_answer VARCHAR(10)');
        }
    }
};
