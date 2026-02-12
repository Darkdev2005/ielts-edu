<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Admin User',
                'password' => 'password',
                'is_admin' => true,
                'is_super_admin' => true,
                'cefr_level' => 'B1',
            ]
        );

        User::firstOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Test User',
                'password' => 'password',
                'cefr_level' => 'A2',
            ]
        );

        $this->call(PlanFeatureSeeder::class);
        $this->call(LessonSeeder::class);
        $this->call(VocabularySeeder::class);
        $this->call(GrammarSeeder::class);
        $this->call(WritingSeeder::class);
        $this->call(SpeakingSeeder::class);
    }
}
