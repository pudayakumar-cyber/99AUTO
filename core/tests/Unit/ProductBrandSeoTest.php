<?php

namespace Tests\Unit;

use App\Models\Attribute;
use App\Models\AttributeOption;
use App\Models\Brand;
use App\Models\Item;
use App\Support\ProductMetaDescription;
use App\Support\ProductMetaTitle;
use App\Support\ProductStructuredData;
use PHPUnit\Framework\TestCase;

class ProductBrandSeoTest extends TestCase
{
    private function product(string $name, string $brand, string $sku): Item
    {
        $item = new Item;
        $item->setRawAttributes([
            'id' => 106193,
            'name' => $name,
            'sku' => $sku,
            'slug' => 'sample-product',
            'item_type' => 'normal',
            'stock' => 3,
            'discount_price' => 79.69,
        ]);
        $item->setRelation('brand', new Brand(['name' => $brand]));
        $item->setRelation('attributes', collect());

        return $item;
    }

    public function test_lucas_title_preserves_full_part_type_grade_and_size_without_double_separator(): void
    {
        $item = $this->product('LUCAS OIL PRODUCTS INC. - 20127 - 5W-20 Synthetic Motor Oil-5L', 'Lucas Oil', '20127');

        $title = ProductMetaTitle::for($item);

        $this->assertSame('LUCAS OIL PRODUCTS INC. 20127 5W-20 Synthetic Motor Oil-5L | 99 Auto Parts', $title);
    }

    public function test_liqui_moly_title_keeps_size_and_product_type(): void
    {
        $item = $this->product('Liqui Moly 20548 Marine 4T SAE 25W-40 Motor Oil 4 L', 'LIQUI MOLY', '20548');

        $this->assertSame('Liqui Moly 20548 Marine 4T SAE 25W-40 Motor Oil 4 L | 99 Auto Parts', ProductMetaTitle::for($item));
    }

    public function test_liqui_moly_title_uses_brand_and_real_sku_when_name_omits_them(): void
    {
        $item = $this->product('Marine 4T Motor Oil 4 L', 'LIQUI MOLY', '20548');

        $this->assertSame('LIQUI MOLY 20548 Marine 4T Motor Oil 4 L | 99 Auto Parts', ProductMetaTitle::for($item));
    }

    public function test_brand_specific_meta_skips_generic_placeholder_in_favour_of_real_short_copy(): void
    {
        $item = $this->product('Liqui Moly 20548 Marine 4T SAE 25W-40 Motor Oil 4 L', 'LIQUI MOLY', '20548');
        $item->meta_description = 'Shop online at 99AutoParts Canada with fast shipping across Canada.';
        $item->sort_details = 'One 4 L canister of LIQUI MOLY Marine 4T SAE 25W-40.';

        $description = ProductMetaDescription::for($item);

        $this->assertStringContainsString('4 L canister', $description);
        $this->assertStringNotContainsString('fast shipping', $description);
    }

    public function test_lucas_missing_copy_does_not_reintroduce_generic_shipping_copy(): void
    {
        $item = $this->product('LUCAS OIL PRODUCTS INC. - 20127 - 5W-20 Synthetic Motor Oil-5L', 'Lucas Oil', '20127');

        $description = ProductMetaDescription::for($item);

        $this->assertStringContainsString('20127', $description);
        $this->assertStringContainsString('5L', $description);
        $this->assertStringNotContainsString('fast shipping', $description);
    }

    public function test_product_schema_matches_visible_name_sku_image_price_currency_and_stock(): void
    {
        $item = $this->product('LUCAS OIL PRODUCTS INC. - 20127 - 5W-20 Synthetic Motor Oil-5L', 'Lucas Oil', '20127');
        $url = 'https://99autoparts.ca/product/sample-product?item_id=106193';
        $image = 'https://99autoparts.ca/core/public/storage/images/lucas.jpg';

        $schema = ProductStructuredData::for($item, $url, $image, 79.69, 'CAD');

        $this->assertSame('Product', $schema['@type']);
        $this->assertSame($item->display_name, $schema['name']);
        $this->assertSame($url, $schema['url']);
        $this->assertSame($image, $schema['image']);
        $this->assertSame('20127', $schema['sku']);
        $this->assertSame('Lucas Oil', $schema['brand']['name']);
        $this->assertSame('79.69', $schema['offers']['price']);
        $this->assertSame('CAD', $schema['offers']['priceCurrency']);
        $this->assertSame('https://schema.org/InStock', $schema['offers']['availability']);
        $this->assertSame($url, $schema['offers']['url']);
        foreach (['gtin', 'aggregateRating', 'review', 'mpn'] as $unsupported) {
            $this->assertArrayNotHasKey($unsupported, $schema);
        }
    }

