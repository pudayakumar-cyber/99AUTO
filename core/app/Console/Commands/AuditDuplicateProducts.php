<?php

namespace App\Console\Commands;

use App\Models\Item;
use App\Support\DuplicateProductAudit;
use Illuminate\Console\Command;

class AuditDuplicateProducts extends Command
{
    protected $signature = 'seo:audit-duplicate-products
        {--limit=50 : Maximum candidate groups to display}
        {--json : Output machine-readable JSON}
        {--include-inactive : Include products that are not currently live}';

    protected $description = 'Report likely duplicate products without changing product data.';

    public function handle(DuplicateProductAudit $audit): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $query = Item::query()->select([
            'id', 'brand_id', 'name', 'slug', 'sku', 'prod_number',
            'product_part_number', 'category_id', 'subcategory_id',
            'childcategory_id', 'discount_price', 'stock', 'status',
        ]);

        if (! $this->option('include-inactive')) {
            $query->where('status', 1);
        }

        $groups = $audit->inspect($query->orderBy('id')->cursor());
        $visibleGroups = array_slice($groups, 0, $limit);

        if ($this->option('json')) {
            $this->line(json_encode([
                'candidate_groups' => count($groups),
                'displayed_groups' => count($visibleGroups),
                'groups' => $visibleGroups,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        $rows = array_map(function (array $group): array {
            $items = $group['items'];

            return [
                $group['confidence'],
                implode(', ', $group['matched_on']),
                implode(', ', array_column($items, 'id')),
                (string) ($items[0]['brand_id'] ?? ''),
                implode(' | ', array_values(array_unique(array_filter(array_column($items, 'name'))))),
                implode(', ', array_values(array_unique(array_filter(array_merge(
                    array_column($items, 'product_part_number'),
                    array_column($items, 'prod_number'),
                    array_column($items, 'sku')
                ), fn ($value) => trim((string) $value) !== '')))),
                implode(', ', array_column($items, 'stock')),
            ];
        }, $visibleGroups);

        $this->table(
            ['Confidence', 'Matched on', 'Item IDs', 'Brand ID', 'Names', 'Identifiers', 'Stock'],
            $rows
        );
        $this->info('Candidate groups: '.count($groups).'; displayed: '.count($visibleGroups).'.');
        $this->warn('Review candidates manually before any canonical, redirect, merge, or deletion decision.');

        return self::SUCCESS;
    }
}
