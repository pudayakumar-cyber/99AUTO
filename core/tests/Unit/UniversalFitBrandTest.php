<?php

namespace Tests\Unit;

use App\Models\Brand;
use App\Models\Item;
use App\Support\UniversalFitBrand;
use PHPUnit\Framework\TestCase;

class UniversalFitBrandTest extends TestCase
{
    /** @dataProvider universalBrandProvider */
    public function test_it_recognizes_universal_fit_brands(string $brand): void
    {
        $this->assertTrue(UniversalFitBrand::matches($brand));
    }

    public static function universalBrandProvider(): array
    {
        return [
            ['Lucas Oil'],
            ['lucas-oil'],
            ['LIQUI MOLY'],
            ['LiquiMoly'],
        ];
    }

    public function test_it_does_not_mark_vehicle_specific_brands_as_universal(): void
    {
        $this->assertFalse(UniversalFitBrand::matches('Mevotech'));
    }

    public function test_item_uses_its_brand_for_the_universal_fit_rule(): void
    {
        $item = new Item();
        $item->setRelation('brand', new Brand(['name' => 'Lucas Oil']));

        $this->assertTrue($item->isUniversalFit());
    }

    public function test_catalog_and_product_templates_render_the_universal_notice(): void
    {
        $grid = file_get_contents(__DIR__.'/../../resources/views/front/catalog/partials/grid-item.blade.php');
        $list = file_get_contents(__DIR__.'/../../resources/views/front/catalog/partials/list-item.blade.php');
        $product = file_get_contents(__DIR__.'/../../resources/views/front/catalog/product.blade.php');
        $catalogScript = file_get_contents(__DIR__.'/../../../assets/front/js/myscript.js');

        $this->assertStringContainsString('data-universal-fit=', $grid);
        $this->assertStringContainsString('data-universal-fit=', $list);
        $this->assertStringContainsString("Check Vehicle Owner's Manual.", $product);
        $this->assertStringContainsString('Universal Fit', $catalogScript);
        $this->assertStringContainsString('data-universal-fit', $catalogScript);
        $this->assertStringContainsString('text-universal-fit', $catalogScript);
        $this->assertStringContainsString('#c25b00', $product);
    }

    public function test_vehicle_search_includes_universal_fit_items_in_catalog_and_suggestions(): void
    {
        $controller = file_get_contents(__DIR__.'/../../app/Http/Controllers/Front/CatalogController.php');

        $this->assertSame(3, substr_count($controller, 'includeUniversalFitItemIds'));
        $this->assertStringContainsString('UniversalFitBrand::names()', $controller);
    }
}
