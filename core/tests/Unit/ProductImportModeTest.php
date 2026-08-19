<?php

namespace Tests\Unit;

use App\Services\ItemCsvImporter;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class ProductImportModeTest extends TestCase
{
    public function test_new_product_headers_require_the_primary_identity_and_core_fields(): void
    {
        $valid = [
            'Title', 'Product Part Number', 'Brand', 'Product Category',
            'ADJUSTED PRICE', 'Stock',
        ];

        $this->assertSame([], ItemCsvImporter::validateHeaders($valid, ItemCsvImporter::MODE_CREATE));

        $errors = ItemCsvImporter::validateHeaders(
            ['Title', 'Brand', 'Product Category', 'ADJUSTED PRICE', 'Stock'],
            ItemCsvImporter::MODE_CREATE
        );

        $this->assertStringContainsString('Product Part Number', implode(' ', $errors));
    }

    public function test_update_headers_require_item_id_or_sku_and_an_update_field(): void
    {
        $this->assertSame(
            [],
            ItemCsvImporter::validateHeaders(['Item ID', 'Stock'], ItemCsvImporter::MODE_UPDATE)
        );

        $this->assertSame(
            [],
            ItemCsvImporter::validateHeaders(['SKU', 'Stock'], ItemCsvImporter::MODE_UPDATE)
        );

        $errors = ItemCsvImporter::validateHeaders(['Product Part Number', 'Stock'], ItemCsvImporter::MODE_UPDATE);

        $this->assertStringContainsString('SKU', implode(' ', $errors));
    }

    public function test_update_headers_accept_the_current_product_export_id_column(): void
    {
        $this->assertSame(
            [],
            ItemCsvImporter::validateHeaders(['id', 'ADJUSTED PRICE'], ItemCsvImporter::MODE_UPDATE)
        );
    }

    public function test_update_headers_accept_media_seo_and_fitment_only_updates(): void
    {
        $this->assertSame(
            [],
            ItemCsvImporter::validateHeaders(['Item ID', 'Image 1 URL'], ItemCsvImporter::MODE_UPDATE)
        );
        $this->assertSame(
            [],
            ItemCsvImporter::validateHeaders(['Item ID', 'Meta Description'], ItemCsvImporter::MODE_UPDATE)
        );
        $this->assertSame(
            [],
            ItemCsvImporter::validateHeaders(['Item ID', 'Vehicle Fitment Table'], ItemCsvImporter::MODE_UPDATE)
        );
    }

    public function test_update_item_id_does_not_fall_back_to_product_part_number(): void
    {
        $method = new ReflectionMethod(ItemCsvImporter::class, 'updateItemIdFromRow');
        $method->setAccessible(true);
        $importer = new ItemCsvImporter;

        $this->assertNull($method->invoke($importer, ['product part number' => 'PPN-100']));
        $this->assertNull($method->invoke($importer, ['item id' => 'not-a-number']));
        $this->assertSame(123, $method->invoke($importer, ['item id' => '123']));
        $this->assertSame(456, $method->invoke($importer, ['id' => '456']));
    }

    public function test_update_sku_fallback_accepts_supported_sku_columns_and_deduplicates_values(): void
    {
        $method = new ReflectionMethod(ItemCsvImporter::class, 'updateSkuValuesFromRow');
        $method->setAccessible(true);

        $this->assertSame(
            ['SKU-100', 'INTERNAL-200'],
            $method->invoke(new ItemCsvImporter, [
                'sku' => ' SKU-100 ',
                'transit sku' => 'sku-100',
                'internal sku' => 'INTERNAL-200',
            ])
        );
    }

    public function test_sku_fallback_requires_one_unique_matched_item(): void
    {
        $method = new ReflectionMethod(ItemCsvImporter::class, 'uniqueMatchedItemId');
        $method->setAccessible(true);
        $importer = new ItemCsvImporter;

        $this->assertSame(10, $method->invoke($importer, [10, 10]));
        $this->assertNull($method->invoke($importer, []));
        $this->assertNull($method->invoke($importer, [10, 20]));
    }

    public function test_new_rows_require_values_for_every_documented_core_column(): void
    {
        $method = new ReflectionMethod(ItemCsvImporter::class, 'newRowHasRequiredValues');
        $method->setAccessible(true);
        $importer = new ItemCsvImporter;
        $row = [
            'product part number' => 'PPN-100',
            'brand' => 'Example Brand',
            'product category' => 'Brake Pads',
            'adjusted price' => '10.50',
            'stock' => '5',
        ];

        $this->assertTrue($method->invoke($importer, $row, 'Example Product'));
        $this->assertFalse($method->invoke($importer, array_merge($row, ['stock' => '']), 'Example Product'));
        $this->assertFalse($method->invoke($importer, $row, ''));
        $this->assertFalse($method->invoke(
            $importer,
            array_merge($row, ['product part number' => 'EXAMPLE-PPN-1001']),
            'Example Product'
        ));
    }

    public function test_downloadable_templates_match_their_import_modes(): void
    {
        $base = dirname(__DIR__, 2).'/resources/import-templates/';

        foreach (ItemCsvImporter::MODES as $mode) {
            $path = $base."products-{$mode}-example.csv";
            $this->assertFileExists($path);

            $handle = fopen($path, 'r');
            $header = fgetcsv($handle);
            fclose($handle);

            $this->assertSame([], ItemCsvImporter::validateHeaders($header ?: [], $mode));
        }
    }
}
