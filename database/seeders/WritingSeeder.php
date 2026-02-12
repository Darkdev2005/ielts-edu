<?php

namespace Database\Seeders;

use App\Models\WritingTask;
use Illuminate\Database\Seeder;

class WritingSeeder extends Seeder
{
    public function run(): void
    {
        $tasks = [
            [
                'title' => 'Task 1: City transport chart',
                'task_type' => 'task1',
                'prompt' => 'The chart below shows the percentage of commuters using different modes of transport in a city in 2000 and 2020. Summarise the information by selecting and reporting the main features and make comparisons where relevant.',
                'difficulty' => 'B1',
                'min_words' => 150,
                'time_limit_minutes' => 20,
            ],
            [
                'title' => 'Task 2: Technology and education',
                'task_type' => 'task2',
                'prompt' => 'Some people believe that online learning will replace traditional classrooms in the future. To what extent do you agree or disagree?',
                'difficulty' => 'B2',
                'min_words' => 250,
                'time_limit_minutes' => 40,
            ],
        ];

        foreach ($tasks as $task) {
            WritingTask::firstOrCreate(
                ['title' => $task['title']],
                $task
            );
        }
    }
}
