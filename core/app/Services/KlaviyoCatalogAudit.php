<?php

namespace App\Services;

use JsonException;

/** Read-only validation of every emitted record; never sends the protected feed URL. */
class KlaviyoCatalogAudit
{
    public function inspect(iterable $records): array
    {
        $count = 0;
        $bytes = 2; // Top-level array brackets.
        $invalid = 0;
        $outOfStock = 0;
        $seen = [];
        $examples = [];

        foreach ($records as $record) {
            $count++;
            $errors = [];
            foreach (['id', 'title', 'link', 'image_link', 'description'] as $field) {
                if (! isset($record[$field]) || ! is_scalar($record[$field]) || trim((string) $record[$field]) === '') {
                    $errors[] = 'missing '.$field;
                }
            }
            foreach (['link', 'image_link'] as $field) {
                $value = $record[$field] ?? null;
                if (! is_string($value) || ! filter_var($value, FILTER_VALIDATE_URL)
                    || strtolower((string) parse_url($value, PHP_URL_SCHEME)) !== 'https') {
                    $errors[] = $field.' must be an HTTPS URL';
                }
            }
            $id = is_scalar($record['id'] ?? null) ? (string) $record['id'] : '';
            if (isset($seen[$id])) {
                $errors[] = 'duplicate id';
            }
            $seen[$id] = true;
            if (! isset($record['price']) || ! is_numeric($record['price']) || $record['price'] < 0) {
                $errors[] = 'invalid price';
            }
            if (($record['inventory_policy'] ?? null) == 1 && ($record['inventory_quantity'] ?? 0) <= 0) {
                $outOfStock++;
            }
            try {
                $bytes += strlen(json_encode($record, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
                $bytes += $count > 1 ? 1 : 0;
            } catch (JsonException) {
                $errors[] = 'JSON encoding failed';
            }
            if ($errors !== []) {
                $invalid++;
                if (count($examples) < 10) {
                    $examples[] = 'Record '.$count.': '.implode(', ', $errors);
                }
            }
        }

        return [
            'count' => $count,
            'bytes' => $bytes,
            'invalid' => $invalid,
            'excluded_from_recommendations' => $outOfStock,
            'examples' => $examples,
            'valid' => $count > 0 && $invalid === 0 && $bytes <= 100000000,
        ];
    }
}
