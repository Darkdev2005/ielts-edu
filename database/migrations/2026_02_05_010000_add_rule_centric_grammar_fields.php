<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('grammar_topics', function (Blueprint $table) {
            $table->string('topic_key')->nullable()->after('slug');
            $table->string('title_uz')->nullable()->after('topic_key');
            $table->string('title_en')->nullable()->after('title_uz');
            $table->string('title_ru')->nullable()->after('title_en');
            $table->text('description_uz')->nullable()->after('description');
            $table->text('description_en')->nullable()->after('description_uz');
            $table->text('description_ru')->nullable()->after('description_en');
            $table->unsignedInteger('sort_order')->default(0)->after('cefr_level');
            $table->unique('topic_key');
        });

        Schema::table('grammar_rules', function (Blueprint $table) {
            $table->string('rule_key')->nullable()->after('rule_id');
            $table->string('cefr_level', 2)->nullable()->after('level');
            $table->text('rule_text_uz')->nullable()->after('content_json');
            $table->text('rule_text_en')->nullable()->after('rule_text_uz');
            $table->text('rule_text_ru')->nullable()->after('rule_text_en');
            $table->text('formula')->nullable()->after('rule_text_ru');
            $table->text('example_uz')->nullable()->after('formula');
            $table->text('example_en')->nullable()->after('example_uz');
            $table->text('example_ru')->nullable()->after('example_en');
            $table->text('negative_example')->nullable()->after('example_ru');
            $table->text('common_mistake')->nullable()->after('negative_example');
            $table->text('correct_form')->nullable()->after('common_mistake');
            $table->unique(['grammar_topic_id', 'rule_key']);
        });

        Schema::table('grammar_exercises', function (Blueprint $table) {
            $table->foreignId('grammar_rule_id')->nullable()->after('grammar_topic_id')
                ->constrained('grammar_rules')->cascadeOnDelete();
            $table->string('exercise_type', 30)->nullable()->after('type');
            $table->text('question')->nullable()->after('prompt');
            $table->text('explanation_uz')->nullable()->after('explanation');
            $table->text('explanation_en')->nullable()->after('explanation_uz');
            $table->text('explanation_ru')->nullable()->after('explanation_en');
            $table->string('cefr_level', 2)->nullable()->after('explanation_ru');
            $table->index(['grammar_rule_id']);
            $table->index(['exercise_type']);
        });
    }

    public function down(): void
    {
        Schema::table('grammar_exercises', function (Blueprint $table) {
            $table->dropIndex(['grammar_rule_id']);
            $table->dropIndex(['exercise_type']);
            $table->dropForeign(['grammar_rule_id']);
            $table->dropColumn([
                'grammar_rule_id',
                'exercise_type',
                'question',
                'explanation_uz',
                'explanation_en',
                'explanation_ru',
                'cefr_level',
            ]);
        });

        Schema::table('grammar_rules', function (Blueprint $table) {
            $table->dropUnique(['grammar_topic_id', 'rule_key']);
            $table->dropColumn([
                'rule_key',
                'cefr_level',
                'rule_text_uz',
                'rule_text_en',
                'rule_text_ru',
                'formula',
                'example_uz',
                'example_en',
                'example_ru',
                'negative_example',
                'common_mistake',
                'correct_form',
            ]);
        });

        Schema::table('grammar_topics', function (Blueprint $table) {
            $table->dropUnique(['topic_key']);
            $table->dropColumn([
                'topic_key',
                'title_uz',
                'title_en',
                'title_ru',
                'description_uz',
                'description_en',
                'description_ru',
                'sort_order',
            ]);
        });
    }
};
