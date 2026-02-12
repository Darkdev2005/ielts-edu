<?php
require __DIR__ . '/../vendor/autoload.php';

use Illuminate\Contracts\Console\Kernel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use App\Models\MockTest;
use App\Models\MockSection;

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$options = getopt('', ['file:', 'title::', 'module::', 'test-id::', 'dry-run']);

$file = $options['file'] ?? (__DIR__ . '/../storage/app/imports/IELTS_EDU_Reading_Mock_Full_REAL_plain.xlsx');
$module = strtolower(trim((string) ($options['module'] ?? 'reading')));
$title = trim((string) ($options['title'] ?? 'Reading Mock Full (Imported)'));
$testId = isset($options['test-id']) ? (int) $options['test-id'] : null;
$dryRun = array_key_exists('dry-run', $options);

if (!file_exists($file)) {
    fwrite(STDERR, "File not found: {$file}\n");
    exit(1);
}

$spreadsheet = IOFactory::load($file);
$sectionsSheet = $spreadsheet->getSheetByName('Sections');
$questionsSheet = $spreadsheet->getSheetByName('Questions');

if (!$sectionsSheet || !$questionsSheet) {
    fwrite(STDERR, "Expected sheets 'Sections' and 'Questions' not found.\n");
    exit(1);
}

$normalizeHeader = function (string $value): string {
    $value = preg_replace('/^\xEF\xBB\xBF/', '', $value);
    $value = strtolower(trim($value));
    $value = str_replace([' ', '-'], '_', $value);
    return preg_replace('/_+/', '_', $value) ?? $value;
};

$sheetToRows = function ($sheet) use ($normalizeHeader): array {
    $highestRow = $sheet->getHighestRow();
    $highestCol = $sheet->getHighestColumn();
    $highestColIndex = Coordinate::columnIndexFromString($highestCol);
    $headerRow = $sheet->rangeToArray('A1:' . Coordinate::stringFromColumnIndex($highestColIndex) . '1', null, true, false)[0];
    $headers = array_map(fn ($h) => $normalizeHeader((string) $h), $headerRow);

    $rows = [];
    for ($row = 2; $row <= $highestRow; $row++) {
        $cells = $sheet->rangeToArray('A' . $row . ':' . Coordinate::stringFromColumnIndex($highestColIndex) . $row, null, true, false)[0];
        if (count($cells) === 1 && trim((string) ($cells[0] ?? '')) === '') {
            continue;
        }
        $data = [];
        foreach ($headers as $index => $key) {
            if ($key === '') {
                continue;
            }
            $data[$key] = $cells[$index] ?? null;
        }
        $rows[] = $data;
    }

    return $rows;
};

$sections = $sheetToRows($sectionsSheet);
$questions = $sheetToRows($questionsSheet);

$sections = array_values(array_filter($sections, fn ($row) => strtolower(trim((string) ($row['module'] ?? ''))) === $module));
$questions = array_values(array_filter($questions, fn ($row) => strtolower(trim((string) ($row['module'] ?? ''))) === $module));

if (empty($sections)) {
    fwrite(STDERR, "No sections found for module {$module}.\n");
    exit(1);
}

$parseOptions = function ($raw): array {
    $raw = trim((string) $raw);
    $options = ['A' => '', 'B' => '', 'C' => '', 'D' => ''];
    if ($raw === '') {
        return $options;
    }

    $parts = preg_split('/\s*\|\s*/', $raw);
    if (count($parts) === 1) {
        $parts = preg_split('/\r\n|\r|\n/', $raw);
    }

    $letters = ['A', 'B', 'C', 'D'];
    $seq = 0;
    foreach ($parts as $part) {
        $part = trim((string) $part);
        if ($part === '') {
            continue;
        }
        if (preg_match('/^([A-D])\s*[\)\.:\-]?\s*(.+)$/i', $part, $matches)) {
            $letter = strtoupper($matches[1]);
            $text = trim($matches[2]);
        } else {
            $letter = $letters[$seq] ?? null;
            $text = $part;
            $seq += 1;
        }
        if ($letter) {
            $options[$letter] = $text;
        }
    }

    return $options;
};

