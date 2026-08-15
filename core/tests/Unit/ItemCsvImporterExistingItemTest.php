<?php

namespace Tests\Unit;

use App\Services\ItemCsvImporter;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class ItemCsvImporterExistingItemTest extends TestCase
{
    public function test_it_builds_updates_for_supported_existing_product_fields(): void
    {
        $updates = $this->existingItemUpdates([
            'transit sku' => 'SKU-20006',
            'product part number' => 'PPN-20006',
            'interchange part numbers' => 'MOOG-123',
            'product features' => 'Updated features',
            'product description' => 'Updated description',
            'scraped price' => '$24.99',
            'stock' => '18',
            'product keywords' => 'oil, engine',
            'meta description' => 'Updated SEO description',
            'tax_id' => '3',
        ], 'Updated product name');

        $this->assertSame('Updated product name', $updates['name']);
        $this->assertSame('SKU-20006', $updates['sku']);
        $this->assertArrayNotHasKey('prod_number', $updates);
        $this->assertSame('PPN-20006', $updates['product_part_number']);
        $this->assertSame('MOOG-123', $updates['moog']);
        $this->assertSame('Updated features', $updates['sort_details']);
        $this->assertSame('Updated description', $updates['details']);
        $this->assertSame(24.99, $updates['previous_price']);
        $this->assertSame(24.99, $updates['discount_price']);
        $this->assertSame(18, $updates['stock']);
        $this->assertSame('oil, engine', $updates['tags']);
        $this->assertSame('oil, engine', $updates['meta_keywords']);
        $this->assertSame('Updated SEO description', $updates['meta_description']);
        $this->assertSame(3, $updates['tax_id']);
    }

    public function test_it_keeps_internal_and_transit_identifiers_separate(): void
    {
        $updates = $this->existingItemUpdates([
            'internal sku' => 'INTERNAL-100',
            'transit sku' => 'TRANSIT-200',
        ], '');

        $this->assertSame('TRANSIT-200', $updates['sku']);
        $this->assertSame('INTERNAL-100', $updates['prod_number']);
    }

    public function test_updating_one_identifier_does_not_overwrite_the_other_identifier(): void
    {
        $internalOnly = $this->existingItemUpdates(['internal sku' => 'INTERNAL-ONLY'], '');
        $this->assertSame('INTERNAL-ONLY', $internalOnly['prod_number']);
        $this->assertArrayNotHasKey('sku', $internalOnly);

        $transitOnly = $this->existingItemUpdates(['transit sku' => 'TRANSIT-ONLY'], '');
        $this->assertSame('TRANSIT-ONLY', $transitOnly['sku']);
        $this->assertArrayNotHasKey('prod_number', $transitOnly);
    }

    public function test_invalid_or_negative_prices_do_not_overwrite_existing_prices(): void
    {
        $this->assertArrayNotHasKey('previous_price', $this->existingItemUpdates([
            'scraped price' => 'N/A',
        ], ''));

        $this->assertArrayNotHasKey('previous_price', $this->existingItemUpdates([
            'scraped price' => '-10',
        ], ''));
    }

    public function test_blank_cells_do_not_overwrite_existing_product_values(): void
    {
        $updates = $this->existingItemUpdates([
            'transit sku' => '',
            'product part number' => '',
            'product description' => '',
            'scraped price' => '',
            'stock' => '',
            'product keywords' => '',
        ], '');

        $this->assertSame([], $updates);
    }

    public function test_it_accepts_the_singular_suggested_category_header(): void
    {
        $method = new ReflectionMethod(ItemCsvImporter::class, 'categoryNameFromRow');
        $method->setAccessible(true);

        $category = $method->invoke(new ItemCsvImporter, [
            'suggested category' => 'Engine Oil > Synthetic',
        ]);

        $this->assertSame('Engine Oil', $category);
    }

    public function test_description_updates_preserve_existing_fitment_rows(): void
    {
        $method = new ReflectionMethod(ItemCsvImporter::class, 'preserveExistingFitment');
        $method->setAccessible(true);

        $updatedDetails = $method->invoke(
            new ItemCsvImporter,
            '<p>Updated description</p><table class="pa-fitment-table"><tbody><tr><td>2024</td><td>Honda</td><td>Civic</td></tr></tbody></table>',
            '<p>Old description</p><table class="pa-fitment-table"><tbody><tr><td>2023</td><td>Toyota</td><td>Camry</td></tr></tbody></table>'
        );

        $this->assertStringContainsString('Updated description', $updatedDetails);
        $this->assertStringContainsString('<td>2024</td><td>Honda</td><td>Civic</td>', $updatedDetails);
        $this->assertStringContainsString('<td>2023</td><td>Toyota</td><td>Camry</td>', $updatedDetails);
    }

    private function existingItemUpdates(array $row, string $title): array
    {
        $method = new ReflectionMethod(ItemCsvImporter::class, 'existingItemScalarUpdates');
        $method->setAccessible(true);

        return $method->invoke(new ItemCsvImporter, $row, $title);
    }
}
