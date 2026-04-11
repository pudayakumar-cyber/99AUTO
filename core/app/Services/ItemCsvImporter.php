<?php

namespace App\Services;

use App\Models\ProductUpload;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Header-based CSV import for bulk products (used by queued ProcessProductUploadJob).
 *
 * Column headers are matched case-insensitively after trim (UTF-8 BOM stripped).
 *
 * Core (common export / admin):
 * - Title OR Product Name (required if no SKU codes)
 * - PROD NUMBER OR Internal SKU OR Transit SKU (used for `sku` / `prod_number`; duplicate rows skipped by these codes)
 * - Product Part Number → `product_part_number` (also updated on duplicate SKU / transit matches when present)
 * - MOOG OR Interchange Part Number (stored in `moog` when MOOG empty)
 * - Brand, Product Category OR Category Group OR Category OR Suggested Categories
 *   (first segment before comma or &gt;)
 * - Images (pipe/comma/newline list) OR Image 1 URL … Image 14 URL (merged; duplicates removed)
 * - ADJUSTED PRICE OR Scraped Price
 *
 * Content (storefront):
 * - Description / Product Description — main HTML (`details`)
 * - Product Features — short highlights (`sort_details`)
 * - Fitment Table OR Year + Make + Model columns — YMM for catalog search (normalized table HTML)
 *
 * Extra columns (appended under “Additional information” in `details` when present):
 * - Box Length, Box Width, Box Height, Box Weight, Product Dimensions, Price Source
 *
 * Legacy aliases still supported:
 * - Product Highlights → Product Features if "Product Features" empty
 * - Product Overview, Specifications, Fitting Vehicles — merged into description if "Description" empty
 */
class ItemCsvImporter
{
    /** @var array<string,int> lowercase name => id */
    private array $brandByLower = [];

    /** @var array<string,int> lowercase name => id */
    private array $categoryByLower = [];

    private string $defaultCategoryName = 'Automotive Lubricants';

