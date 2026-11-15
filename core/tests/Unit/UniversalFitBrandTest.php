<?php

namespace Tests\Unit;

use App\Models\Brand;
use App\Models\Item;
use App\Support\UniversalFitBrand;
use PHPUnit\Framework\TestCase;

class UniversalFitBrandTest extends TestCase
{
    /** @dataProvider applicationBrandProvider */
    public function test_it_recognizes_brands_that_need_product_specific_checks(string $brand): void
    {
        $this->assertTrue(UniversalFitBrand::matches($brand));
    }

    public static function applicationBrandProvider(): array
    {
        return [
            ['Lucas Oil'],
            ['lucas-oil'],
            ['LIQUI MOLY'],
            ['LiquiMoly'],
        ];
    }

    public function test_it_does_not_apply_brand_checks_to_vehicle_specific_brands(): void
    {
        $this->assertFalse(UniversalFitBrand::matches('Mevotech'));
    }

    public function test_brand_alone_does_not_prove_universal_vehicle_fit(): void
    {
        foreach ([['Lucas Oil', '20127'], ['LIQUI MOLY', '2248']] as [$brand, $sku]) {
            $item = new Item(['sku' => $sku]);
            $item->setRelation('brand', new Brand(['name' => $brand]));

            $this->assertFalse($item->isUniversalFit());
            $this->assertTrue($item->requiresApplicationCheck());
        }
    }

    public function test_nonlisted_brand_does_not_receive_an_application_check_badge(): void
    {
        $item = new Item(['sku' => 'ABC-123']);
        $item->setRelation('brand', new Brand(['name' => 'Mevotech']));

        $this->assertFalse($item->requiresApplicationCheck());
    }

    public function test_catalog_and_product_templates_render_the_universal_notice(): void
    {
        $grid = file_get_contents(__DIR__.'/../../resources/views/front/catalog/partials/grid-item.blade.php');
        $list = file_get_contents(__DIR__.'/../../resources/views/front/catalog/partials/list-item.blade.php');
        $product = file_get_contents(__DIR__.'/../../resources/views/front/catalog/product.blade.php');
        $catalogScript = file_get_contents(__DIR__.'/../../../assets/front/js/myscript.js');

        $this->assertStringContainsString('data-universal-fit=', $grid);
        $this->assertStringContainsString('data-universal-fit=', $list);
        $this->assertStringContainsString('requiresApplicationCheck()', $grid);
        $this->assertStringContainsString('requiresApplicationCheck()', $list);
        $this->assertStringContainsString('Check application specifications', $grid);
        $this->assertStringContainsString('Check application specifications', $list);
        $this->assertStringContainsString("Check Vehicle Owner's Manual.", $product);
        $this->assertStringContainsString('Check application specifications', $product);
        $this->assertStringContainsString('Universal Fit', $catalogScript);
        $this->assertStringContainsString('data-universal-fit', $catalogScript);
        $this->assertStringContainsString('text-universal-fit', $catalogScript);
        $this->assertStringContainsString('#c25b00', $product);
    }

    public function test_vehicle_search_includes_only_product_verified_universal_items(): void
    {
        $controller = file_get_contents(__DIR__.'/../../app/Http/Controllers/Front/CatalogController.php');

        $this->assertSame(3, substr_count($controller, 'includeUniversalFitItemIds'));
        $this->assertStringContainsString('UniversalFitBrand::matches($name)', $controller);
        $this->assertStringContainsString('UniversalFitBrand::verifiedSkus()', $controller);
        $this->assertStringContainsString("whereIn(DB::raw('TRIM(sku)'), \$verifiedSkus)", $controller);
        $this->assertStringContainsString('UniversalFitBrand::isVerifiedProduct', $controller);
    }
}
