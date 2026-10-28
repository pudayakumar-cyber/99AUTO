<?php

namespace App\Support;

final class DuplicateProductAudit
{
    private const IDENTIFIERS = ['product_part_number', 'prod_number', 'sku'];

    public function inspect(iterable $records): array
    {
        $items = [];
        $candidateGroups = [];

        foreach ($records as $record) {
            $item = $this->record($record);
            $id = (int) ($item['id'] ?? 0);

            if ($id < 1) {
                continue;
            }

            $items[$id] = $item;
            $brandKey = (string) ($item['brand_id'] ?? 'none');

            foreach (self::IDENTIFIERS as $field) {
                $value = $this->normalizeIdentifier($item[$field] ?? null);
                if ($value !== '') {
                    $candidateGroups['identifier|'.$brandKey.'|'.$field.'|'.$value][] = $id;
                }
            }

            $name = $this->normalizeName($item['name'] ?? null);
            if ($name !== '') {
                $candidateGroups['title|'.$brandKey.'|'.$name][] = $id;
            }
        }

        $groups = [];

        foreach ($candidateGroups as $candidateKey => $ids) {
            $ids = array_values(array_unique($ids));
            sort($ids);

            if (count($ids) < 2) {
                continue;
            }

            [$matchType, , $field] = array_pad(explode('|', $candidateKey, 4), 3, null);
            $signature = implode(',', $ids);

            if (! isset($groups[$signature])) {
                $groups[$signature] = [
                    'confidence' => $matchType === 'identifier' ? 'high' : 'review',
                    'matched_on' => [],
                    'items' => array_map(fn (int $id): array => $items[$id], $ids),
                ];
            }

            $groups[$signature]['matched_on'][] = $matchType === 'identifier' ? $field : 'name';
            if ($matchType === 'identifier') {
                $groups[$signature]['confidence'] = 'high';
            }
        }

        $groups = array_values(array_map(function (array $group): array {
            $group['matched_on'] = array_values(array_unique($group['matched_on']));

            return $group;
        }, $groups));

        usort($groups, function (array $left, array $right): int {
            $confidence = ['high' => 0, 'review' => 1];

            return [$confidence[$left['confidence']], -count($left['items']), $left['items'][0]['id']]
                <=> [$confidence[$right['confidence']], -count($right['items']), $right['items'][0]['id']];
        });

        return $groups;
    }

    private function record(mixed $record): array
    {
        if (is_array($record)) {
            return $record;
        }

        if (is_object($record) && method_exists($record, 'toArray')) {
            return $record->toArray();
        }

        return (array) $record;
    }

    private function normalizeIdentifier(mixed $value): string
    {
        return preg_replace('/[^A-Z0-9]+/', '', mb_strtoupper(trim((string) $value))) ?? '';
    }

    private function normalizeName(mixed $value): string
    {
        $value = html_entity_decode(strip_tags((string) $value), ENT_QUOTES, 'UTF-8');
        $value = mb_strtolower(trim($value));

        return preg_replace('/\s+/u', ' ', $value) ?? '';
    }
}
