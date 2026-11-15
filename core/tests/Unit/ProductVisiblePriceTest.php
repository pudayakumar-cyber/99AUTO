<?php

namespace Tests\Unit;

use App\Helpers\PriceHelper;
use App\Models\Attribute;
use App\Models\AttributeOption;
use App\Models\Currency;
use App\Models\Item;
use App\Models\Setting;
use App\Support\ProductStructuredData;
use Illuminate\Support\Facades\Session;
use Tests\TestCase;

class ProductVisiblePriceTest extends TestCase
{
    protected function tearDown(): void
    {
        foreach (['defaultCurrency', 'selectedCurrencies', 'setting'] as $property) {
            $reflection = new \ReflectionProperty(PriceHelper::class, $property);
            $reflection->setValue(null, $property === 'selectedCurrencies' ? [] : null);
        }
        parent::tearDown();
    }

    private function configurePrice(int $isDecimal): void
    {
        Session::forget('currency');
        $currency = new Currency(['name' => 'USD', 'sign' => '$', 'value' => 1.25, 'is_default' => 1]);
        $setting = new Setting(['is_decimal' => $isDecimal, 'currency_direction' => 1,
            'decimal_separator' => '.', 'thousand_separator' => ',']);
        foreach (['defaultCurrency' => $currency, 'setting' => $setting] as $property => $value) {
            (new \ReflectionProperty(PriceHelper::class, $property))->setValue(null, $value);
        }
    }

    private function itemWithOption(): Item
    {
        $item = new Item(['name' => 'Oil', 'sku' => '123', 'discount_price' => 79.69,
            'item_type' => 'normal', 'stock' => 3]);
        $attribute = new Attribute(['name' => 'Size']);
        $attribute->setRelation('options', collect([new AttributeOption(['price' => 5.00, 'stock' => '3'])]));
        $item->setRelation('attributes', collect([$attribute]));
        $item->setRelation('brand', new \App\Models\Brand(['name' => 'Lucas Oil']));
        return $item;
    }

    public function test_nonbase_currency_with_option_matches_initial_visible_price(): void
    {
        $this->configurePrice(1);
        $item = $this->itemWithOption();

        $this->assertSame('$105.86', PriceHelper::grandCurrencyPrice($item));
        $schema = ProductStructuredData::for($item, 'https://99autoparts.ca/product/oil', null,
            PriceHelper::grandCurrencyPriceValue($item), PriceHelper::setCurrencyName());
        $this->assertSame('105.86', $schema['offers']['price']);
    }

    public function test_whole_number_display_matches_offer_price(): void
    {
        $this->configurePrice(0);
        $item = $this->itemWithOption();

        $this->assertSame('$106', PriceHelper::grandCurrencyPrice($item));
        $schema = ProductStructuredData::for($item, 'https://99autoparts.ca/product/oil', null,
            PriceHelper::grandCurrencyPriceValue($item), PriceHelper::setCurrencyName());
        $this->assertSame('106.00', $schema['offers']['price']);
    }
}