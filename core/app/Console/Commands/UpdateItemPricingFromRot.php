<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class UpdateItemPricingFromRot extends Command
{
    protected $signature = 'rot:update-pricing {csv_path}';
    protected $description = 'Update item pricing from ROT sheet using PROD NUMBER with normalized matching';

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

        // Clear old not-found log
        file_put_contents(storage_path('logs/rot_not_found.log'), '');

        while (($row = fgetcsv($file)) !== false) {

            // Skip malformed rows
            if (count($header) !== count($row)) {
                $skipped++;
                continue;
            }

            $data = array_combine($header, $row);

            $prodNumber    = trim($data['PROD NUMBER'] ?? '');
            $adjustedPrice = $data['ADJUSTED PRICE'] ?? null;

            // Basic validation
            if ($prodNumber === '' || !is_numeric($adjustedPrice)) {
                $skipped++;
                continue;
            }

            // Normalize SKU (CBS-1553 → CBS1553)
            // $normalizedProd = strtoupper(
            //     preg_replace('/[^A-Za-z0-9]/', '', $prodNumber)
            // );
            $normalizedProd = $prodNumber;

            // Apply +12 markup
            $finalPrice = (float) $adjustedPrice + 12;

            // 🔍 Robust normalized search
           $items = DB::table('items')
                ->where('name', 'LIKE', '%' . $normalizedProd . '%')
                ->orWhere('slug', 'LIKE', '%' . $normalizedProd . '%')
                ->get();



            if ($items->isEmpty()) {
                $notFound++;

                // Log missing SKU
                file_put_contents(
                    storage_path('logs/rot_not_found.log'),
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

        $this->info('✅ ROT pricing update completed successfully');
        $this->line("✔ Updated items: {$updated}");
        $this->line("❌ Items not found: {$notFound}");
        $this->line("⏭ Skipped rows: {$skipped}");
        $this->line("📄 Missing SKUs logged in storage/logs/rot_not_found.log");
    }
}
