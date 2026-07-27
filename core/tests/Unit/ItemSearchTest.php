<?php

namespace Tests\Unit;

use App\Models\Item;
use Tests\TestCase;

class ItemSearchTest extends TestCase
{
    public function test_it_searches_product_names_and_model_identifiers(): void
    {
        $query = Item::query()->searchByNameOrModel('ABC-123');

        $sql = $query->toSql();

        foreach (['name', 'sku', 'prod_number', 'product_part_number'] as $column) {
            $this->assertStringContainsString($column, $sql);
        }

        $this->assertSame(
            array_fill(0, 4, '%ABC-123%'),
            $query->getBindings()
        );
    }

    public function test_it_ignores_an_empty_search_term(): void
    {
        $query = Item::query()->searchByNameOrModel('   ');

        $this->assertStringNotContainsString(' where ', strtolower($query->toSql()));
        $this->assertSame([], $query->getBindings());
    }
}
