<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class UpdateItemPricingFromBaw extends Command
{
    protected $signature = 'baw:update-pricing {csv_path}';
    protected $description = 'Update item pricing from BAW sheet using PROD NUMBER with normalized matching';

    public function handle()
    {
        $path = $this->argument('csv_path');

        if (!file_exists($path)) {
            $this->error('❌ CSV file not found.');
            return;
        }

        $file = fopen($path, 'r');
        $header = fgetcsv($file);

        if (!$header) {
            $this->error('❌ Invalid CSV header.');
            return;
        }

        $updated  = 0;
        $notFound = 0;
        $skipped  = 0;

        // Clear old BAW log
        file_put_contents(storage_path('logs/baw_not_found.log'), '');

        while (($row = fgetcsv($file)) !== false) {

            // Skip malformed rows
            if (count($header) !== count($row)) {
                $skipped++;
                continue;
            }

            $data = array_combine($header, $row);

            $prodNumber    = trim($data['PROD NUMBER'] ?? '');
            $adjustedPrice = $data['ADJUSTED PRICE'] ?? null;

            // Validation
            if ($prodNumber === '' || !is_numeric($adjustedPrice)) {
                $skipped++;
                continue;
            }

            // Normalize SKU (CB-55074 → CB55074)
            // $normalizedProd = strtoupper(
            //     preg_replace('/[^A-Za-z0-9]/', '', $prodNumber)
            // );
            $normalizedProd = $prodNumber;

            // +12 markup (change later if BAW differs)
            $finalPrice = (float) $adjustedPrice + 12;

            // 🔍 Robust normalized match
            $items = DB::table('items')
                ->where('name', 'LIKE', '%' . $normalizedProd . '%')
                ->orWhere('slug', 'LIKE', '%' . $normalizedProd . '%')
                ->get();



            if ($items->isEmpty()) {
                $notFound++;

                // Log missing BAW SKU
                file_put_contents(
                    storage_path('logs/baw_not_found.log'),
                    $prodNumber . PHP_EOL,
                    FILE_APPEND
                );

                continue;
            }

            // Update all matched items
            foreach ($items as $item) {
                DB::table('items')
                    ->where('id', $item->id)
                    ->update([
                        'previous_price' => $finalPrice,
                        'discount_price' => $finalPrice,
                        'updated_at'     => now(),
                    ]);

                $updated++;
            }
        }

        fclose($file);

        $this->info('✅ BAW pricing update completed successfully');
        $this->line("✔ Updated items: {$updated}");
        $this->line("❌ Items not found: {$notFound}");
        $this->line("⏭ Skipped rows: {$skipped}");
        $this->line("📄 Missing BAW SKUs logged in storage/logs/baw_not_found.log");
    }
}
