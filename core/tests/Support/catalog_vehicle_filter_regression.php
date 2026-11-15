<?php
// Standalone seam regression: php tests/Support/catalog_vehicle_filter_regression.php
// Runs without Laravel/vendor (including under php-wasm-cli), using the actual controller, Item and policy.
namespace Illuminate\Database\Eloquent {
    class Model
    {
        private array $attributes = [];
        private array $relations = [];
        public function __construct(array $attributes = []) { $this->attributes = $attributes; }
        public function __get(string $key) { return $this->relations[$key] ?? $this->attributes[$key] ?? null; }
        public function setRelation(string $key, $value): void { $this->relations[$key] = $value; }
    }
    class Builder {}
}
namespace Illuminate\Support {
    class Str
    {
        public static function of(string $value): self { return new self($value); }
        public function __construct(private string $value) {}
        public function lower(): self { $this->value = strtolower($this->value); return $this; }
        public function replaceMatches(string $pattern, string $replacement): self
        { $this->value = preg_replace($pattern, $replacement, $this->value); return $this; }
        public function toString(): string { return $this->value; }
    }
}
namespace Illuminate\Support\Facades {
    class DB {
        public static function raw(string $sql): \RawSqlStub { return new \RawSqlStub($sql); }
    }
}
namespace App\Http\Controllers { class Controller {} }
namespace App\Models {
    class Brand extends \Illuminate\Database\Eloquent\Model
    {
        public static array $rows = [];
        public static function query(): \BrandQueryStub { return new \BrandQueryStub(self::$rows); }
    }
}
namespace {
    function optional($value) { return $value ?? new class { public ?string $name = null; }; }
    function collect(array $values) {
        return new class($values) {
            public function __construct(private array $values) {}
            public function contains(callable $callback): bool {
                foreach ($this->values as $value) { if ($callback($value)) return true; }
                return false;
            }
        };
    }
    // Exercise a future verified exception without ever populating the live policy list.
    $policy = file_get_contents(__DIR__.'/../../app/Support/UniversalFitBrand.php');
    $fixtureNeedle = "'lucasoil' => [],";
    if (substr_count($policy, $fixtureNeedle) !== 1 ||
        !str_contains($policy, "'liquimoly' => [],")) {
        throw new \RuntimeException('Production verified parts must remain empty for this fixture');
    }
    eval('?>'.str_replace($fixtureNeedle, "'lucasoil' => ['TEST-ONLY-9001'],", $policy));
    require __DIR__.'/../../app/Models/Item.php';
    require __DIR__.'/../../app/Http/Controllers/Front/CatalogController.php';

