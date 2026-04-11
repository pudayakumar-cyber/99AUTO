<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class MakesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
         $years = DB::table('years')->get();

        foreach ($years as $year) {

            $response = Http::timeout(30)->get(
                'https://partsavatar.ca/api/get-makes-by-year-v3',
                [
                    'year'   => $year->year,
                    'locale' => 'en',
                ]
            );

            if (!$response->successful()) {
                continue;
            }

            $makes = $response->json();

            // If API returns something like: ["Toyota","Honda"]
            foreach ($makes as $make) {
                DB::table('makes')->insert([
                    'year_id'    => $year->id,
                    'make'       => $make,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // Be polite to API
            usleep(200000); // 0.2 sec
        }
    }
}
