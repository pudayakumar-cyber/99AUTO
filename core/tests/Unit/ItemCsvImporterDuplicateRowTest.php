<?php

namespace Tests\Unit;

use App\Services\ItemCsvImporter;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class ItemCsvImporterDuplicateRowTest extends TestCase
{
    public function test_it_skips_only_identical_rows_within_a_chunk(): void
    {
        $method = new ReflectionMethod(ItemCsvImporter::class, 'isRepeatedRow');
        $method->setAccessible(true);
        $importer = new ItemCsvImporter;

        $row = [
            'product part number' => '72-CK80539',
            'product name' => 'Control Arm With Ball Joint',
            'scraped price' => '64.84',
            'stock' => '10',
        ];

        $this->assertFalse($method->invoke($importer, $row));
        $this->assertTrue($method->invoke($importer, $row));
        $this->assertFalse($method->invoke($importer, array_merge($row, ['stock' => '11'])));
        $this->assertFalse($method->invoke($importer, array_merge($row, ['year' => '2025'])));
    }
}
