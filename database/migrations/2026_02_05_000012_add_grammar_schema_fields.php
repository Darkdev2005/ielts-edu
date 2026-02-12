<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();
        $supportsModify = in_array($driver, ['mysql', 'mariadb'], true);
        $concatRule = $driver === 'sqlite'
            ? "('rule-' || id)"
            : "CONCAT('rule-', id)";
        $concatExercise = $driver === 'sqlite'
            ? "('ex-' || id)"
            : "CONCAT('ex-', id)";

        Schema::table('grammar_topics', function (Blueprint $table) {
            $table->string('slug')->nullable()->after('title');
        });

        Schema::table('grammar_rules', function (Blueprint $table) {
            $table->string('rule_id')->nullable()->after('grammar_topic_id');
            $table->string('level', 10)->nullable()->after('rule_id');
            $table->string('rule_type', 50)->nullable()->after('level');
            $table->json('content_json')->nullable()->after('content');
        });

        Schema::table('grammar_exercises', function (Blueprint $table) {
            $table->string('exercise_id')->nullable()->after('grammar_topic_id');
            $table->string('type', 30)->nullable()->after('exercise_id');
            $table->json('payload_json')->nullable()->after('explanation');
        });

        if ($supportsModify) {
            DB::statement('ALTER TABLE grammar_exercises MODIFY correct_answer VARCHAR(255)');
        }

        $topics = DB::table('grammar_topics')->select('id', 'title')->get();
        $used = [];
        foreach ($topics as $topic) {
            $base = Str::slug((string) $topic->title);
            if ($base === '') {
                $base = 'topic-'.$topic->id;
            }
            $slug = $base;
            $suffix = 2;
            while (in_array($slug, $used, true)) {
                $slug = $base.'-'.$suffix;
                $suffix += 1;
            }
            $used[] = $slug;
            DB::table('grammar_topics')
                ->where('id', $topic->id)
                ->update(['slug' => $slug]);
        }

        DB::table('grammar_rules')
            ->whereNull('rule_id')
            ->update(['rule_id' => DB::raw($concatRule)]);

        DB::table('grammar_exercises')
            ->whereNull('exercise_id')
            ->update(['exercise_id' => DB::raw($concatExercise)]);

        DB::table('grammar_exercises')->whereNull('type')->update(['type' => 'mcq']);

        Schema::table('grammar_topics', function (Blueprint $table) {
            $table->unique('slug');
        });

        Schema::table('grammar_rules', function (Blueprint $table) {
            $table->unique(['grammar_topic_id', 'rule_id']);
            $table->index(['grammar_topic_id', 'sort_order']);
            $table->index(['grammar_topic_id', 'rule_type']);
        });

        Schema::table('grammar_exercises', function (Blueprint $table) {
            $table->unique(['grammar_topic_id', 'exercise_id']);
            $table->index(['grammar_topic_id', 'sort_order']);
            $table->index(['grammar_topic_id', 'type']);
        });
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();
        $supportsModify = in_array($driver, ['mysql', 'mariadb'], true);

        Schema::table('grammar_rules', function (Blueprint $table) {
            $table->dropIndex(['grammar_topic_id', 'rule_type']);
            $table->dropIndex(['grammar_topic_id', 'sort_order']);
            $table->dropUnique(['grammar_topic_id', 'rule_id']);
            $table->dropColumn(['rule_id', 'level', 'rule_type', 'content_json']);
        });

        Schema::table('grammar_exercises', function (Blueprint $table) {
            $table->dropIndex(['grammar_topic_id', 'type']);
            $table->dropIndex(['grammar_topic_id', 'sort_order']);
            $table->dropUnique(['grammar_topic_id', 'exercise_id']);
            $table->dropColumn(['exercise_id', 'type', 'payload_json']);
        });

        Schema::table('grammar_topics', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->dropColumn('slug');
        });

        if ($supportsModify) {
            DB::statement('ALTER TABLE grammar_exercises MODIFY correct_answer VARCHAR(1)');
        }
    }
};