    class BrandQueryStub
    {
        public function __construct(private array $rows) {}
        public function whereIn(string $field, array $values): self {
            $this->rows = array_filter($this->rows,
                fn ($name) => in_array($name, $values, true));
            return $this;
        }
        public function pluck(string $field, string $key): BrandCollectionStub {
            if ($field !== 'name' || $key !== 'id') throw new \RuntimeException('Unexpected brand projection');
            return new BrandCollectionStub($this->rows);
        }
    }
    class BrandCollectionStub
    {
        public function __construct(private array $rows) {}
        public function filter(callable $callback): self {
            return new self(array_filter($this->rows, $callback));
        }
        public function keys(): array { return array_keys($this->rows); }
        public function get(int $id): ?string { return $this->rows[$id] ?? null; }
        public function isEmpty(): bool { return empty($this->rows); }
    }
    class RawSqlStub
    {
        public function __construct(public string $sql) {}
    }
    class ItemQueryStub
    {
        public static array $readSizes = [];
        public ?array $orders = ['id desc'];
        private array $filters = [];
        public function __construct(private array $items) {}
        public function setEagerLoads(array $loads): void {}
        public function with(string $relation): self { return $this; }
        public function getQuery(): self { return $this; }
        public function whereIn($field, array $ids): self {
            $field = $field instanceof RawSqlStub ? $field->sql : $field;
            if (!in_array($field, ['id', 'brand_id', 'sku', 'TRIM(sku)'], true)) {
                throw new \RuntimeException("Unexpected filter: $field");
            }
            $this->filters[] = [$field, $ids];
            return $this;
        }
        private function matchingItems(): array {
            return array_values(array_filter($this->items, function ($item) {
                foreach ($this->filters as [$field, $values]) {
                    $value = $field === 'TRIM(sku)' ? trim($item->sku) : $item->$field;
                    if (!in_array($value, $values, true)) return false;
                }
                return true;
            }));
        }
        public function get(array $columns): ItemCollectionStub {
            foreach (['id', 'brand_id', 'sku'] as $column) {
                if (!in_array($column, $columns, true)) throw new \RuntimeException("Missing $column in projection");
            }
            $matched = $this->matchingItems();
            self::$readSizes[] = count($matched);
            return new ItemCollectionStub($matched);
        }
    }
    class ItemCollectionStub
    {
        public function __construct(private array $items) {}
        public function reject(callable $callback): self {
            return new self(array_values(array_filter($this->items, fn ($item) => !$callback($item))));
        }
        public function filter(callable $callback): self {
            return new self(array_values(array_filter($this->items, $callback)));
        }
        public function pluck(string $key): self {
            return new self(array_map(fn ($item) => $item->$key, $this->items));
        }
        public function all(): array { return $this->items; }
    }
    function item(int $id, ?string $brandName, string $sku): \App\Models\Item {
        $item = new \App\Models\Item(['id' => $id, 'brand_id' => $id, 'sku' => $sku]);
        if ($brandName !== null) {
            $item->setRelation('brand', new \App\Models\Brand(['name' => $brandName]));
            \App\Models\Brand::$rows[$id] = $brandName;
        }
        return $item;
    }
    function assertSameIds(array $expected, array $actual, string $label): void {
        if ($expected !== $actual) throw new \RuntimeException(
            "$label: expected ".json_encode($expected).'; got '.json_encode($actual)
        );
        echo "PASS $label\n";
    }
    $source = file_get_contents(__DIR__.'/../../app/Http/Controllers/Front/CatalogController.php');
    foreach (['index', 'suggestSearch'] as $caller) {
        $start = strpos($source, 'public function '.$caller.'(');
        $end = strpos($source, 'public function ', $start + 16);
        $body = substr($source, $start, $end === false ? null : $end - $start);
        if ($start === false || !str_contains($body,
            '$matchedFitmentIds = $this->includeUniversalFitItemIds($itemsQuery, $matchedFitmentIds);')) {
            throw new \RuntimeException("$caller does not use the shared filtering seam");
        }
        echo "PASS $caller uses shared filtering seam\n";
    }
    $query = new ItemQueryStub([
        item(10, 'Lucas Oil', '20127'),
        item(11, 'LIQUI MOLY', '2248'),
        item(12, 'Mevotech', 'ABC-123'),
        item(13, null, 'ORPHAN-123'),
    ]);
    $method = new \ReflectionMethod(\App\Http\Controllers\Front\CatalogController::class, 'includeUniversalFitItemIds');
    $controller = (new \ReflectionClass(\App\Http\Controllers\Front\CatalogController::class))->newInstanceWithoutConstructor();
    // Cached/indexed or fallback IDs are deliberately stale; a deleted ID must not survive.
    assertSameIds([12, 13], $method->invoke($controller, $query, [10, 11, 12, 13, 999]),
        'stale conditional brands removed; ordinary and null-brand matches kept; deleted ID removed');
    assertSameIds([], $method->invoke($controller, $query, [10, 11]),
        'only application-check products never appear as vehicle matches');
    assertSameIds([], $method->invoke($controller, $query, []),
        'empty matches do not invent universal products');
    $exceptionQuery = new ItemQueryStub([
        item(20, 'lucas-oil', ' TEST-ONLY-9001 '),
        item(21, 'Lucas Oil', 'NOT-VERIFIED'),
        item(22, 'Mevotech', 'TEST-ONLY-9001'),
        item(23, 'LIQUI MOLY', 'TEST-ONLY-9001'),
        item(24, 'Lucas Oil', 'TEST-ONLY-9001'),
        item(25, 'Mevotech', 'REGULAR-MATCH'),
    ]);
    if (!\App\Support\UniversalFitBrand::isVerifiedProduct('lucas-oil', ' TEST-ONLY-9001 ')) {
        throw new \RuntimeException('Synthetic fixture did not exercise the real verified policy');
    }
    assertSameIds([22, 25, 20, 24], $method->invoke($controller, $exceptionQuery, [21, 22, 23, 25, 999]),
        'canonical brand and trimmed verified SKU union; other-brand matches retained');
    if (end(ItemQueryStub::$readSizes) !== 3) {
        throw new \RuntimeException('Verified exception query must prefilter to recognized-brand, trimmed-SKU candidates: '.json_encode(ItemQueryStub::$readSizes));
    }
    echo "PASS verified exception query reads only candidates\n";
    assertSameIds([20, 24], $method->invoke($controller, $exceptionQuery, []),
        'verified exceptions unioned even without indexed matches');
}