    public function test_schema_omits_unknown_sku_image_and_unverified_currency(): void
    {
        $item = $this->product('Liqui Moly Motor Oil', 'LIQUI MOLY', '');
        $item->stock = 0;
        $schema = ProductStructuredData::for($item, 'https://99autoparts.ca/product/sample-product?item_id=106193', null, 40.0, 'dollar');

        $this->assertArrayNotHasKey('sku', $schema);
        $this->assertArrayNotHasKey('image', $schema);
        $this->assertArrayNotHasKey('offers', $schema);
        $this->assertSame('Liqui Moly Motor Oil', $schema['name']);
    }

    public function test_schema_is_not_generated_for_an_unrelated_brand(): void
    {
        $item = $this->product('Other oil', 'Other Brand', '123');

        $this->assertFalse(ProductStructuredData::supports($item));
        $this->assertSame([], ProductStructuredData::for($item, 'https://99autoparts.ca/product/other-oil', null, 40.0, 'CAD'));
    }

    public function test_affiliate_and_digital_items_have_no_store_offer(): void
    {
        foreach (['affiliate', 'digital', 'license'] as $type) {
            $item = $this->product('Liqui Moly Motor Oil', 'LIQUI MOLY', '20548');
            $item->item_type = $type;
            if ($type === 'affiliate') {
                $this->assertTrue($item->is_stock());
            }
            $schema = ProductStructuredData::for($item, 'https://99autoparts.ca/product/sample-product', null, 40.0, 'CAD');

            $this->assertArrayNotHasKey('offers', $schema, $type);
        }
    }

    public function test_product_template_emits_schema_with_the_visible_price_and_identity_url(): void
    {
        $template = file_get_contents(dirname(__DIR__, 2).'/resources/views/front/catalog/product.blade.php');
        $meta = explode("@section('styleplugins')", explode("@section('meta')", $template)[1])[0];

        $this->assertStringContainsString('ProductStructuredData::for(', $meta);
        $this->assertStringContainsString('ProductUrl::for($item)', $meta);
        $this->assertStringContainsString('ProductStructuredData::supports($item)', $meta);
        $this->assertStringContainsString('PriceHelper::grandCurrencyPriceValue($item)', $meta);
        $this->assertStringNotContainsString('PriceHelper::grandPrice($item)', $meta);
        $this->assertStringContainsString('PriceHelper::setCurrencyName()', $meta);
        $this->assertStringContainsString('application/ld+json', $meta);
    }

    public function test_offer_is_omitted_when_first_option_is_unselectable_despite_a_later_sellable_option(): void
    {
        $item = $this->product('Lucas Oil', 'Lucas Oil', '123');
        $item->discount_price = 100;
        $attribute = new Attribute(['name' => 'Size']);
        $attribute->setRelation('options', collect([
            new AttributeOption(['price' => 0, 'stock' => '0']),
            new AttributeOption(['price' => 10, 'stock' => '3']),
        ]));
        $item->setRelation('attributes', collect([$attribute]));

        // The main price uses the raw first option ($100), but the select only
        // renders the second option ($110). Neither is a truthful initial Offer.
        $schema = ProductStructuredData::for($item, 'https://99autoparts.ca/product/oil', null, 100.0, 'CAD');
        $this->assertSame('Product', $schema['@type']);
        $this->assertArrayNotHasKey('offers', $schema);
    }

    public function test_offer_is_omitted_when_an_attribute_has_no_selectable_option(): void
    {
        $item = $this->product('Liqui Moly Motor Oil', 'LIQUI MOLY', '20548');
        $attribute = new Attribute(['name' => 'Size']);
        $attribute->setRelation('options', collect());
        $item->setRelation('attributes', collect([$attribute]));

        $schema = ProductStructuredData::for($item, 'https://99autoparts.ca/product/oil', null, 79.69, 'CAD');
        $this->assertSame('Product', $schema['@type']);
        $this->assertArrayNotHasKey('offers', $schema);
    }

    public function test_schema_reports_out_of_stock_for_zero_stock(): void
    {
        $item = $this->product('Liqui Moly Motor Oil', 'LIQUI MOLY', '20548');
        $item->stock = 0;
        $schema = ProductStructuredData::for($item, 'https://99autoparts.ca/product/sample-product?item_id=106193', null, 40.0, 'CAD');

        $this->assertSame('https://schema.org/OutOfStock', $schema['offers']['availability']);
    }
}
