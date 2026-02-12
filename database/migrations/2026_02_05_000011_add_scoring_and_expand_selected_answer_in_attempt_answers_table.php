<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attempt_answers', function (Blueprint $table) {
            if (!Schema::hasColumn('attempt_answers', 'score')) {
                $table->unsignedInteger('score')->default(0)->after('is_correct');
            }
            if (!Schema::hasColumn('attempt_answers', 'max_score')) {
                $table->unsignedInteger('max_score')->default(1)->after('score');
            }
        });

        $driver = Schema::getConnection()->getDriverName();
        $supportsModify = in_array($driver, ['mysql', 'mariadb'], true);

        if ($supportsModify && Schema::hasColumn('attempt_answers', 'selected_answer')) {
            DB::statement('ALTER TABLE attempt_answers MODIFY selected_answer TEXT NULL');
        }
    }

    public function down(): void
    {
        Schema::table('attempt_answers', function (Blueprint $table) {
            if (Schema::hasColumn('attempt_answers', 'score')) {
                $table->dropColumn('score');
            }
            if (Schema::hasColumn('attempt_answers', 'max_score')) {
                $table->dropColumn('max_score');
            }
        });

        $driver = Schema::getConnection()->getDriverName();
        $supportsModify = in_array($driver, ['mysql', 'mariadb'], true);

        if ($supportsModify && Schema::hasColumn('attempt_answers', 'selected_answer')) {
            DB::statement('ALTER TABLE attempt_answers MODIFY selected_answer VARCHAR(10) NULL');
        }
    }
};
