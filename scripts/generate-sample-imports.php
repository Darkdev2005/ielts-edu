<?php

require __DIR__.'/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$base = __DIR__.'/../storage/app/samples';
if (!is_dir($base)) {
    mkdir($base, 0777, true);
}

$sets = [
    'lesson_questions' => [
        'headers' => ['question_type', 'prompt', 'option_a', 'option_b', 'option_c', 'option_d', 'correct_answer', 'ai_explanation', 'matching_items'],
        'rows' => [
            ['mcq', 'What is the main idea?', 'Parks are noisy', 'Parks help communities', 'Parks are private', 'Parks are dangerous', 'B', 'The passage says parks bring people together.', ''],
            ['tfng', 'The writer says parks are only for sports.', '', '', '', '', 'FALSE', 'The text says parks are for everyone.', ''],
            ['completion', 'Complete: Parks provide ____ space for people to relax.', '', '', '', '', 'green|public', 'The passage mentions green spaces.', ''],
            ['matching', 'Match headings to paragraphs.', 'Heading i', 'Heading ii', 'Heading iii', 'Heading iv', '1:A|2:C|3:B', 'Each paragraph has a matching heading.', 'Paragraph A|Paragraph B|Paragraph C'],
            ['mcq', 'What is one purpose of parks?', 'Sell products', 'Provide green space', 'Replace roads', 'Reduce traffic lights', 'B', 'Parks are described as green spaces.', ''],
        ],
    ],
    'mock_questions' => [
        'headers' => ['question_type', 'question_text', 'option_a', 'option_b', 'option_c', 'option_d', 'correct_answer', 'order_index'],
        'rows' => [
            ['mcq', 'What is the main idea of paragraph A?', 'Climate is stable', 'Cities need green zones', 'Cars are banned', 'People avoid parks', 'B', 1],
            ['tfng', 'The writer says all parks close at night.', '', '', '', '', 'FALSE', 2],
            ['completion', 'Complete: The council invested in ____ spaces.', '', '', '', '', 'public', 3],
            ['matching', 'Match heading to paragraph B.', '', '', '', '', 'iii', 4],
        ],
    ],
    'grammar_exercises' => [
        'headers' => [
            'topic_key',
            'exercise_id',
            'type',
            'correct_answer',
            'sort_order',
            'sentence_with_gap',
            'full_sentence',
            'option_a',
            'option_b',
            'option_c',
            'option_d',
            'explanation_uz',
            'explanation_en',
            'explanation_ru',
        ],
        'rows' => [
            ['present-simple', 'ps-01', 'mcq', 'B', 1, '', 'She ___ to school every day.', 'go', 'goes', 'going', 'gone', 'Izoh: He/She/It -s oladi.', 'Explanation: He/She/It takes -s.', 'RU: He/She/It -s.'],
            ['present-simple', 'ps-02', 'mcq', 'A', 2, '', 'They ___ football on Sundays.', 'play', 'plays', 'playing', 'played', 'Izoh: they uchun base verb.', 'Explanation: use base verb for they.', 'RU: base verb.'],
            ['present-simple', 'ps-03', 'mcq', 'A', 3, '', 'I ___ not like tea.', 'do', 'does', 'am', 'is', 'Izoh: I/you/we/they uchun do not.', 'Explanation: use do not with I/you/we/they.', 'RU: do not.'],
        ],
    ],
    'vocabulary_items' => [
        'headers' => ['term', 'definition', 'definition_uz', 'definition_ru', 'part_of_speech', 'example'],
        'rows' => [
            ['appointment', 'a meeting at a fixed time', 'belgilangan vaqtdagi uchrashuv', 'встреча в назначенное время', 'noun', 'I have a doctor appointment.'],
            ['commute', 'to travel between home and work', 'uy va ish orasida qatnash', 'ездить на работу', 'verb', 'She commutes by train.'],
            ['routine', 'a regular way of doing things', 'muntazam odat', 'распорядок', 'noun', 'My morning routine starts early.'],
            ['schedule', 'a plan of activities or times', 'reja jadvali', 'расписание', 'noun', 'My schedule is busy.'],
            ['confirm', 'to say something is true', 'tasdiqlamoq', 'подтвердить', 'verb', 'Please confirm your email.'],
        ],
    ],
];

foreach ($sets as $name => $data) {
    $csvPath = $base.'/'.$name.'_sample.csv';
    $handle = fopen($csvPath, 'w');
    fputcsv($handle, $data['headers'], ',', '"', '\\');
    foreach ($data['rows'] as $row) {
        fputcsv($handle, $row, ',', '"', '\\');
    }
    fclose($handle);

    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->fromArray(array_merge([$data['headers']], $data['rows']), null, 'A1');
    $writer = new Xlsx($spreadsheet);
    $writer->save($base.'/'.$name.'_sample.xlsx');
}

echo "Sample import files generated in {$base}\n";
