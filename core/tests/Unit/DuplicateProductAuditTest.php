<?php

namespace Tests\Unit;

use App\Support\DuplicateProductAudit;
use PHPUnit\Framework\TestCase;

class DuplicateProductAuditTest extends TestCase
{
    public function test_identifier_matches_are_high_confidence_and_combined(): void
    {
        $groups = (new DuplicateProductAudit)->inspect([
            $this->item(54494, 8, 'NGK Spark Plug', 'NGK-123', 'ngk 123'),
            $this->item(54532, 8, 'NGK Spark Plug', 'NGK123', 'NGK-123'),
        ]);

        $this->assertCount(1, $groups);
        $this->assertSame('high', $groups[0]['confidence']);
        $this->assertSame([54494, 54532], array_column($groups[0]['items'], 'id'));
        $this->assertContains('product_part_number', $groups[0]['matched_on']);
        $this->assertContains('prod_number', $groups[0]['matched_on']);
        $this->assertContains('name', $groups[0]['matched_on']);
    }

    public function test_same_title_without_shared_identifier_requires_review(): void
    {
        $groups = (new DuplicateProductAudit)->inspect([
            $this->item(1, 4, 'Control Arm', 'CA-100', 'ONE'),
            $this->item(2, 4, '  Control   Arm ', 'CA-200', 'TWO'),
        ]);

        $this->assertCount(1, $groups);
        $this->assertSame('review', $groups[0]['confidence']);
        $this->assertSame(['name'], $groups[0]['matched_on']);
    }

    public function test_same_title_and_identifier_across_brands_are_not_grouped(): void
    {
        $groups = (new DuplicateProductAudit)->inspect([
            $this->item(1, 4, 'Oil Filter', 'OF-10', 'OF10'),
            $this->item(2, 9, 'Oil Filter', 'OF-10', 'OF10'),
        ]);

        $this->assertSame([], $groups);
    }

    public function test_invalid_rows_and_unique_products_are_ignored(): void
    {
        $groups = (new DuplicateProductAudit)->inspect([
            ['id' => null, 'brand_id' => 1, 'name' => 'Invalid'],
            $this->item(10, 1, 'Unique Product', 'UNIQUE-10', 'U10'),
        ]);

        $this->assertSame([], $groups);
    }

    private function item(int $id, int $brandId, string $name, string $partNumber, string $prodNumber): array
    {
        return [
            'id' => $id,
            'brand_id' => $brandId,
            'name' => $name,
            'product_part_number' => $partNumber,
            'prod_number' => $prodNumber,
            'sku' => $partNumber,
            'stock' => 5,
            'status' => 1,
        ];
    }
}
