<?php

namespace Tests\Unit;

use App\Models\Brand;
use App\Models\Item;
use App\Support\ProductMetaTitle;
use Tests\TestCase;

class ProductMetaTitleTest extends TestCase
{
    public function test_it_keeps_the_product_phrase_and_part_number_concise(): void
    {
        $item = new Item([
            'name' => 'Control Arm With Ball Joint',
            'product_part_number' => '3ar9bvvm7n',
        ]);
        $item->setRelation('brand', new Brand(['name' => 'MEVOTECH ORIGINAL GRADE']));

        $title = ProductMetaTitle::for($item);

        $this->assertSame(
            'Control Arm With Ball Joint - 3ar9bvvm7n | 99 Auto Parts',
            $title
        );
        $this->assertLessThanOrEqual(60, mb_strlen($title));
    }

    public function test_it_does_not_repeat_an_identifier_already_in_the_name(): void
    {
        $item = new Item([
            'name' => 'Bosch 0986AF0136 Premium Oil Filter',
            'product_part_number' => '0986AF0136',
        ]);
        $item->setRelation('brand', new Brand());

        $title = ProductMetaTitle::for($item);

        $this->assertSame(1, substr_count($title, '0986AF0136'));
        $this->assertLessThanOrEqual(60, mb_strlen($title));
    }

    public function test_it_uses_the_brand_when_no_identifier_exists(): void
    {
        $item = new Item(['name' => 'Premium Cabin Air Filter']);
        $item->setRelation('brand', new Brand(['name' => 'Bosch']));

        $this->assertSame(
            'Premium Cabin Air Filter - Bosch | 99 Auto Parts',
            ProductMetaTitle::for($item)
        );
    }

    public function test_it_caps_long_source_values_at_sixty_characters(): void
    {
        $item = new Item([
            'name' => str_repeat('Heavy Duty Replacement Suspension Component ', 3),
            'product_part_number' => 'EXTREMELY-LONG-PART-NUMBER-123456789',
        ]);
        $item->setRelation('brand', new Brand());

        $this->assertLessThanOrEqual(60, mb_strlen(ProductMetaTitle::for($item)));
    }
}
