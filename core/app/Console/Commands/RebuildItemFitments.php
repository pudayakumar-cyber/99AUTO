<?php

namespace App\Console\Commands;

use App\Models\Item;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RebuildItemFitments extends Command
{
    protected $signature = 'items:rebuild-fitments {--item-id= : Rebuild one item only} {--fresh : Delete existing fitments before rebuilding}';

    protected $description = 'Rebuild indexed item fitments from the product details HTML.';

    public function handle(): int
    {
        $itemId = $this->option('item-id');

        if ($this->option('fresh')) {
            $deleteQuery = DB::table('item_fitments');

            if ($itemId) {
                $deleteQuery->where('item_id', (int) $itemId);
            }

            $deleteQuery->delete();
        }

        $query = Item::query()
            ->select('id', 'details')
            ->whereNotNull('details')
            ->where('details', 'like', '%<table%');

        if ($itemId) {
            $query->where('id', (int) $itemId);
        }

        $processed = 0;
        $inserted = 0;
        $now = now();

        $query->orderBy('id')->chunkById(200, function ($items) use (&$processed, &$inserted, $now) {
            foreach ($items as $item) {
                $processed++;
                $rows = $this->fitmentRowsFromDetails((string) $item->details);
                $payload = [];

                foreach ($rows as [$yearsCell, $makeCell, $modelCell]) {
                    $make = $this->canonicalFitmentToken($makeCell);
                    $model = $this->canonicalFitmentToken($modelCell);

                    if ($make === '' || $model === '') {
                        continue;
                    }

                    foreach ($this->expandFitmentYears($yearsCell) as $year) {
                        if (! preg_match('/^\d{4}$/', $year)) {
                            continue;
                        }

                        $payload[$item->id . '|' . $year . '|' . $make . '|' . $model] = [
                            'item_id' => $item->id,
                            'year' => $year,
                            'make' => $make,
                            'model' => $model,
                            'raw_make' => mb_substr($makeCell, 0, 255),
                            'raw_model' => mb_substr($modelCell, 0, 255),
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }
                }

                if ($payload === []) {
                    continue;
                }

                foreach (array_chunk(array_values($payload), 500) as $chunk) {
                    DB::table('item_fitments')->upsert(
                        $chunk,
                        ['item_id', 'year', 'make', 'model'],
                        ['raw_make', 'raw_model', 'updated_at']
                    );

                    $inserted += count($chunk);
                }
            }
        });

        Cache::forget('item_fitments_table_ready_v1');
        $this->info("Processed {$processed} items and indexed {$inserted} fitment rows.");

        return self::SUCCESS;
    }

    private function fitmentRowsFromDetails(string $details): array
    {
        $tableHtml = null;

        if (preg_match('/<table[^>]*class="[^"]*\bpa-fitment-table\b[^"]*"[^>]*>[\s\S]*?<\/table>/i', $details, $matches)) {
            $tableHtml = $matches[0];
        } elseif (preg_match('/<div[^>]*id=["\']collapsePaFitting["\'][^>]*>[\s\S]*?(<table[^>]*>[\s\S]*?<\/table>)/i', $details, $matches)) {
            $tableHtml = $matches[1];
        }

        if ($tableHtml === null) {
            return [];
        }

        preg_match_all('/<tr[^>]*>(.*?)<\/tr>/si', $tableHtml, $rowMatches);

        $rows = [];
        foreach ($rowMatches[1] as $rowHtml) {
            preg_match_all('/<t[dh][^>]*>(.*?)<\/t[dh]>/si', $rowHtml, $columns);

            if (count($columns[1]) < 3) {
                continue;
            }

            $cells = array_map(
                fn ($value) => trim(html_entity_decode(strip_tags((string) $value))),
                array_slice($columns[1], 0, 3)
            );

            $firstCell = $this->canonicalFitmentToken($cells[0] ?? '');
            if (in_array($firstCell, ['year', 'years', 'fitment'], true)) {
                continue;
            }

            if ($cells[0] === '' || $cells[1] === '' || $cells[2] === '') {
                continue;
            }

            $rows[] = $cells;
        }

        return $rows;
    }

    private function expandFitmentYears(string $yearsCell): array
    {
        $years = [];

        foreach (preg_split('/\s*,\s*/', trim($yearsCell)) ?: [] as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }

            if (preg_match('/^(\d{4})\s*-\s*(\d{4})$/', $part, $matches)) {
                $start = (int) $matches[1];
                $end = (int) $matches[2];

                if ($start <= $end) {
                    for ($year = $start; $year <= $end; $year++) {
                        $years[] = (string) $year;
                    }
                    continue;
                }
            }

            $years[] = Str::of($part)->replaceMatches('/\s+/u', ' ')->trim()->lower()->toString();
        }

        return array_values(array_unique($years));
    }

    private function canonicalFitmentToken(?string $value): string
    {
        return Str::of(html_entity_decode((string) $value))
            ->lower()
            ->replace('&', ' and ')
            ->replaceMatches('/[^a-z0-9]+/u', '')
            ->trim()
            ->toString();
    }
}