$mapQuestionType = function (string $value): ?string {
    $type = strtolower(trim($value));
    $type = str_replace([' ', '-'], '_', $type);
    $type = preg_replace('/_+/', '_', $type) ?? $type;

    return match ($type) {
        'mcq_single', 'mcq', 'multiple_choice', 'multiplechoice' => 'mcq',
        'tfng', 'true_false_not_given', 'truefalse_notgiven', 'tf_ng' => 'tfng',
        'y/n/ng', 'ynng', 'yes_no_not_given', 'yesno_notgiven' => 'ynng',
        'sentence_completion', 'summary_completion', 'completion' => 'completion',
        'matching_headings', 'matching_information', 'matching' => 'matching',
        default => null,
    };
};

$normalizeCorrect = function (string $type, $value, array $options): string {
    if ($type === 'ynng' && is_bool($value)) {
        $value = $value ? 'YES' : 'NO';
    } elseif ($type === 'ynng' && is_numeric($value)) {
        $value = (string) (int) $value;
        if ($value === '1') {
            $value = 'YES';
        } elseif ($value === '0') {
            $value = 'NO';
        }
    } elseif ($type === 'tfng' && is_bool($value)) {
        $value = $value ? 'TRUE' : 'FALSE';
    } elseif ($type === 'tfng' && is_numeric($value)) {
        $value = (string) (int) $value;
        if ($value === '1') {
            $value = 'TRUE';
        } elseif ($value === '0') {
            $value = 'FALSE';
        }
    }

    $answer = trim((string) $value);
    if ($answer === '') {
        return '';
    }

    if ($type === 'mcq') {
        $upper = strtoupper($answer);
        if (is_numeric($upper)) {
            $map = ['1' => 'A', '2' => 'B', '3' => 'C', '4' => 'D'];
            return $map[$upper] ?? '';
        }
        if (preg_match('/^([A-D])\b/', $upper, $matches)) {
            return $matches[1];
        }
        $needle = strtolower($answer);
        foreach ($options as $letter => $text) {
            if ($needle === strtolower(trim((string) $text))) {
                return $letter;
            }
        }
        return '';
    }

    if ($type === 'tfng') {
        $normalized = strtoupper($answer);
        $normalized = str_replace(['NOT GIVEN', 'NOT-GIVEN', 'NOTGIVEN', 'NG'], 'NOT_GIVEN', $normalized);
        if ($normalized === 'T') {
            $normalized = 'TRUE';
        } elseif ($normalized === 'F') {
            $normalized = 'FALSE';
        }
        return in_array($normalized, ['TRUE', 'FALSE', 'NOT_GIVEN'], true) ? $normalized : '';
    }

    if ($type === 'ynng') {
        $normalized = strtoupper($answer);
        $normalized = str_replace(['NOT GIVEN', 'NOT-GIVEN', 'NOTGIVEN', 'NG'], 'NOT_GIVEN', $normalized);
        if ($normalized === 'Y') {
            $normalized = 'YES';
        } elseif ($normalized === 'N') {
            $normalized = 'NO';
        }
        return in_array($normalized, ['YES', 'NO', 'NOT_GIVEN'], true) ? $normalized : '';
    }

    return $answer;
};

$instructionJoin = function ($instruction, $questionText): string {
    $instruction = trim((string) $instruction);
    $questionText = trim((string) $questionText);
    if ($instruction !== '') {
        return $instruction . "\n" . $questionText;
    }
    return $questionText;
};

$test = null;
if ($testId) {
    $test = MockTest::find($testId);
    if (!$test) {
        fwrite(STDERR, "MockTest not found for id {$testId}.\n");
        exit(1);
    }
} else {
    $test = MockTest::updateOrCreate(
        ['module' => $module, 'title' => $title],
        [
            'description' => 'Imported from Excel mock file',
            'time_limit' => 3600,
            'total_questions' => 0,
            'is_active' => true,
        ]
    );
}

$sectionMap = [];
$imported = 0;
$errors = [];

