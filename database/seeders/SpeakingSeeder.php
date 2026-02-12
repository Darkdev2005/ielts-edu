<?php

namespace Database\Seeders;

use App\Models\SpeakingPrompt;
use Illuminate\Database\Seeder;

class SpeakingSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            ['part' => 1, 'prompt' => 'Do you enjoy studying alone or with others? Why?', 'difficulty' => 'A2'],
            ['part' => 1, 'prompt' => 'Describe your daily routine in the morning.', 'difficulty' => 'A1'],
            ['part' => 2, 'prompt' => 'Describe a memorable day in the last month. You should say what happened and why it was memorable.', 'difficulty' => 'B1'],
            ['part' => 2, 'prompt' => 'Describe a skill you want to learn. You should say what it is, why you want it, and how you plan to learn it.', 'difficulty' => 'B1'],
            ['part' => 3, 'prompt' => 'How does technology change the way people learn? Why?', 'difficulty' => 'B2'],
            ['part' => 3, 'prompt' => 'What are the advantages and disadvantages of studying online?', 'difficulty' => 'B2'],
        ];

        foreach ($items as $item) {
            SpeakingPrompt::firstOrCreate(
                ['part' => $item['part'], 'prompt' => $item['prompt']],
                [
                    'difficulty' => $item['difficulty'],
                    'is_active' => true,
                ]
            );
        }
    }
}
