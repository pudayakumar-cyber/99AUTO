<?php

namespace Tests\Unit;

use App\Models\Brand;
use App\Models\Item;
use PHPUnit\Framework\TestCase;

class ItemDisplayNameTest extends TestCase
{
    public function test_it_prefixes_missing_brand_and_part_number(): void
    {
        $item = new Item([
            'name' => 'Front Gas Charged Strut',
            'product_part_number' => '234054',
        ]);
        $item->setRelation('brand', new Brand(['name' => 'KYB']));

        $this->assertSame('KYB - 234054 - Front Gas Charged Strut', $item->display_name);
    }

    public function test_it_does_not_duplicate_brand_or_part_number_already_in_name(): void
    {
        $item = new Item([
            'name' => '5W20 Special-Tec 5L - Liqui Moly Synthetic Engine Oil 2264',
            'product_part_number' => '2264',
        ]);
        $item->setRelation('brand', new Brand(['name' => 'LIQUI MOLY']));

        $this->assertSame(
            '5W20 Special-Tec 5L - Liqui Moly Synthetic Engine Oil 2264',
            $item->display_name
        );
    }
}
