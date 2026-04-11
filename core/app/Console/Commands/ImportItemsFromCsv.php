<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImportItemsFromCsv extends Command
{
    protected $signature = 'import:items {csv_path}';
    protected $description = 'Import items from CSV and download images';

    public function handle()
    {
        $path = $this->argument('csv_path');

        if (!file_exists($path)) {
            $this->error("CSV file not found");
            return;
        }

        $file = fopen($path, 'r');
        $header = fgetcsv($file);

        while (($row = fgetcsv($file)) !== false) {
            $data = array_combine($header, $row);

            DB::transaction(function () use ($data) {

                // 🔹 BRAND HANDLING
                $brandName = trim($data['Brand'] ?? '');

                $brandId = null;

                if ($brandName !== '') {
                    $brand = DB::table('brands')->where('name', $brandName)->first();

                    if ($brand) {
                        $brandId = $brand->id;
                    } else {
                        $brandId = DB::table('brands')->insertGetId([
                            'name'       => $brandName,
                            'slug'       => Str::slug($brandName),
                            'status'     => 1,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }

                // ⛔ Skip if item already exists
                if (DB::table('items')->where('name', $data['Title'])->exists()) {
                    return;
                }

                // 🔹 CATEGORY HANDLING
                $categoryName = trim('Brake Pads');
                $category = DB::table('categories')->where('name', $categoryName)->first();

                if (!$category) {
                    $categoryId = DB::table('categories')->insertGetId([
                        'name' => $categoryName,
                        'slug' => Str::slug($categoryName),
                        'status' => 1,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                } else {
                    $categoryId = $category->id;
                }

                // 🔹 IMAGE HANDLING
                $images = preg_split('/[\|\n,]+/', $data['Images']);
                $images = array_values(array_filter(array_map('trim', $images)));

                $photoPath = null;

                if (!empty($images[0])) {
                    $photoPath = $this->downloadImage($images[0]);
                }

                // 🔹 DETAILS MERGE
                $details = ($data['Product Overview'] ?? '')
                         . ($data['Specifications'] ?? '')
                         . ($data['Fitting Vehicles'] ?? '');

                // 🔹 INSERT ITEM
                $data['ADJUSTED PRICE'] = 0 + 8;
                $itemId = DB::table('items')->insertGetId([
                    'category_id'   => $categoryId,
                    'brand_id'      => $brandId, // ✅ NEW
                    'name'          => $data['Title'],
                    'prod_number'   => $data['PROD NUMBER'],
                    'moog'          => $data['MOOG'] ?? null,
                    'slug'          => Str::slug($data['Title']),
                    'tags'          => 'brakes, rotors, car parts, suspension',
                    'sort_details'  => $data['Product Highlights'] ?? '',
                    'details'       => $details,
                    'photo'         => $photoPath,
                    'thumbnail'     => $photoPath,
                    'status'        => 1,
                    'file_type'     => 'file',
                    'item_type'     => 'normal',
                    'is_type'       => 'normal',
                    'previous_price' => is_numeric($data['ADJUSTED PRICE']) ? (float)$data['ADJUSTED PRICE'] : null,
                    'discount_price' => is_numeric($data['ADJUSTED PRICE']) ? (float)$data['ADJUSTED PRICE'] : null,
                    'stock'         => 100,
                    'created_at'    => now(),
                    'updated_at'    => now(),
                ]);


                // 🔹 GALLERIES
                foreach (array_slice($images, 1) as $img) {
                    $galleryPath = $this->downloadImage($img);

                    if ($galleryPath) {
                        DB::table('galleries')->insert([
                            'item_id' => $itemId,
                            'photo' => $galleryPath,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }
            });
        }

        fclose($file);
        $this->info('✅ Import completed successfully');
    }

   private function downloadImage($url)
    {
        try {
            $url = trim($url);
            if (!$url) {
                return null;
            }

            $response = Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
                'Accept' => 'image/*',
            ])
            ->withoutVerifying()
            ->timeout(60)
            ->get($url);

            if (!$response->successful()) {
                return null;
            }

            $extension = pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION);
            $extension = $extension ?: 'jpg';

            $fileName = uniqid() . '.' . $extension;

            // ⬇️ stored physically here
            Storage::disk('public')->put('images/' . $fileName, $response->body());

            // ⬇️ DB gets ONLY filename
            return $fileName;

        } catch (\Exception $e) {
            return null;
        }
    }

}
