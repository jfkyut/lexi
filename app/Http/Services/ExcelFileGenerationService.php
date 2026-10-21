<?php

namespace App\Http\Services;

use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;

class ExcelFileGenerationService
{
    /**
     * @param  array<int, string>  $headers
     * @param  array<int, array<int, string|int|float|bool|null>>  $rows
     */
    public function generate(string $title, array $headers, array $rows): string
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getProperties()->setTitle($title);
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Data');
        $sheet->setCellValueExplicit('A1', $title, DataType::TYPE_STRING);

        foreach ($headers as $columnIndex => $header) {
            $sheet->setCellValueExplicit(
                [$columnIndex + 1, 2],
                $header,
                DataType::TYPE_STRING,
            );
        }

        foreach ($rows as $rowIndex => $row) {
            foreach ($row as $columnIndex => $value) {
                $cellValue = match (true) {
                    is_bool($value) => $value ? 'TRUE' : 'FALSE',
                    $value === null => '',
                    default => (string) $value,
                };

                $sheet->setCellValueExplicit(
                    [$columnIndex + 1, $rowIndex + 3],
                    $cellValue,
                    DataType::TYPE_STRING,
                );
            }
        }

        $lastColumn = $sheet->getHighestColumn();
        $sheet->mergeCells('A1:'.$lastColumn.'1');
        $sheet->getStyle('A1:'.$lastColumn.'2')->getFont()->setBold(true);
        $sheet->freezePane('A3');
        $sheet->setAutoFilter('A2:'.$lastColumn.max(2, count($rows) + 2));

        foreach (range(1, count($headers)) as $columnIndex) {
            $sheet->getColumnDimensionByColumn($columnIndex)->setAutoSize(true);
        }

        $temporaryPath = tempnam(sys_get_temp_dir(), 'lexi-xlsx-');

        if (! is_string($temporaryPath)) {
            throw new RuntimeException('A temporary Excel file could not be created.');
        }

        try {
            (new Xlsx($spreadsheet))->save($temporaryPath);
            $contents = file_get_contents($temporaryPath);

            if (! is_string($contents)) {
                throw new RuntimeException('The generated Excel file could not be read.');
            }

            return $contents;
        } finally {
            unlink($temporaryPath);
            $spreadsheet->disconnectWorksheets();
        }
    }
}
