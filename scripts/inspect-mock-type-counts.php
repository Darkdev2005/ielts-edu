<?php
require __DIR__ . '/../vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
$path = __DIR__ . '/../storage/app/imports/IELTS_EDU_Reading_Mock_Full_REAL_plain.xlsx';
$sheet = IOFactory::load($path)->getSheetByName('Questions');
$highestRow = $sheet->getHighestRow();
$highestCol = $sheet->getHighestColumn();
$highestColIndex = Coordinate::columnIndexFromString($highestCol);
$headerRow = $sheet->rangeToArray('A1:'.Coordinate::stringFromColumnIndex($highestColIndex).'1', null, true, false)[0];
$map = [];
foreach ($headerRow as $i => $h) { $map[strtolower(trim((string)$h))] = $i; }
$counts = [];
for ($row=2; $row <= $highestRow; $row++) {
    $rowVals = $sheet->rangeToArray('A'.$row.':'.Coordinate::stringFromColumnIndex($highestColIndex).$row, null, true, false)[0];
    $type = strtolower(trim((string)($rowVals[$map['question_type']] ?? '')));
    if ($type === '') continue;
    $counts[$type] = ($counts[$type] ?? 0) + 1;
}
ksort($counts);
foreach ($counts as $type => $count) { echo $type.": ".$count."\n"; }
