<?php

namespace App\Services;

/**
 * Normalizes CSV / pasted fitment data into HTML the storefront YMM filter expects:
 * rows with exactly three cells: Year(s), Make, Model (years comma-separated in first cell).
 *
 * @see \App\Http\Controllers\Front\CatalogController fitment filter
 */
class FitmentTableNormalizer
{
    /**
     * @return string HTML fragment (table or enhanced existing HTML), or empty if input empty
     */
    public function toSearchableHtml(string $raw): string
    {
        $trim = trim($raw);
        if ($trim === '') {
            return '';
        }

        if (preg_match('/<\s*table\b/i', $trim)) {
            return $this->injectPaFitmentTableClass($trim);
        }

        $lines = preg_split('/\R/u', $trim);
        $rows = [];
        foreach ($lines as $line) {
            $parsed = $this->parseStructuredLine($line);
            if ($parsed !== null) {
                $rows[] = $parsed;
            }
        }

        if ($rows !== []) {
            return $this->buildTable($rows);
        }

        return '<div class="fitment-import-plain">'.e($trim).'</div>';
    }

    public function shouldAddHeading(string $originalRaw): bool
    {
        $trim = ltrim($originalRaw);

        return $trim !== '' && ! preg_match('/<\s*table\b/i', $trim);
    }

    /**
     * @return array{0:string,1:string,2:string}|null
     */
    private function parseStructuredLine(string $line): ?array
    {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            return null;
        }

        if (str_contains($line, "\t")) {
            $parts = preg_split("/\t+/", $line);
            if (count($parts) === 3) {
                return [trim($parts[0]), trim($parts[1]), trim($parts[2])];
            }
        }

        if (substr_count($line, '|') >= 2) {
            $parts = explode('|', $line, 3);
            if (count($parts) === 3) {
                return [trim($parts[0]), trim($parts[1]), trim($parts[2])];
            }
        }

        if (str_contains($line, ',')) {
            $parts = str_getcsv($line);
            if (count($parts) === 3) {
                return [trim($parts[0]), trim($parts[1]), trim($parts[2])];
            }
        }

        return null;
    }

    /**
     * @param  list<array{0:string,1:string,2:string}>  $rows
     */
    private function buildTable(array $rows): string
    {
        $html = '<table class="pa-fitment-table"><thead><tr>'
            .'<th>'.e(__('Year')).'</th>'
            .'<th>'.e(__('Make')).'</th>'
            .'<th>'.e(__('Model')).'</th>'
            .'</tr></thead><tbody>';

        foreach ($rows as [$y, $make, $model]) {
            $html .= '<tr>'
                .'<td>'.e($y).'</td>'
                .'<td>'.e($make).'</td>'
                .'<td>'.e($model).'</td>'
                .'</tr>';
        }

        $html .= '</tbody></table>';

        return $html;
    }

    private function injectPaFitmentTableClass(string $html): string
    {
        if (stripos($html, 'pa-fitment-table') !== false) {
            return $html;
        }

        return (string) preg_replace_callback(
            '/<table\s+([^>]*)>/i',
            function (array $m): string {
                $attrs = trim($m[1]);
                if ($attrs === '') {
                    return '<table class="pa-fitment-table">';
                }
                if (preg_match('/\bclass\s*=\s*"([^"]*)"/i', $attrs, $cm)) {
                    $classes = trim($cm[1]).' pa-fitment-table';

                    return '<table '.preg_replace(
                        '/\bclass\s*=\s*"[^"]*"/i',
                        'class="'.htmlspecialchars($classes, ENT_QUOTES, 'UTF-8').'"',
                        $attrs,
                        1
                    ).'>';
                }
                if (preg_match("/\bclass\s*=\s*'([^']*)'/i", $attrs, $cm)) {
                    $classes = trim($cm[1]).' pa-fitment-table';

                    return '<table '.preg_replace(
                        "/\bclass\s*=\s*'[^']*'/i",
                        "class='".htmlspecialchars($classes, ENT_QUOTES, 'UTF-8')."'",
                        $attrs,
                        1
                    ).'>';
                }

                return '<table class="pa-fitment-table" '.$attrs.'>';
            },
            $html,
            1
        );
    }
}
