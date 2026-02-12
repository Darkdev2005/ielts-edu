<?php

namespace Database\Seeders;

use App\Models\MockSection;
use App\Models\MockTest;
use Illuminate\Database\Seeder;

class MockReadingSeeder extends Seeder
{
    public function run(): void
    {
        $test = MockTest::updateOrCreate(
            [
                'module' => 'reading',
                'title' => 'Reading Mock Test 01 | Sustainable Communities',
            ],
            [
                'description' => 'A full 40-question reading mock with 3 passages and strict timing.',
                'time_limit' => 3600,
                'total_questions' => 40,
                'is_active' => true,
            ]
        );

        $sections = [
            1 => [
                'title' => 'Passage 1: Rooftop Gardens',
                'passage_text' => "A. In 2024, the city launched a rooftop garden pilot to reduce summer heat in dense neighborhoods. The first sites were public libraries and two secondary schools.\n\nB. Engineers reported that roofs with new planting beds were up to 7 degrees cooler in July than nearby concrete roofs. However, not every building could join the project because some roofs were too old for extra weight.\n\nC. Funding came from a municipal climate grant with additional support from local businesses. Volunteers helped maintain about one third of all sites. At schools, science teachers used the gardens for practical lessons twice a month. Beehives were tested at only two locations.",
                'questions' => [
                    ['question_type' => 'mcq', 'question_text' => 'What was the main goal of the rooftop garden pilot?', 'options_json' => ['A' => 'To create new parking space', 'B' => 'To reduce urban heat', 'C' => 'To replace school canteens', 'D' => 'To produce food for supermarkets'], 'correct_answer' => 'B'],
                    ['question_type' => 'mcq', 'question_text' => 'Who helped maintain many of the garden sites?', 'options_json' => ['A' => 'National army units', 'B' => 'Only paid contractors', 'C' => 'Local volunteers', 'D' => 'International students'], 'correct_answer' => 'C'],
                    ['question_type' => 'mcq', 'question_text' => 'Which source provided core funding for the project?', 'options_json' => ['A' => 'Tourism tax', 'B' => 'University tuition fees', 'C' => 'Private banks only', 'D' => 'Municipal climate grant'], 'correct_answer' => 'D'],
                    ['question_type' => 'mcq', 'question_text' => 'How often did schools use the gardens for science lessons?', 'options_json' => ['A' => 'Twice a month', 'B' => 'Every day', 'C' => 'Once a year', 'D' => 'Every weekend'], 'correct_answer' => 'A'],

                    ['question_type' => 'tfng', 'question_text' => 'All rooftops in the city were suitable for conversion.', 'correct_answer' => 'FALSE'],
                    ['question_type' => 'tfng', 'question_text' => 'The roof temperature comparison was measured in July.', 'correct_answer' => 'TRUE'],
                    ['question_type' => 'tfng', 'question_text' => 'Private companies paid for the entire program.', 'correct_answer' => 'FALSE'],
                    ['question_type' => 'tfng', 'question_text' => 'Beehives were introduced at every rooftop site.', 'correct_answer' => 'FALSE'],

                    ['question_type' => 'completion', 'question_text' => 'Roofs with gardens were up to ___ degrees cooler.', 'correct_answer' => '7'],
                    ['question_type' => 'completion', 'question_text' => 'Schools used gardens for science lessons ___ a month.', 'correct_answer' => 'twice'],
                    ['question_type' => 'completion', 'question_text' => 'Volunteers maintained about one ___ of sites.', 'correct_answer' => 'third'],

                    ['question_type' => 'matching', 'question_text' => 'Match heading to paragraph A (i practical school use, ii reason for launch, iii funding model, iv structural limits).', 'correct_answer' => 'ii'],
                    ['question_type' => 'matching', 'question_text' => 'Match heading to paragraph B (i practical school use, ii reason for launch, iii funding model, iv structural limits).', 'correct_answer' => 'iv'],
                    ['question_type' => 'matching', 'question_text' => 'Match heading to paragraph C (i practical school use, ii reason for launch, iii funding model, iv structural limits).', 'correct_answer' => 'i'],
                ],
            ],
            2 => [
                'title' => 'Passage 2: Remote Work Hubs',
                'passage_text' => "A. Several small towns opened shared remote-work hubs in unused municipal buildings. Officials hoped this would attract professionals who no longer needed to commute daily to major cities.\n\nB. Six months later, nearby cafes and small shops reported better weekday trade. One local business group measured an 18 percent increase in lunch-time sales.\n\nC. Not all effects were positive. Housing demand rose quickly, and average rents increased by 9 percent. The council announced an affordable-housing plan, but construction had not started by year end.\n\nD. To improve local connections, hub managers organized community events every Wednesday evening. Members said these meetings helped them find local partners and clients.",
                'questions' => [
                    ['question_type' => 'mcq', 'question_text' => 'Why were remote-work hubs opened?', 'options_json' => ['A' => 'To reuse empty public buildings and attract workers', 'B' => 'To replace local schools', 'C' => 'To cut tourism numbers', 'D' => 'To close cafes in town centers'], 'correct_answer' => 'A'],
                    ['question_type' => 'mcq', 'question_text' => 'What unresolved issue did towns face?', 'options_json' => ['A' => 'Lack of internet access', 'B' => 'Rising rents', 'C' => 'No job applications', 'D' => 'Drop in business sales'], 'correct_answer' => 'B'],
                    ['question_type' => 'mcq', 'question_text' => 'What happened to local cafe sales?', 'options_json' => ['A' => 'They fell by 18 percent', 'B' => 'They stayed unchanged', 'C' => 'They rose by 18 percent', 'D' => 'They doubled in one week'], 'correct_answer' => 'C'],

                    ['question_type' => 'tfng', 'question_text' => 'Every hub member worked in the IT industry.', 'correct_answer' => 'NOT_GIVEN'],
                    ['question_type' => 'tfng', 'question_text' => 'Local cafes earned more after hubs opened.', 'correct_answer' => 'TRUE'],
                    ['question_type' => 'tfng', 'question_text' => 'Affordable housing was completed before year end.', 'correct_answer' => 'FALSE'],
                    ['question_type' => 'tfng', 'question_text' => 'Community events took place daily.', 'correct_answer' => 'FALSE'],

                    ['question_type' => 'completion', 'question_text' => 'Lunch-time sales increased by ___ percent.', 'correct_answer' => '18'],
                    ['question_type' => 'completion', 'question_text' => 'Average rents increased by ___ percent.', 'correct_answer' => '9'],
                    ['question_type' => 'completion', 'question_text' => 'Events were held every ___ evening.', 'correct_answer' => 'wednesday'],

                    ['question_type' => 'matching', 'question_text' => 'Which paragraph (A-D) mentions business growth?', 'correct_answer' => 'b'],
                    ['question_type' => 'matching', 'question_text' => 'Which paragraph (A-D) describes housing pressure?', 'correct_answer' => 'c'],
                    ['question_type' => 'matching', 'question_text' => 'Which paragraph (A-D) explains reuse of public buildings?', 'correct_answer' => 'a'],
                ],
            ],
            3 => [
                'title' => 'Passage 3: Dark-Sky Tourism',
                'passage_text' => "A. A mountain region created a dark-sky trail connecting five villages. The project aimed to attract visitors interested in astronomy while supporting local guesthouses outside peak summer months.\n\nB. To protect night vision, visitors received red-light torches at entry points. Local guides completed six months of training before leading groups.\n\nC. Weather remained a challenge. Cloud cover was frequent in spring, and some tours were cancelled. Even so, businesses reported that the season now continued well into autumn.\n\nD. Professional astronomers welcomed the project but asked for quieter vehicle routes at night to reduce noise near observation points.",
                'questions' => [
                    ['question_type' => 'mcq', 'question_text' => 'How many villages does the dark-sky trail connect?', 'options_json' => ['A' => 'Two', 'B' => 'Three', 'C' => 'Four', 'D' => 'Five'], 'correct_answer' => 'D'],
                    ['question_type' => 'mcq', 'question_text' => 'Why were red-light torches provided?', 'options_json' => ['A' => 'To mark parking areas', 'B' => 'To protect night vision', 'C' => 'To increase brightness for photos', 'D' => 'To signal emergency exits'], 'correct_answer' => 'B'],
                    ['question_type' => 'mcq', 'question_text' => 'How long was guide training?', 'options_json' => ['A' => 'Two weeks', 'B' => 'Three months', 'C' => 'Six months', 'D' => 'One year'], 'correct_answer' => 'C'],
                    ['question_type' => 'mcq', 'question_text' => 'What economic effect was reported?', 'options_json' => ['A' => 'Tourism extended into autumn', 'B' => 'Hotels closed in spring', 'C' => 'Ticket prices were cut in half', 'D' => 'The trail reduced local jobs'], 'correct_answer' => 'A'],

                    ['question_type' => 'tfng', 'question_text' => 'Spring weather often included cloud cover.', 'correct_answer' => 'TRUE'],
                    ['question_type' => 'tfng', 'question_text' => 'Visitors were encouraged to use white torches.', 'correct_answer' => 'FALSE'],
                    ['question_type' => 'tfng', 'question_text' => 'The trail was designed only for professional astronomers.', 'correct_answer' => 'FALSE'],
                    ['question_type' => 'tfng', 'question_text' => 'Night-time vehicle noise was raised as a concern.', 'correct_answer' => 'TRUE'],

                    ['question_type' => 'completion', 'question_text' => 'Guides received ___ months of training.', 'correct_answer' => '6'],
                    ['question_type' => 'completion', 'question_text' => 'The trail connects ___ villages.', 'correct_answer' => '5'],
                    ['question_type' => 'completion', 'question_text' => 'The tourist season now continues into ___.', 'correct_answer' => 'autumn'],

                    ['question_type' => 'matching', 'question_text' => 'Which paragraph (A-D) describes visitor equipment?', 'correct_answer' => 'b'],
                    ['question_type' => 'matching', 'question_text' => 'Which paragraph (A-D) focuses on weather risk?', 'correct_answer' => 'c'],
                ],
            ],
        ];

        $totalQuestions = 0;

        foreach ($sections as $sectionNumber => $payload) {
            $section = MockSection::updateOrCreate(
                [
                    'mock_test_id' => $test->id,
                    'section_number' => $sectionNumber,
                ],
                [
                    'title' => $payload['title'],
                    'passage_text' => $payload['passage_text'],
                    'audio_url' => null,
                    'audio_disk' => null,
                    'audio_path' => null,
                ]
            );

            $section->questions()->delete();

            foreach ($payload['questions'] as $index => $question) {
                $section->questions()->create([
                    'question_type' => $question['question_type'],
                    'question_text' => $question['question_text'],
                    'options_json' => $question['options_json'] ?? null,
                    'correct_answer' => $question['correct_answer'],
                    'order_index' => $index + 1,
                ]);
            }

            $count = count($payload['questions']);
            $totalQuestions += $count;

            $section->update([
                'question_count' => $count,
            ]);
        }

        $test->update([
            'total_questions' => $totalQuestions,
            'time_limit' => 3600,
            'is_active' => true,
        ]);
    }
}
