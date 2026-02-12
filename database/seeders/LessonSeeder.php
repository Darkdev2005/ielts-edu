<?php

namespace Database\Seeders;

use App\Models\Lesson;
use App\Models\Question;
use App\Models\User;
use Illuminate\Database\Seeder;

class LessonSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::firstWhere('is_admin', true);
        if (!$admin) {
            return;
        }

        $lesson = Lesson::create([
            'title' => 'City Parks and Community Life',
            'type' => 'reading',
            'content_text' => 'City parks are more than green spaces. They offer places to meet, exercise, and relax...',
            'difficulty' => 'B1',
            'created_by' => $admin->id,
        ]);

        Question::create([
            'lesson_id' => $lesson->id,
            'type' => 'mcq',
            'prompt' => 'What is one main benefit of city parks mentioned in the text?',
            'options' => ['They increase traffic', 'They create spaces to meet and relax', 'They replace libraries', 'They reduce public transport'],
            'correct_answer' => 'B',
            'ai_explanation' => 'The passage highlights parks as spaces to meet, exercise, and relax.',
        ]);
    }
}
