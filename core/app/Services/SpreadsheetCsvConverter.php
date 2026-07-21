<?php

namespace App\Services;

use RuntimeException;
use ZipArchive;

class SpreadsheetCsvConverter
{
    public function convertXlsxToCsv(string $xlsxPath, string $csvPath): void
    {
        if (! is_file($xlsxPath)) {
            throw new RuntimeException('Spreadsheet file not found.');
        }

        $zip = new ZipArchive();
        if ($zip->open($xlsxPath) !== true) {
            throw new RuntimeException('Unable to open XLSX file.');
        }

        try {
            $sharedStrings = $this->readSharedStrings($zip);
            $sheetPath = $this->firstWorksheetPath($zip);
            $sheetXml = $zip->getFromName($sheetPath);
            if ($sheetXml === false) {
                throw new RuntimeException('Unable to read first worksheet from XLSX file.');
            }

            $sheet = simplexml_load_string($sheetXml);
            if ($sheet === false) {
                throw new RuntimeException('Invalid worksheet XML in XLSX file.');
            }

            $handle = fopen($csvPath, 'w');
            if ($handle === false) {
                throw new RuntimeException('Unable to create converted CSV file.');
            }

            try {
                foreach ($sheet->sheetData->row as $row) {
                    $values = [];
                    $lastColumn = 0;

                    foreach ($row->c as $cell) {
                        $columnIndex = $this->cellColumnIndex((string) $cell['r']);
                        while ($lastColumn + 1 < $columnIndex) {
                            $values[] = '';
                            $lastColumn++;
                        }

                        $values[] = $this->cellValue($cell, $sharedStrings);
                        $lastColumn = $columnIndex;
                    }

                    fputcsv($handle, $values);
                }
            } finally {
                fclose($handle);
            }
        } finally {
            $zip->close();
        }
    }

    /**
     * @return list<string>
     */
    private function readSharedStrings(ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');
        if ($xml === false) {
            return [];
        }

        $sharedXml = simplexml_load_string($xml);
        if ($sharedXml === false) {
            return [];
        }

        $strings = [];
        foreach ($sharedXml->si as $si) {
            if (isset($si->t)) {
                $strings[] = (string) $si->t;
                continue;
            }

            $parts = [];
            foreach ($si->r as $run) {
                $parts[] = (string) $run->t;
            }
            $strings[] = implode('', $parts);
        }

        return $strings;
    }

    private function firstWorksheetPath(ZipArchive $zip): string
    {
        $workbook = $zip->getFromName('xl/workbook.xml');
        $rels = $zip->getFromName('xl/_rels/workbook.xml.rels');

        if ($workbook !== false && $rels !== false) {
            $workbookXml = simplexml_load_string($workbook);
            $relsXml = simplexml_load_string($rels);

            if ($workbookXml !== false && $relsXml !== false && isset($workbookXml->sheets->sheet[0])) {
                $attributes = $workbookXml->sheets->sheet[0]->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships');
                $relationshipId = (string) ($attributes['id'] ?? '');

                if ($relationshipId !== '') {
                    foreach ($relsXml->Relationship as $relationship) {
                        if ((string) $relationship['Id'] !== $relationshipId) {
                            continue;
                        }

                        $target = ltrim((string) $relationship['Target'], '/');
                        if (! str_starts_with($target, 'xl/')) {
                            $target = 'xl/'.$target;
                        }

                        return $target;
                    }
                }
            }
        }

        if ($zip->locateName('xl/worksheets/sheet1.xml') !== false) {
            return 'xl/worksheets/sheet1.xml';
        }

        throw new RuntimeException('No worksheet found in XLSX file.');
    }

    /**
     * @param  array<int,string>  $sharedStrings
     */
    private function cellValue(\SimpleXMLElement $cell, array $sharedStrings): string
    {
        $type = (string) ($cell['t'] ?? '');

        if ($type === 's') {
            $index = (int) ($cell->v ?? -1);

            return (string) ($sharedStrings[$index] ?? '');
        }

        if ($type === 'inlineStr') {
            if (isset($cell->is->t)) {
                return (string) $cell->is->t;
            }

            $parts = [];
            foreach ($cell->is->r as $run) {
                $parts[] = (string) $run->t;
            }

            return implode('', $parts);
        }

        if ($type === 'b') {
            return ((string) ($cell->v ?? '0')) === '1' ? '1' : '0';
        }

        return (string) ($cell->v ?? '');
    }

    private function cellColumnIndex(string $cellReference): int
    {
        if (! preg_match('/^([A-Z]+)/i', $cellReference, $matches)) {
            return 1;
        }

        $letters = strtoupper($matches[1]);
        $index = 0;
        for ($i = 0, $length = strlen($letters); $i < $length; $i++) {
            $index = ($index * 26) + (ord($letters[$i]) - 64);
        }

        return max(1, $index);
    }
}
