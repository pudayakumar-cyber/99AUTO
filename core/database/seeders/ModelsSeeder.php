<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class ModelsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get the last processed year_id (handle NULL safely)
        $lastProcessedYearId = DB::table('models')->max('year_id') ?? 0;

        // Get makes for years greater than last processed year_id
        $makes = DB::table('makes')
            ->join('years', 'years.id', '=', 'makes.year_id')
            ->select(
                'makes.id as make_id',
                'makes.make',
                'years.id as year_id',
                'years.year'
            )
            ->where('years.id', '>', $lastProcessedYearId)
            ->get();

        // Exit if nothing to process
        if ($makes->isEmpty()) {
            echo "No new makes to process.\n";
            return;
        }

        foreach ($makes as $row) {

            // Fetch models from API
            $response = Http::timeout(60)->get(
                'https://partsavatar.ca/api/get-models-by-year-make-v3',
                [
                    'year'   => $row->year,
                    'make'   => $row->make,
                    'locale' => 'en',
                ]
            );

            if (!$response->successful()) {
                echo "Failed to fetch data for {$row->year} {$row->make}\n";
                continue;
            }

            $models = $response->json();

            foreach ($models as $item) {
                // Prevent duplicates on re-runs
                DB::table('models')->updateOrInsert(
                    [
                        'year_id' => $row->year_id,
                        'make_id' => $row->make_id,
                        'model'   => $item['model'] ?? null,
                    ],
                    [
                        'bodyType'   => $item['bodyType'] ?? null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }

            // Prevent API rate-limit
            usleep(500000); // 0.5 sec
        }

        echo "Seeding completed.\n";
    }
}
