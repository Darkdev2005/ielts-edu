<?php
require __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

$path = __DIR__ . '/../storage/app/imports/IELTS_EDU_Reading_Mock_Full_REAL_plain.xlsx';
if (!file_exists($path)) {
    fwrite(STDERR, "File not found: {$path}\n");
    exit(1);
}

$spreadsheet = IOFactory::load($path);
$sheetNames = $spreadsheet->getSheetNames();
foreach ($sheetNames as $index => $name) {
    echo "Sheet {$index}: {$name}\n";
    $sheet = $spreadsheet->getSheet($index);
    $highestRow = min(10, $sheet->getHighestRow());
    $highestCol = $sheet->getHighestColumn();
    $highestColIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestCol);
    for ($row = 1; $row <= $highestRow; $row++) {
        $values = [];
        for ($col = 1; $col <= $highestColIndex; $col++) {
            $values[] = (string) $sheet->getCellByColumnAndRow($col, $row)->getValue();
        }
        $values = array_map(fn($v) => trim(preg_replace('/\s+/', ' ', $v)), $values);
        echo '  R'.$row.': '.json_encode($values, JSON_UNESCAPED_UNICODE)."\n";
    }
    echo "\n";
}
