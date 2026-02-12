<?php
require __DIR__ . '/../vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
$path = __DIR__ . '/../storage/app/imports/IELTS_EDU_Reading_Mock_Full_REAL_plain.xlsx';
$sheet = IOFactory::load($path)->getSheetByName('Questions');
$highestColIndex = Coordinate::columnIndexFromString($sheet->getHighestColumn());
$headerRow = $sheet->rangeToArray('A1:'.Coordinate::stringFromColumnIndex($highestColIndex).'1', null, true, false)[0];
$map = [];
foreach ($headerRow as $i => $h) { $map[strtolower(trim((string)$h))] = $i; }
$targets = [5,6,7,8,6,7,8,9];
for ($row=2; $row<= $sheet->getHighestRow(); $row++) {
  $rowVals = $sheet->rangeToArray('A'.$row.':'.Coordinate::stringFromColumnIndex($highestColIndex).$row, null, true, false)[0];
  $sec = (int) ($rowVals[$map['section_number']] ?? 0);
  $qnum = (int) ($rowVals[$map['question_number']] ?? 0);
  if (($sec === 1 && in_array($qnum, [5,6,7,8], true)) || ($sec === 2 && in_array($qnum, [6,7,8,9], true))) {
    $type = $rowVals[$map['question_type']] ?? '';
    $correct = $rowVals[$map['correct_answer']] ?? '';
    echo "section {$sec} q{$qnum} type={$type} correct={$correct}\n";
  }
}