    /**
     * Process at most $chunkSize CSV data rows starting from byte offset.
     *
     * @return array{processed:int,imported:int,skipped:int,next_byte:int,has_more:bool}
     */
    public function importChunk(string $path, int $startByte, int $chunkSize): array
    {
        if (! is_file($path)) {
            throw new \InvalidArgumentException('CSV file not found: '.$path);
        }

        $this->warmCaches();

        $file = fopen($path, 'r');
        if ($file === false) {
            throw new \RuntimeException('Unable to open CSV');
        }

        $headerLine = fgetcsv($file);
        if ($headerLine === false) {
            fclose($file);
            throw new \RuntimeException('CSV is empty');
        }

        $header = $this->normalizeHeader($headerLine);

        $afterHeader = ftell($file);
        $seekTo = $startByte > 0 ? $startByte : $afterHeader;
        fseek($file, $seekTo);

        $processed = 0;
        $imported = 0;
        $skipped = 0;

        DB::beginTransaction();
        try {
            while ($processed < $chunkSize && ($row = fgetcsv($file)) !== false) {
                $processed++;
                [$didImport, $didSkip] = $this->processRecord($row, $header);
                $imported += $didImport ? 1 : 0;
                $skipped += $didSkip ? 1 : 0;
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        } finally {
            $nextByte = ftell($file);
            $hasMore = ! feof($file);
            fclose($file);
        }

        return [
            'processed' => $processed,
            'imported' => $imported,
            'skipped' => $skipped,
            'next_byte' => (int) $nextByte,
            'has_more' => $hasMore,
        ];
    }

    /**
     * @param  array<int,string|null>  $row
     * @param  array<int,string>  $header
     * @return array{0:bool,1:bool} [imported, skipped]
     */
    private function processRecord(array $row, array $header): array
    {
        if ($this->rowIsEmpty($row)) {
            return [false, false];
        }

        $row = array_pad($row, count($header), '');
        $combined = @array_combine($header, $row);
        if ($combined === false) {
            return [false, false];
        }

        $data = $this->normalizeRowKeys($combined);
        $title = trim($this->firstValue($data, ['title', 'product name']));
        if ($title === '') {
            return [false, true];
        }

        $existingItemId = $this->findExistingItemId($data, $title);
        if ($existingItemId !== null) {
            $this->fillExistingItemMediaIfMissing($existingItemId, $data);
            $this->syncProductPartNumberOnExistingItem($existingItemId, $data);
            // Same SKU/Transit SKU rows should extend fitment, not be dropped.
            if ($this->mergeFitmentIntoExistingItem($existingItemId, $data)) {
                return [false, false];
            }

            return [false, true];
        }

        $this->processRow($data, $title);

        return [true, false];
    }

    /**
     * @param  array<string,string>  $data
     */
    private function findExistingItemId(array $data, string $title): ?int
    {
        $internal = $this->firstValue($data, ['internal sku', 'prod number']);
        $transit = trim((string) ($data['transit sku'] ?? ''));
        $codes = array_values(array_unique(array_filter(
            [$internal, $transit],
            static fn (string $c): bool => trim($c) !== ''
        )));

        foreach ($codes as $code) {
            $id = DB::table('items')
                ->where(function ($q) use ($code): void {
                    $q->where('sku', $code)->orWhere('prod_number', $code);
                })
                ->value('id');
            if ($id) {
                return (int) $id;
            }
        }

        if ($codes === []) {
            $id = DB::table('items')->where('name', $title)->value('id');
            if ($id) {
                return (int) $id;
            }
        }

        return null;
    }

    private function warmCaches(): void
    {
        foreach (DB::table('brands')->select('id', 'name')->cursor() as $b) {
            $k = mb_strtolower(trim($b->name));
            if ($k !== '') {
                $this->brandByLower[$k] = (int) $b->id;
            }
        }

        foreach (DB::table('categories')->select('id', 'name')->cursor() as $c) {
            $k = mb_strtolower(trim($c->name));
            if ($k !== '') {
                $this->categoryByLower[$k] = (int) $c->id;
            }
        }
    }

    /**
     * @param  array<string,string>  $data  normalized lowercase keys
     */
    private function processRow(array $data, string $title): void
    {
        $brandName = trim($data['brand'] ?? '');
        $brandId = $brandName !== '' ? $this->resolveBrandId($brandName) : null;

        $categoryName = trim($this->firstValue($data, [
            'product category',
            'category group',
            'category',
            'suggested categories',
        ]));
        if ($categoryName !== '') {
            $categoryName = trim(explode(',', $categoryName, 2)[0]);
            $categoryName = trim(preg_replace('/\s*[>|].*$/u', '', $categoryName) ?? '');
        }
        if ($categoryName === '') {
            $categoryName = $this->defaultCategoryName;
        }
        $categoryId = $this->resolveCategoryId($categoryName);

        $images = $this->collectImageUrls($data);

        $photoPath = null;
        if (! empty($images[0])) {
            $photoPath = $this->downloadImage($images[0]);
        }

        $sortDetails = $this->firstValue($data, [
            'product features',
            'product highlights',
            'features',
        ]);

        $details = $this->buildDetailsHtml($data);

        $price = $this->parsePrice($this->firstValue($data, ['adjusted price', 'scraped price']));

        $baseSlug = Str::slug($title);
        $slug = $this->uniqueSlug($baseSlug);

        $taxId = null;
        if (isset($data['tax_id']) && $data['tax_id'] !== '' && is_numeric($data['tax_id'])) {
            $taxId = (int) $data['tax_id'];
        }

        $sku = $this->firstValue($data, ['internal sku', 'prod number']);
        if ($sku === '') {
            $sku = trim((string) ($data['transit sku'] ?? ''));
        }

        $productPartNumber = trim($this->firstValue($data, ['product part number']));
        $productPartNumber = $productPartNumber !== '' ? $productPartNumber : null;

        $itemId = DB::table('items')->insertGetId([
            'category_id' => $categoryId,
            'brand_id' => $brandId,
            'tax_id' => $taxId,
            'name' => $title,
            'prod_number' => $sku,
            'moog' => $this->firstValue($data, ['moog', 'interchange part number']) ?: null,
            'product_part_number' => $productPartNumber,
            'slug' => $slug,
            'sku' => $sku ?: null,
            'tags' => 'automotive, parts',
            'sort_details' => $sortDetails,
            'details' => $details,
            'photo' => $photoPath,
            'thumbnail' => $photoPath,
            'status' => 1,
            'file_type' => 'file',
            'item_type' => 'normal',
            'is_type' => 'undefine',
            'previous_price' => $price,
            'discount_price' => $price,
            'stock' => 100,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

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
    }

    /**
     * Skip import when any SKU code already exists on `items.sku` or `items.prod_number`,
     * or when no codes are provided and the product name already exists.
     *
     * @param  array<string,string>  $data  normalized lowercase keys
     */
    private function rowAlreadyExists(array $data, string $title): bool
    {
        return $this->findExistingItemId($data, $title) !== null;
    }

    private function uniqueSlug(string $base): string
    {
        $slug = $base;
        $n = 0;
        while (DB::table('items')->where('slug', $slug)->exists()) {
            $n++;
            $slug = $base.'-'.$n;
        }

        return $slug;
    }

    private function resolveBrandId(string $brandName): int
    {
        $key = mb_strtolower($brandName);
        if (isset($this->brandByLower[$key])) {
            return $this->brandByLower[$key];
        }

        $id = (int) DB::table('brands')->insertGetId([
            'name' => $brandName,
            'slug' => Str::slug($brandName).'-'.Str::random(4),
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->brandByLower[$key] = $id;

        return $id;
    }

    private function resolveCategoryId(string $categoryName): int
    {
        $key = mb_strtolower($categoryName);
        if (isset($this->categoryByLower[$key])) {
            return $this->categoryByLower[$key];
        }

        $id = (int) DB::table('categories')->insertGetId([
            'name' => $categoryName,
            'slug' => Str::slug($categoryName).'-'.Str::random(4),
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->categoryByLower[$key] = $id;

        return $id;
    }

    private function parsePrice(string $raw): float
    {
        $raw = str_replace([',', '$', ' '], '', $raw);

        return is_numeric($raw) ? (float) $raw : 0.0;
    }

    /**
     * @param  array<string,string>  $data  normalized lowercase keys
     */
    private function firstValue(array $data, array $keys): string
    {
        foreach ($keys as $k) {
            $v = trim((string) ($data[$k] ?? ''));
            if ($v !== '') {
                return $v;
            }
        }

        return '';
    }

    /**
     * Full HTML for the product Description tab: main copy + optional dedicated fitment block.
     *
     * @param  array<string,string>  $data
     */
    private function buildDetailsHtml(array $data): string
    {
        $main = $this->firstValue($data, [
            'description',
            'product description',
            'long description',
        ]);

        if ($main === '') {
            $main = trim(
                ($data['product overview'] ?? '').
                ($data['specifications'] ?? '').
                ($data['fitting vehicles'] ?? '')
            );
        }

        $fitment = $this->extractFitmentInput($data);

        if ($fitment === '') {
            return $this->appendTechnicalFooter($main, $data);
        }

        /** @var FitmentTableNormalizer $fitNorm */
        $fitNorm = app(FitmentTableNormalizer::class);
        $fitmentHtml = $fitNorm->toSearchableHtml($fitment);
        if ($fitmentHtml === '') {
            return $this->appendTechnicalFooter($main, $data);
        }

        $separator = $main === '' ? '' : "\n\n";
        $block = $separator;
        if ($fitNorm->shouldAddHeading($fitment)) {
            $block .= '<h3>'.e(__('Vehicle fitment')).'</h3>';
        }
        $block .= $fitmentHtml;

        return $this->appendTechnicalFooter($main.$block, $data);
    }

    /**
     * Box dimensions, listing meta — no dedicated DB columns; stored in HTML details.
     *
     * @param  array<string,string>  $data
     */
    private function appendTechnicalFooter(string $html, array $data): string
    {
        $parts = [];
        $position = trim((string) ($data['position'] ?? ''));
        if ($position !== '') {
            $parts[] = '<p><strong>'.e(__('Position')).':</strong> '.e($position).'</p>';
        }
        $categoryGroup = trim((string) ($data['category group'] ?? ''));
        if ($categoryGroup !== '') {
            $parts[] = '<p><strong>'.e(__('Category group')).':</strong> '.e($categoryGroup).'</p>';
        }
        $productCategory = trim((string) ($data['product category'] ?? ''));
        if ($productCategory !== '') {
            $parts[] = '<p><strong>'.e(__('Product category')).':</strong> '.e($productCategory).'</p>';
        }
        $boxBits = array_filter([
            trim((string) ($data['box length'] ?? '')) !== '' ? 'L: '.trim((string) $data['box length']) : '',
            trim((string) ($data['box width'] ?? '')) !== '' ? 'W: '.trim((string) $data['box width']) : '',
            trim((string) ($data['box height'] ?? '')) !== '' ? 'H: '.trim((string) $data['box height']) : '',
            trim((string) ($data['box weight'] ?? '')) !== '' ? __('Weight').': '.trim((string) $data['box weight']) : '',
        ]);
        if ($boxBits !== []) {
            $parts[] = '<p><strong>'.e(__('Package / box')).':</strong> '.e(implode(', ', $boxBits)).'</p>';
        }
        $pd = trim((string) ($data['product dimensions'] ?? ''));
        if ($pd !== '') {
            $parts[] = '<p><strong>'.e(__('Product dimensions')).':</strong> '.e($pd).'</p>';
        }
        $ip = trim((string) ($data['interchange part number'] ?? ''));
        if ($ip !== '' && trim((string) ($data['moog'] ?? '')) === '') {
            $parts[] = '<p><strong>'.e(__('Interchange part number')).':</strong> '.e($ip).'</p>';
        }
        if ($parts === []) {
            return $html;
        }

        $footer = '<h3>'.e(__('Additional information')).'</h3>'.implode('', $parts);

        return $html === '' ? $footer : $html."\n\n".$footer;
    }

    /**
     * @param  array<string,string>  $data
     */
    private function extractFitmentInput(array $data): string
    {
        $fitment = $this->firstValue($data, [
            'fitment table',
            'vehicle fitment table',
            'fitment',
            'vehicle fitment',
            'ymm',
            'ymm rows',
        ]);

        if ($fitment !== '') {
            return $fitment;
        }

        $y = trim((string) ($data['year'] ?? ''));
        $ma = trim((string) ($data['make'] ?? ''));
        $mo = trim((string) ($data['model'] ?? ''));

        return ($y !== '' && $ma !== '' && $mo !== '') ? $y.'|'.$ma.'|'.$mo : '';
    }

    /**
     * @param  array<string,string>  $data
     */
    private function mergeFitmentIntoExistingItem(int $itemId, array $data): bool
    {
        $fitment = $this->extractFitmentInput($data);
        if ($fitment === '') {
            return false;
        }

        /** @var FitmentTableNormalizer $fitNorm */
        $fitNorm = app(FitmentTableNormalizer::class);
        $fitmentHtml = $fitNorm->toSearchableHtml($fitment);
        if ($fitmentHtml === '') {
            return false;
        }

        preg_match_all(
            '/<tr[^>]*>\s*<td[^>]*>(.*?)<\/td>\s*<td[^>]*>(.*?)<\/td>\s*<td[^>]*>(.*?)<\/td>\s*<\/tr>/si',
            $fitmentHtml,
            $newRows,
            PREG_SET_ORDER
        );
        if ($newRows === []) {
            return false;
        }

        $details = (string) (DB::table('items')->where('id', $itemId)->value('details') ?? '');
        preg_match_all(
            '/<tr[^>]*>\s*<td[^>]*>(.*?)<\/td>\s*<td[^>]*>(.*?)<\/td>\s*<td[^>]*>(.*?)<\/td>\s*<\/tr>/si',
            $details,
            $existingRows,
            PREG_SET_ORDER
        );

        $existingKeys = [];
        foreach ($existingRows as $row) {
            $existingKeys[$this->fitmentRowKey($row[1], $row[2], $row[3])] = true;
        }

        $rowsToAdd = [];
        foreach ($newRows as $row) {
            $key = $this->fitmentRowKey($row[1], $row[2], $row[3]);
            if (! isset($existingKeys[$key])) {
                $rowsToAdd[] = '<tr><td>'.trim($row[1]).'</td><td>'.trim($row[2]).'</td><td>'.trim($row[3]).'</td></tr>';
                $existingKeys[$key] = true;
            }
        }

        if ($rowsToAdd === []) {
            return true;
        }

        $rowsBlock = implode('', $rowsToAdd);
        if (preg_match('/<table[^>]*class="[^"]*\bpa-fitment-table\b[^"]*"[^>]*>/i', $details)) {
            if (stripos($details, '</tbody>') !== false) {
                $details = preg_replace('/<\/tbody>/i', $rowsBlock.'</tbody>', $details, 1) ?? $details;
            } else {
                $details = preg_replace('/<\/table>/i', '<tbody>'.$rowsBlock.'</tbody></table>', $details, 1) ?? $details;
            }
        } else {
            $details .= ($details === '' ? '' : "\n\n")
                .'<h3>'.e(__('Vehicle fitment')).'</h3>'
                .'<table class="pa-fitment-table"><tbody>'.$rowsBlock.'</tbody></table>';
        }

        DB::table('items')->where('id', $itemId)->update([
            'details' => $details,
            'updated_at' => now(),
        ]);

        return true;
    }

    private function fitmentRowKey(string $year, string $make, string $model): string
    {
        return mb_strtolower(trim(strip_tags(html_entity_decode($year))).'|'.trim(strip_tags(html_entity_decode($make))).'|'.trim(strip_tags(html_entity_decode($model))));
    }

    /**
     * For duplicate SKU rows, do not re-upload images when product already has photo.
     * Only fill media if current item photo is empty.
     *
     * @param  array<string,string>  $data
     */
    /**
     * When a row matches an existing item (same internal SKU, transit SKU in `sku`/`prod_number`, or name),
     * persist Product Part Number from CSV when the column is non-empty.
     *
     * @param  array<string,string>  $data  normalized lowercase keys
     */
    private function syncProductPartNumberOnExistingItem(int $itemId, array $data): void
    {
        $productPartNumber = trim($this->firstValue($data, ['product part number']));
        if ($productPartNumber === '') {
            return;
        }

        DB::table('items')->where('id', $itemId)->update([
            'product_part_number' => $productPartNumber,
            'updated_at' => now(),
        ]);
    }

    private function fillExistingItemMediaIfMissing(int $itemId, array $data): void
    {
        $item = DB::table('items')->where('id', $itemId)->first(['photo', 'thumbnail']);
        if (! $item) {
            return;
        }

        $currentPhoto = trim((string) ($item->photo ?? ''));
        if ($currentPhoto !== '') {
            return; // Already has image; skip upload for existing SKU.
        }

        // If gallery already exists, use first gallery photo as main image.
        $existingGalleryPhoto = DB::table('galleries')
            ->where('item_id', $itemId)
            ->orderBy('id')
            ->value('photo');
        if (! empty($existingGalleryPhoto)) {
            DB::table('items')->where('id', $itemId)->update([
                'photo' => $existingGalleryPhoto,
                'thumbnail' => $existingGalleryPhoto,
                'updated_at' => now(),
            ]);

            return;
        }

        $images = $this->collectImageUrls($data);
        if ($images === []) {
            return;
        }

        $mainPhoto = null;
        $downloaded = [];
        foreach ($images as $url) {
            $downloadedPath = $this->downloadImage($url);
            if (! $downloadedPath) {
                continue;
            }
            $downloaded[] = $downloadedPath;
            if ($mainPhoto === null) {
                $mainPhoto = $downloadedPath;
            }
        }

        if ($mainPhoto === null) {
            return;
        }

        DB::table('items')->where('id', $itemId)->update([
            'photo' => $mainPhoto,
            'thumbnail' => $mainPhoto,
            'updated_at' => now(),
        ]);

        $existingGallery = DB::table('galleries')
            ->where('item_id', $itemId)
            ->pluck('photo')
            ->map(static fn ($v) => (string) $v)
            ->all();
        $existingGallerySet = array_fill_keys($existingGallery, true);

        foreach ($downloaded as $path) {
            if ($path === $mainPhoto || isset($existingGallerySet[$path])) {
                continue;
            }
            DB::table('galleries')->insert([
                'item_id' => $itemId,
                'photo' => $path,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $existingGallerySet[$path] = true;
        }
    }

    /**
     * @return list<string>
     */
    private function collectImageUrls(array $data): array
    {
        $urls = [];
        $pipe = trim((string) ($data['images'] ?? ''));
        if ($pipe !== '') {
            foreach (preg_split('/[\|\n,]+/', $pipe) as $u) {
                $u = trim((string) $u);
                if ($u !== '') {
                    $urls[] = $u;
                }
            }
        }
        for ($i = 1; $i <= 14; $i++) {
            $key = 'image '.$i.' url';
            $u = trim((string) ($data[$key] ?? ''));
            if ($u !== '') {
                $urls[] = $u;
            }
        }

        return array_values(array_unique($urls));
    }

    private function normalizeHeader(array $headerLine): array
    {
        $out = [];
        foreach ($headerLine as $i => $col) {
            $col = trim((string) $col);
            if ($i === 0) {
                $col = preg_replace('/^\xEF\xBB\xBF/', '', $col) ?? $col;
            }
            $out[] = $col;
        }

        return $out;
    }

    /**
     * @param  array<string,string>  $combined
     * @return array<string,string>
     */
    private function normalizeRowKeys(array $combined): array
    {
        $data = [];
        foreach ($combined as $k => $v) {
            $data[mb_strtolower(trim((string) $k))] = (string) $v;
        }

        return $data;
    }

    private function rowIsEmpty(array $row): bool
    {
        foreach ($row as $cell) {
            if (trim((string) $cell) !== '') {
                return false;
            }
        }

        return true;
    }

    private function maybeFlushProgress(ProductUpload $upload, int $processed, int $imported, int $skipped): void
    {
        if ($processed % 25 !== 0) {
            return;
        }

        $upload->update([
            'processed_rows' => $processed,
            'imported_count' => $imported,
            'skipped_count' => $skipped,
        ]);
    }

    private function downloadImage(?string $url): ?string
    {
        try {
            $url = trim((string) $url);
            if ($url === '') {
                return null;
            }

            $response = Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0 (compatible; ProductImport/1.0)',
                'Accept' => 'image/*,*/*;q=0.8',
            ])
                ->withoutVerifying()
                ->timeout(60)
                ->get($url);

            if (! $response->successful()) {
                return null;
            }

            $extension = pathinfo(parse_url($url, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION);
            $extension = $extension ?: 'jpg';
            $extension = preg_replace('/[^a-z0-9]/i', '', $extension) ?: 'jpg';

            $fileName = 'OM_'.time().'_'.Str::random(8).'.'.$extension;

            $body = $response->body();
            Storage::disk('public')->put('images/'.$fileName, $body);

            // This project serves files from public/storage/images as a real directory.
            $servedDir = public_path('storage/images');
            if (! File::isDirectory($servedDir)) {
                File::makeDirectory($servedDir, 0755, true);
            }
            File::put($servedDir.'/'.$fileName, $body);

            return $fileName;
        } catch (\Throwable) {
            return null;
        }
    }

    public static function countDataRows(string $path): int
    {
        $file = fopen($path, 'r');
        if ($file === false) {
            return 0;
        }
        fgetcsv($file);
        $n = 0;
        while (fgetcsv($file) !== false) {
            $n++;
        }
        fclose($file);

        return $n;
    }

    /**
     * Build byte offsets that mark each chunk start (data rows only, header skipped).
     *
     * @return list<int>
     */
    public static function chunkStartBytes(string $path, int $chunkSize): array
    {
        $chunkSize = max(1, $chunkSize);
        $file = fopen($path, 'r');
        if ($file === false) {
            return [];
        }

        $header = fgetcsv($file);
        if ($header === false) {
            fclose($file);
            return [];
        }

        $starts = [];
        $rowIndex = 0;
        while (true) {
            $pos = ftell($file);
            $row = fgetcsv($file);
            if ($row === false) {
                break;
            }
            if ($rowIndex % $chunkSize === 0) {
                $starts[] = (int) $pos;
            }
            $rowIndex++;
        }

        fclose($file);

        return $starts;
    }
}
