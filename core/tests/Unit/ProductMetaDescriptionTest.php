<?php

namespace Tests\Unit;

use App\Models\Item;
use App\Support\ProductMetaDescription;
use PHPUnit\Framework\TestCase;

class ProductMetaDescriptionTest extends TestCase
{
    public function test_shared_manufacturer_copy_produces_distinct_product_descriptions(): void
    {
        $copy = 'Premium brake components engineered for dependable stopping performance.';
        $first = new Item;
        $first->setRawAttributes(['name' => 'Front Brake Pads', 'product_part_number' => 'PAD-101', 'meta_description' => $copy]);
        $first->setRelation('brand', null);
        $second = new Item;
        $second->setRawAttributes(['name' => 'Rear Brake Pads', 'product_part_number' => 'PAD-102', 'meta_description' => $copy]);
        $second->setRelation('brand', null);

        $firstDescription = ProductMetaDescription::for($first);
        $secondDescription = ProductMetaDescription::for($second);

        $this->assertNotSame($firstDescription, $secondDescription);
        $this->assertStringStartsWith('PAD-101 - Front Brake Pads. ', $firstDescription);
        $this->assertStringStartsWith('PAD-102 - Rear Brake Pads. ', $secondDescription);
        $this->assertLessThanOrEqual(160, mb_strlen($firstDescription));
    }

    public function test_empty_product_copy_has_a_useful_fallback(): void
    {
        $item = new Item;
        $item->setRawAttributes(['name' => 'Control Arm', 'sku' => 'CA-9']);
        $item->setRelation('brand', null);

        $description = ProductMetaDescription::for($item);

        $this->assertStringContainsString('CA-9 - Control Arm', $description);
        $this->assertStringContainsString('99AutoParts Canada', $description);
    }
}
