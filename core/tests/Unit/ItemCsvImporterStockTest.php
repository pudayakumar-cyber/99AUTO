<?php

namespace Tests\Unit;

use App\Services\ItemCsvImporter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class ItemCsvImporterStockTest extends TestCase
{
    #[DataProvider('stockValues')]
    public function test_it_parses_supported_stock_columns(array $row, ?int $expected): void
    {
        $method = new ReflectionMethod(ItemCsvImporter::class, 'stockFromRow');
        $method->setAccessible(true);

        $actual = $method->invoke(new ItemCsvImporter, $row);

        $this->assertSame($expected, $actual);
    }

    public static function stockValues(): array
    {
        return [
            'stock' => [['stock' => '25'], 25],
            'zero stock' => [['stock' => '0'], 0],
            'inventory alias' => [['inventory' => '1,250'], 1250],
            'quantity alias' => [['quantity' => '7.9'], 7],
            'negative stock is clamped' => [['stock quantity' => '-4'], 0],
            'blank stock is ignored' => [['stock' => ''], null],
            'invalid stock is ignored' => [['stock' => 'unknown'], null],
        ];
    }
}