$runImport = function () use (
    $sections,
    $questions,
    $module,
    $test,
    $parseOptions,
    $mapQuestionType,
    $normalizeCorrect,
    $instructionJoin,
    &$sectionMap,
    &$imported,
    &$errors,
    $dryRun
): void {
    foreach ($sections as $row) {
        $sectionNumber = (int) ($row['section_number'] ?? 0);
        if ($sectionNumber <= 0) {
            $errors[] = 'Invalid section_number in Sections sheet.';
            continue;
        }

        $title = trim((string) ($row['section_title'] ?? ''));
        $passage = trim((string) ($row['passage_text_or_transcript'] ?? ''));
        $audioSource = strtolower(trim((string) ($row['audio_source_type'] ?? '')));
        $audioValue = trim((string) ($row['audio_url_or_path'] ?? ''));

        if ($dryRun) {
            $sectionMap[$sectionNumber] = $sectionNumber;
            continue;
        }

        $section = MockSection::updateOrCreate(
            [
                'mock_test_id' => $test->id,
                'section_number' => $sectionNumber,
            ],
            [
                'title' => $title !== '' ? $title : null,
                'passage_text' => $passage !== '' ? $passage : null,
                'audio_url' => $audioValue !== '' ? $audioValue : null,
                'audio_disk' => null,
                'audio_path' => null,
                'question_count' => 0,
            ]
        );

        $section->questions()->delete();
        $sectionMap[$sectionNumber] = $section->id;
    }

    foreach ($questions as $row) {
        $sectionNumber = (int) ($row['section_number'] ?? 0);
        if ($sectionNumber <= 0 || !isset($sectionMap[$sectionNumber])) {
            $errors[] = 'Question references missing section: ' . ($row['section_number'] ?? '');
            continue;
        }

        $questionTypeRaw = (string) ($row['question_type'] ?? '');
        $questionType = $mapQuestionType($questionTypeRaw);
        if (!$questionType) {
            $errors[] = 'Unknown question type: ' . $questionTypeRaw;
            continue;
        }

        $questionText = $instructionJoin($row['instruction_text'] ?? '', $row['question_text'] ?? '');
        if ($questionText === '') {
            $errors[] = 'Empty question text for section ' . $sectionNumber;
            continue;
        }

        $orderIndex = (int) ($row['order_in_section'] ?? 0);
        if ($orderIndex <= 0) {
            $orderIndex = (int) ($row['question_number'] ?? 0);
        }
        if ($orderIndex <= 0) {
            $orderIndex = $imported + 1;
        }

        $options = $questionType === 'mcq' ? $parseOptions($row['options'] ?? '') : [];
        if ($questionType === 'mcq' && in_array('', $options, true)) {
            $errors[] = 'Missing options for MCQ in section ' . $sectionNumber . ' question ' . ($row['question_number'] ?? '');
            continue;
        }

        $correct = $normalizeCorrect($questionType, $row['correct_answer'] ?? '', $options);
        if ($correct === '') {
            $errors[] = 'Missing/invalid correct answer in section ' . $sectionNumber . ' question ' . ($row['question_number'] ?? '');
            continue;
        }

        if ($dryRun) {
            $imported += 1;
            continue;
        }

        $sectionId = $sectionMap[$sectionNumber];
        MockSection::find($sectionId)
            ->questions()
            ->create([
                'question_type' => $questionType,
                'question_text' => $questionText,
                'options_json' => $questionType === 'mcq' ? $options : null,
                'correct_answer' => $correct,
                'order_index' => max(1, $orderIndex),
            ]);

        $imported += 1;
    }

    if ($dryRun) {
        return;
    }

    $total = 0;
    foreach ($sectionMap as $sectionNumber => $sectionId) {
        $section = MockSection::find($sectionId);
        if (!$section) {
            continue;
        }
        $count = $section->questions()->count();
        $section->update(['question_count' => $count]);
        $total += $count;
    }

    $test->update([
        'total_questions' => $total,
        'time_limit' => 3600,
        'is_active' => true,
    ]);
};

$runImport();

if (!empty($errors)) {
    echo "Import completed with warnings:\n";
    foreach ($errors as $error) {
        echo "- {$error}\n";
    }
}

echo "Imported questions: {$imported}\n";
if ($dryRun) {
    echo "(dry-run: no database changes)\n";
}
