<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('grammar_attempt_answers', 'selected_answer')) {
            return;
        }

        $driver = DB::getDriverName();
        if ($driver !== 'mysql') {
            return;
        }

        DB::statement('ALTER TABLE grammar_attempt_answers MODIFY selected_answer TEXT NULL');
    }

    public function down(): void
    {
        if (!Schema::hasColumn('grammar_attempt_answers', 'selected_answer')) {
            return;
        }

        $driver = DB::getDriverName();
        if ($driver !== 'mysql') {
            return;
        }

        DB::statement('ALTER TABLE grammar_attempt_answers MODIFY selected_answer VARCHAR(1) NULL');
    }
};
