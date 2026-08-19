<?php

namespace App\Services;

use App\Models\ProductUpload;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Header-based CSV import for bulk products (used by queued ProcessProductUploadJob).
 *
 * Import modes are intentionally isolated:
 * - create: Product Part Number is required; matching identifiers are skipped.
 * - update: exact Item ID is preferred, with an exact unique SKU fallback;
 *   unmatched or ambiguous rows are skipped and never inserted.
 *
 * Column headers are matched case-insensitively after trim (UTF-8 BOM stripped).
 *
 * Core (common export / admin):
 * - Title OR Product Name (required for new inserts; optional when updating by Item ID or SKU)
 * - SKU OR PROD NUMBER OR Internal SKU OR Transit SKU (used for `sku` / `prod_number`; duplicate rows skipped by these codes)
 * - Product Part Number → `product_part_number` (matches existing items; also updated on duplicate SKU / transit matches when present)
 * - MOOG OR Interchange Part Number (stored in `moog` when MOOG empty)
 * - Brand, Product Category OR Category Group OR Category OR Suggested Categories
 *   (first segment before comma or &gt;)
 * - Images (pipe/comma/newline list) OR Image 1 URL … Image 14 URL (merged; duplicates removed)
 * - ADJUSTED PRICE OR Scraped Price (also updated on duplicate SKU / transit matches when present)
 * - Stock OR Inventory OR Quantity (also updated on duplicate SKU / transit matches when present)
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
    public const MODE_CREATE = 'create';

    public const MODE_UPDATE = 'update';

    public const MODES = [self::MODE_CREATE, self::MODE_UPDATE];

    /** @var array<string,int> lowercase name => id */
    private array $brandByLower = [];

    /** @var array<string,int> lowercase name => id */
    private array $categoryByLower = [];

    private string $defaultCategoryName = 'Automotive Lubricants';

    /** @var array<string,true> */
    private array $seenRowFingerprints = [];

    /** @var array<string,int> */
    private array $itemIdByCode = [];

    /** @var array<string,int> */
    private array $itemIdByProductPartNumber = [];

    /** @var array<string,int> */
    private array $itemIdByName = [];

    /**
     * Process at most $chunkSize CSV data rows starting from byte offset.
     *
     * @return array{processed:int,imported:int,skipped:int,next_byte:int,has_more:bool}
     */
    public function importChunk(
        string $path,
        int $startByte,
        int $chunkSize,
        string $mode = self::MODE_CREATE
    ): array
    {
        if (! is_file($path)) {
            throw new \InvalidArgumentException('CSV file not found: '.$path);
        }

        if (! in_array($mode, self::MODES, true)) {
            throw new \InvalidArgumentException('Unsupported product import mode: '.$mode);
        }

        $this->warmCaches();
        $this->seenRowFingerprints = [];

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
                [$didImport, $didSkip] = $this->processRecord($row, $header, $mode);
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
    private function processRecord(array $row, array $header, string $mode): array
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
        if ($this->isRepeatedRow($data)) {
            return [false, true];
        }

        return $mode === self::MODE_UPDATE
            ? $this->processUpdateRecord($data)
            : $this->processCreateRecord($data);
    }

    /**
     * Create mode never modifies an existing item. Product Part Number is the
     * required primary identity, while SKU fields provide additional duplicate checks.
     *
     * @param  array<string,string>  $data
     * @return array{0:bool,1:bool}
     */
    private function processCreateRecord(array $data): array
    {
        $title = trim($this->firstValue($data, ['title', 'product name']));
        if (! $this->newRowHasRequiredValues($data, $title)) {
            return [false, true];
        }

        if ($this->findExistingItemId($data, $title) !== null) {
            return [false, true];
        }

        $this->processRow($data, $title);

        return [true, false];
    }

    /**
     * Update mode prefers Item ID and falls back to an exact unique SKU match.
     * This path cannot call the insert method under any circumstance.
     *
     * @param  array<string,string>  $data
     * @return array{0:bool,1:bool}
     */
    private function processUpdateRecord(array $data): array
    {
        $itemId = $this->resolveUpdateItemId($data);
        if ($itemId === null) {
            return [false, true];
        }

        if ($this->updateIdentifiersConflict($itemId, $data)) {
            return [false, true];
        }

        $title = trim($this->firstValue($data, ['title', 'product name']));
        $didUpdate = $this->syncExistingItem($itemId, $data, $title);
        $didUpdateMedia = $this->fillExistingItemMediaIfMissing($itemId, $data);
        $didMergeFitment = $this->mergeFitmentIntoExistingItem($itemId, $data);

        return ($didUpdate || $didUpdateMedia || $didMergeFitment) ? [true, false] : [false, true];
    }

    /**
     * @param  array<string,string>  $data
     */
    private function newRowHasRequiredValues(array $data, string $title): bool
    {
        $partNumber = $this->firstValue($data, ['product part number']);

        return $title !== ''
            && $partNumber !== ''
            && $partNumber !== 'EXAMPLE-PPN-1001'
            && trim((string) ($data['brand'] ?? '')) !== ''
            && $this->categoryNameFromRow($data) !== ''
            && $this->priceFromRow($data) !== null
            && $this->stockFromRow($data) !== null;
    }

    /**
     * @param  array<string,string>  $data
     */
    private function updateItemIdFromRow(array $data): ?int
    {
        // The current product export names this database identifier `id`.
        // Accept that exact alias; SKU fallback is handled separately.
        $rawId = $this->firstValue($data, ['item id', 'id']);

        return ctype_digit($rawId) && (int) $rawId > 0 ? (int) $rawId : null;
    }

    /**
     * Resolve an update target without guessing. A valid Item ID wins. When it
     * is missing or unknown, every supplied SKU is checked against both SKU
     * storage columns and the row is accepted only when one unique item matches.
     *
     * @param  array<string,string>  $data
     */
    private function resolveUpdateItemId(array $data): ?int
    {
        $itemId = $this->updateItemIdFromRow($data);
        if ($itemId !== null && DB::table('items')->where('id', $itemId)->exists()) {
            return $itemId;
        }

        $matchedIds = [];
        foreach ($this->updateSkuValuesFromRow($data) as $sku) {
            $ids = DB::table('items')
                ->where(function ($query) use ($sku): void {
                    $query->where('sku', $sku)
                        ->orWhere('prod_number', $sku);
                })
                ->limit(2)
                ->pluck('id');

            foreach ($ids as $id) {
                $matchedIds[] = (int) $id;
            }
        }

        return $this->uniqueMatchedItemId($matchedIds);
    }

    /**
     * @param  array<string,string>  $data
     * @return list<string>
     */
    private function updateSkuValuesFromRow(array $data): array
    {
        $values = [];
        foreach (['sku', 'transit sku', 'internal sku', 'prod number'] as $key) {
            $value = trim((string) ($data[$key] ?? ''));
            $normalized = mb_strtolower($value);
            if ($value !== '' && ! array_key_exists($normalized, $values)) {
                $values[$normalized] = $value;
            }
        }

        return array_values($values);
    }

    /**
     * @param  array<int,int>  $matchedIds
     */
    private function uniqueMatchedItemId(array $matchedIds): ?int
    {
        $matchedIds = array_values(array_unique(array_filter(
            $matchedIds,
            static fn (int $id): bool => $id > 0
        )));

        return count($matchedIds) === 1 ? $matchedIds[0] : null;
    }

    /**
     * Prevent an update from assigning an identifier already owned by another item.
     *
     * @param  array<string,string>  $data
     */
    private function updateIdentifiersConflict(int $itemId, array $data): bool
    {
        foreach ($this->explicitUpdateIdentifiers($data) as $identifier) {
            if (DB::table('items')
                ->where('id', '<>', $itemId)
                ->where(function ($query) use ($identifier): void {
                    $query->where('sku', $identifier)
                        ->orWhere('prod_number', $identifier)
                        ->orWhere('product_part_number', $identifier);
                })
                ->exists()) {
                return true;
            }
        }

        $partNumber = $this->firstValue($data, ['product part number']);

        return $partNumber !== '' && DB::table('items')
            ->where('id', '<>', $itemId)
            ->where(function ($query) use ($partNumber): void {
                $query->where('product_part_number', $partNumber)
                    ->orWhere('sku', $partNumber)
                    ->orWhere('prod_number', $partNumber);
            })
            ->exists();
    }

    /**
     * Update mode changes only identifiers explicitly provided by the user.
     * This avoids copying Internal SKU into sku or Transit SKU into prod_number.
     *
     * @param  array<string,string>  $data
     * @return array<string,string>
     */
    private function explicitUpdateIdentifiers(array $data): array
    {
        $identifiers = [];
        $sku = $this->firstValue($data, ['sku', 'transit sku']);
        $prodNumber = $this->firstValue($data, ['internal sku', 'prod number']);

        if ($sku !== '') {
            $identifiers['sku'] = $sku;
        }
        if ($prodNumber !== '') {
            $identifiers['prod_number'] = $prodNumber;
        }

        return $identifiers;
    }

    /**
     * Validate file structure before any queue job is created.
     *
     * @param  array<int,string|null>  $headerLine
     * @return list<string>
     */
    public static function validateHeaders(array $headerLine, string $mode): array
    {
        if (! in_array($mode, self::MODES, true)) {
            return ['Invalid product import mode.'];
        }

        $headers = [];
        foreach ($headerLine as $index => $header) {
            $header = trim((string) $header);
            if ($index === 0) {
                $header = preg_replace('/^\xEF\xBB\xBF/', '', $header) ?? $header;
            }
            $headers[] = mb_strtolower($header);
        }

        if ($headers === [] || $headers === ['']) {
            return ['The uploaded file is empty or has no header row.'];
        }

        if ($mode === self::MODE_UPDATE) {
            $hasIdentity = array_intersect(
                $headers,
                ['item id', 'id', 'sku', 'transit sku', 'internal sku', 'prod number']
            ) !== [];
            $errors = $hasIdentity
                ? []
                : ['Update files must contain Item ID/id or an exact SKU, Transit SKU, Internal SKU, or PROD NUMBER column.'];

            $updateColumns = [
                'title', 'product name', 'product part number', 'sku', 'internal sku', 'prod number',
                'transit sku', 'brand', 'product category', 'category group', 'category',
                'suggested category', 'suggested categories', 'adjusted price', 'scraped price',
                'stock', 'stock quantity', 'inventory', 'inventory quantity', 'quantity', 'qty',
                'description', 'product description', 'long description', 'product overview',
                'specifications', 'fitting vehicles', 'product features', 'product highlights',
                'features', 'images', 'image 1 url', 'image 2 url', 'image 3 url',
                'image 4 url', 'image 5 url', 'image 6 url', 'image 7 url', 'image 8 url',
                'image 9 url', 'image 10 url', 'image 11 url', 'image 12 url',
                'image 13 url', 'image 14 url', 'fitment table', 'vehicle fitment table',
                'fitment', 'vehicle fitment', 'ymm', 'ymm rows', 'year', 'make', 'model',
                'moog', 'interchange part number', 'interchange part numbers',
                'product keywords', 'keywords', 'tags', 'meta description', 'tax_id',
            ];
            if (array_intersect($headers, $updateColumns) === []) {
                $errors[] = 'Update files must contain at least one supported product field in addition to the matching identifier.';
            }

            return $errors;
        }

        $requirements = [
            'Title' => ['title', 'product name'],
            'Product Part Number' => ['product part number'],
            'Brand' => ['brand'],
            'Product Category' => ['product category', 'category group', 'category', 'suggested category', 'suggested categories'],
            'ADJUSTED PRICE' => ['adjusted price', 'scraped price'],
            'Stock' => ['stock', 'stock quantity', 'inventory', 'inventory quantity', 'quantity', 'qty'],
        ];

        $missing = [];
        foreach ($requirements as $label => $aliases) {
            if (array_intersect($headers, $aliases) === []) {
                $missing[] = $label;
            }
        }

        return $missing === []
            ? []
            : ['New-product files are missing required columns: '.implode(', ', $missing).'.'];
    }

    /**
     * @param  array<string,string>  $data
     */
    private function findExistingItemId(array $data, string $title): ?int
    {
        $internal = $this->firstValue($data, ['internal sku', 'prod number']);
        $transit = trim((string) ($data['transit sku'] ?? ''));
        $productPartNumber = trim($this->firstValue($data, ['product part number']));
        $codes = array_values(array_unique(array_filter(
            [$internal, $transit],
            static fn (string $c): bool => trim($c) !== ''
        )));

        foreach ($codes as $code) {
            $cacheKey = mb_strtolower($code);
            $id = $this->itemIdByCode[$cacheKey] ?? null;
            if ($id === null) {
                $id = DB::table('items')
                    ->where(function ($q) use ($code): void {
                        $q->where('sku', $code)
                            ->orWhere('prod_number', $code)
                            ->orWhere('product_part_number', $code);
                    })
                    ->value('id');
            }
            if ($id) {
                return $this->itemIdByCode[$cacheKey] = (int) $id;
            }
        }

        if ($productPartNumber !== '') {
            $cacheKey = mb_strtolower($productPartNumber);
            $id = $this->itemIdByProductPartNumber[$cacheKey] ?? null;
            if ($id === null) {
                $id = DB::table('items')
                    ->where(function ($query) use ($productPartNumber): void {
                        $query->where('product_part_number', $productPartNumber)
                            ->orWhere('sku', $productPartNumber)
                            ->orWhere('prod_number', $productPartNumber);
                    })
                    ->value('id');
            }
            if ($id) {
                return $this->itemIdByProductPartNumber[$cacheKey] = (int) $id;
            }
        }

        // When Transit SKU or Product Part Number is present, do not fall back to name (avoids wrong item on partial rows).
        $ignoreNameMatch = $transit !== '' || $productPartNumber !== '';
        if (! $ignoreNameMatch && $codes === [] && $title !== '') {
            $cacheKey = mb_strtolower($title);
            $id = $this->itemIdByName[$cacheKey] ?? null;
            if ($id === null) {
                $id = DB::table('items')->where('name', $title)->value('id');
            }
            if ($id) {
                return $this->itemIdByName[$cacheKey] = (int) $id;
            }
        }

        return null;
    }

    /**
     * Identical export rows do not need repeated database writes. Rows with
     * different fitment or inventory values retain distinct fingerprints.
     *
     * @param  array<string,string>  $data
     */
    private function isRepeatedRow(array $data): bool
    {
        $fingerprint = hash('sha256', serialize($data));
        if (isset($this->seenRowFingerprints[$fingerprint])) {
            return true;
        }

        $this->seenRowFingerprints[$fingerprint] = true;

        return false;
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

        $categoryName = $this->categoryNameFromRow($data);
        if ($categoryName === '') {
            $categoryName = $this->defaultCategoryName;
        }
        $categoryId = $this->resolveCategoryId($categoryName);

        $images = $this->collectImageUrls($data);

        $downloadedImages = [];
        foreach ($images as $url) {
            $downloadedImage = $this->downloadImage($url);
            if ($downloadedImage) {
                $downloadedImages[] = $downloadedImage;
            }
        }

        $photoPath = $downloadedImages[0] ?? null;

        $sortDetails = $this->firstValue($data, [
            'product features',
            'product highlights',
            'features',
        ]);

        $details = $this->buildDetailsHtml($data);

        $price = $this->priceFromRow($data) ?? 0.0;
        $stock = $this->stockFromRow($data) ?? 100;

        $identifiers = $this->identifiersFromRow($data);
        $productPartNumber = trim($this->firstValue($data, ['product part number']));
        $productPartNumber = $productPartNumber !== '' ? $productPartNumber : null;
        $slugIdentifier = $productPartNumber ?? $identifiers['sku'] ?? $identifiers['prod_number'];
        $slug = $this->uniqueSlug($this->itemSlugBase($title, $slugIdentifier));

        $taxId = null;
        if (isset($data['tax_id']) && $data['tax_id'] !== '' && is_numeric($data['tax_id'])) {
            $taxId = (int) $data['tax_id'];
        }

        $keywords = $this->firstValue($data, ['product keywords', 'keywords', 'tags']);
        $metaDescription = $this->firstValue($data, ['meta description']);

        $itemId = DB::table('items')->insertGetId([
            'category_id' => $categoryId,
            'brand_id' => $brandId,
            'tax_id' => $taxId,
            'name' => $title,
            'prod_number' => $identifiers['prod_number'],
            'moog' => $this->firstValue($data, ['moog', 'interchange part number', 'interchange part numbers']) ?: null,
            'product_part_number' => $productPartNumber,
            'slug' => $slug,
            'sku' => $identifiers['sku'],
            'tags' => $keywords !== '' ? $keywords : 'automotive, parts',
            'sort_details' => $sortDetails,
            'details' => $details,
            'meta_keywords' => $keywords !== '' ? $keywords : null,
            'meta_description' => $metaDescription !== '' ? $metaDescription : null,
            'photo' => $photoPath,
            'thumbnail' => $photoPath,
            'status' => 1,
            'file_type' => 'file',
            'item_type' => 'normal',
            'is_type' => 'undefine',
            'previous_price' => $price,
            'discount_price' => $price,
            'stock' => $stock,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach (array_slice($downloadedImages, 1) as $galleryPath) {
            DB::table('galleries')->insert([
                'item_id' => $itemId,
                'photo' => $galleryPath,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Skip import when any SKU code already exists on `items.sku` or `items.prod_number`,
     * when `product_part_number` matches, or when no identifiers block name match and the product name already exists.
     *
     * @param  array<string,string>  $data  normalized lowercase keys
     */
    private function rowAlreadyExists(array $data, string $title): bool
    {
        return $this->findExistingItemId($data, $title) !== null;
    }

    private function uniqueSlug(string $base): string
    {
        if (! DB::table('items')->where('slug', $base)->exists()) {
            return $base;
        }

        do {
            $slug = $base.'-'.Str::lower(Str::random(8));
        } while (DB::table('items')->where('slug', $slug)->exists());

        return $slug;
    }

    private function itemSlugBase(string $title, ?string $identifier): string
    {
        $identifier = trim((string) $identifier);
        if ($identifier === '' || mb_stripos($title, $identifier) !== false) {
            return Str::slug($title);
        }

        return Str::slug($title.'-'.$identifier);
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

    /**
     * @param  array<string,string>  $data  normalized lowercase keys
     */
    private function categoryNameFromRow(array $data): string
    {
        $categoryName = trim($this->firstValue($data, [
            'product category',
            'category group',
            'category',
            'suggested category',
            'suggested categories',
        ]));
        if ($categoryName === '') {
            return '';
        }

        $categoryName = trim(explode(',', $categoryName, 2)[0]);

        return trim(preg_replace('/\s*[>|].*$/u', '', $categoryName) ?? '');
    }

    /**
     * @param  array<string,string>  $data  normalized lowercase keys
     * @return array{sku:?string,prod_number:?string}
     */
    private function identifiersFromRow(array $data): array
    {
        $internal = $this->firstValue($data, ['internal sku', 'prod number']);
        $transit = trim((string) ($data['transit sku'] ?? ''));

        return [
            'sku' => $transit !== '' ? $transit : ($internal !== '' ? $internal : null),
            'prod_number' => $internal !== '' ? $internal : ($transit !== '' ? $transit : null),
        ];
    }

    /**
     * @param  array<string,string>  $data  normalized lowercase keys
     */
    private function priceFromRow(array $data): ?float
    {
        $rawPrice = $this->firstValue($data, ['adjusted price', 'scraped price']);
        if ($rawPrice === '') {
            return null;
        }

        $normalized = str_replace([',', '$', ' '], '', $rawPrice);
        if (! is_numeric($normalized)) {
            return null;
        }

        $price = (float) $normalized;

        return $price >= 0 ? $price : null;
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

        $newRows = $this->extractFitmentRows($fitmentHtml);
        if ($newRows === []) {
            return false;
        }

        $details = (string) (DB::table('items')->where('id', $itemId)->value('details') ?? '');
        [$updatedDetails, $didChange] = $this->mergeFitmentRowsIntoDetails($details, $newRows);

        if ($didChange) {
            DB::table('items')->where('id', $itemId)->update([
                'details' => $updatedDetails,
                'updated_at' => now(),
            ]);
        }

        return true;
    }

    private function preserveExistingFitment(string $updatedDetails, string $existingDetails): string
    {
        $existingRows = $this->extractFitmentRows($existingDetails);
        if ($existingRows === []) {
            return $updatedDetails;
        }

        [$mergedDetails] = $this->mergeFitmentRowsIntoDetails($updatedDetails, $existingRows);

        return $mergedDetails;
    }

    /**
     * @return list<array<int,string>>
     */
    private function extractFitmentRows(string $html): array
    {
        preg_match_all(
            '/<tr[^>]*>\s*<td[^>]*>(.*?)<\/td>\s*<td[^>]*>(.*?)<\/td>\s*<td[^>]*>(.*?)<\/td>\s*<\/tr>/si',
            $html,
            $rows,
            PREG_SET_ORDER
        );

        return $rows;
    }

    /**
     * @param  list<array<int,string>>  $rows
     * @return array{0:string,1:bool}
     */
    private function mergeFitmentRowsIntoDetails(string $details, array $rows): array
    {
        $existingKeys = [];
        foreach ($this->extractFitmentRows($details) as $row) {
            $existingKeys[$this->fitmentRowKey($row[1], $row[2], $row[3])] = true;
        }

        $rowsToAdd = [];
        foreach ($rows as $row) {
            $key = $this->fitmentRowKey($row[1], $row[2], $row[3]);
            if (isset($existingKeys[$key])) {
                continue;
            }

            $rowsToAdd[] = '<tr><td>'.trim($row[1]).'</td><td>'.trim($row[2]).'</td><td>'.trim($row[3]).'</td></tr>';
            $existingKeys[$key] = true;
        }

        if ($rowsToAdd === []) {
            return [$details, false];
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

        return [$details, true];
    }

    private function fitmentRowKey(string $year, string $make, string $model): string
    {
        return mb_strtolower(trim(strip_tags(html_entity_decode($year))).'|'.trim(strip_tags(html_entity_decode($make))).'|'.trim(strip_tags(html_entity_decode($model))));
    }

    /**
     * Update a matched product from non-empty CSV cells without erasing curated data.
     *
     * @param  array<string,string>  $data  normalized lowercase keys
     */
    private function syncExistingItem(int $itemId, array $data, string $title): bool
    {
        $updates = $this->existingItemScalarUpdates($data, $title);

        if (array_key_exists('details', $updates)) {
            $existingDetails = (string) (DB::table('items')->where('id', $itemId)->value('details') ?? '');
            $updates['details'] = $this->preserveExistingFitment($updates['details'], $existingDetails);
        }

        $brandName = trim((string) ($data['brand'] ?? ''));
        if ($brandName !== '') {
            $updates['brand_id'] = $this->resolveBrandId($brandName);
        }

        $categoryName = $this->categoryNameFromRow($data);
        if ($categoryName !== '') {
            $updates['category_id'] = $this->resolveCategoryId($categoryName);
        }

        if ($updates === []) {
            return false;
        }

        $updates['updated_at'] = now();
        DB::table('items')->where('id', $itemId)->update($updates);

        return true;
    }

    /**
     * @param  array<string,string>  $data  normalized lowercase keys
     * @return array<string,mixed>
     */
    private function existingItemScalarUpdates(array $data, string $title): array
    {
        $updates = [];

        if ($title !== '') {
            $updates['name'] = $title;
        }

        foreach ($this->explicitUpdateIdentifiers($data) as $column => $identifier) {
            $updates[$column] = $identifier;
        }

        $productPartNumber = $this->firstValue($data, ['product part number']);
        if ($productPartNumber !== '') {
            $updates['product_part_number'] = $productPartNumber;
        }

        $moog = $this->firstValue($data, ['moog', 'interchange part number', 'interchange part numbers']);
        if ($moog !== '') {
            $updates['moog'] = $moog;
        }

        $features = $this->firstValue($data, ['product features', 'product highlights', 'features']);
        if ($features !== '') {
            $updates['sort_details'] = $features;
        }

        if ($this->hasAnyValue($data, [
            'description',
            'product description',
            'long description',
            'product overview',
            'specifications',
            'fitting vehicles',
        ])) {
            $updates['details'] = $this->buildDetailsHtml($data);
        }

        $price = $this->priceFromRow($data);
        if ($price !== null) {
            $updates['previous_price'] = $price;
            $updates['discount_price'] = $price;
        }

        $stock = $this->stockFromRow($data);
        if ($stock !== null) {
            $updates['stock'] = $stock;
        }

        $keywords = $this->firstValue($data, ['product keywords', 'keywords', 'tags']);
        if ($keywords !== '') {
            $updates['tags'] = $keywords;
            $updates['meta_keywords'] = $keywords;
        }

        $metaDescription = $this->firstValue($data, ['meta description']);
        if ($metaDescription !== '') {
            $updates['meta_description'] = $metaDescription;
        }

        $taxId = trim((string) ($data['tax_id'] ?? ''));
        if ($taxId !== '' && is_numeric($taxId)) {
            $updates['tax_id'] = (int) $taxId;
        }

        return $updates;
    }

    /**
     * @param  array<string,string>  $data
     * @param  list<string>  $keys
     */
    private function hasAnyValue(array $data, array $keys): bool
    {
        foreach ($keys as $key) {
            if (trim((string) ($data[$key] ?? '')) !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string,string>  $data  normalized lowercase keys
     */
    private function stockFromRow(array $data): ?int
    {
        $rawStock = $this->firstValue($data, [
            'stock',
            'stock quantity',
            'inventory',
            'inventory quantity',
            'quantity',
            'qty',
        ]);

        if ($rawStock === '') {
            return null;
        }

        $normalized = str_replace([',', ' '], '', $rawStock);
        if (! is_numeric($normalized)) {
            return null;
        }

        return max(0, (int) floor((float) $normalized));
    }

    /**
     * For duplicate SKU rows, do not re-upload images when product already has photo.
     * Only fill media if current item photo is empty.
     *
     * @param  array<string,string>  $data
     */
    private function fillExistingItemMediaIfMissing(int $itemId, array $data): bool
    {
        $item = DB::table('items')->where('id', $itemId)->first(['photo', 'thumbnail']);
        if (! $item) {
            return false;
        }

        $currentPhoto = trim((string) ($item->photo ?? ''));
        if ($currentPhoto !== '') {
            return false; // Existing media is preserved in update mode.
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

            return true;
        }

        $images = $this->collectImageUrls($data);
        if ($images === []) {
            return false;
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
            return false;
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

        return true;
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

            $body = $response->body();
            if ($body === '' || strlen($body) > 15 * 1024 * 1024) {
                return null;
            }

            $mimeType = (new \finfo(FILEINFO_MIME_TYPE))->buffer($body);
            $extension = match ($mimeType) {
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/webp' => 'webp',
                'image/gif' => 'gif',
                'image/avif' => 'avif',
                default => null,
            };
            if ($extension === null) {
                return null;
            }

            $fileName = 'OM_'.time().'_'.Str::random(8).'.'.$extension;

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
