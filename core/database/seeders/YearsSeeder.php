<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class YearsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
     public function run(): void 
     {
        $data = [];

        for ($year = 1962; $year <= 2025; $year++) {

            // Example: 2010–2019
            $start = floor($year / 10) * 10;
            $end   = $start + 9;

            $data[] = [
                'year'       => $year,
                'year_group' => "{$start}-{$end}",
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        DB::table('years')->insert($data);
     }
}
