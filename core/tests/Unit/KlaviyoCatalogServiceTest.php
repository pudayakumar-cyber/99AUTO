<?php

namespace Tests\Unit;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Gallery;
use App\Models\Item;
use App\Services\KlaviyoCatalogService;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Contracts\Routing\UrlGenerator;
use Illuminate\Database\Eloquent\Collection;
use PHPUnit\Framework\TestCase;

class KlaviyoCatalogServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $app = new Container;
        Container::setInstance($app);
        $app->instance('config', new Repository([
            'services' => ['klaviyo' => [
                'catalog_feed_token' => 'secret-token',
                'catalog_currency' => 'CAD',
            ]],
        ]));
        $url = new class {
            public function to($path): string
            {
                return 'https://99autoparts.ca/'.ltrim($path, '/');
            }
        };
        $app->instance('url', $url);
        $app->instance(UrlGenerator::class, $url);
    }

    protected function tearDown(): void
    {
        Container::setInstance(null);
        parent::tearDown();
    }

    public function test_catalog_feed_requires_the_configured_token(): void
    {
        $catalog = new KlaviyoCatalogService;

        $this->assertTrue($catalog->authorized('secret-token'));
        $this->assertFalse($catalog->authorized('wrong-token'));
        $this->assertFalse($catalog->authorized(null));
    }

    public function test_it_maps_a_product_to_a_stable_klaviyo_catalog_record(): void
    {
        $item = new Item;
        $item->setRawAttributes([
            'id' => 42,
            'name' => 'Ceramic Brake Pads',
            'slug' => 'ceramic-brake-pads',
            'sku' => 'PAD-42',
            'sort_details' => '<p>Premium pads</p>',
            'photo' => null,
            'thumbnail' => null,
            'discount_price' => 55.25,
            'previous_price' => 64.00,
            'stock' => 7,
            'status' => 1,
            'item_type' => 'normal',
        ]);
        $item->setRelation('brand', new Brand(['name' => 'ACME']));
        $item->setRelation('category', new Category(['name' => 'Brakes']));
        $item->setRelation('subcategory', null);
        $item->setRelation('childcategory', null);
        $item->setRelation('galleries', new Collection([
            new Gallery(['photo' => 'pad.jpg']),
        ]));

        $record = (new KlaviyoCatalogService)->map($item);

        $this->assertSame('42', $record['id']);
        $this->assertSame('ACME - PAD-42 - Ceramic Brake Pads', $record['title']);
        $this->assertSame('https://99autoparts.ca/product/ceramic-brake-pads?item_id=42', $record['link']);
        $this->assertSame('https://99autoparts.ca/core/public/storage/images/pad.jpg', $record['image_link']);
        $this->assertSame(55.25, $record['price']);
        $this->assertSame(64.0, $record['compare_at_price']);
        $this->assertSame(['Brakes'], $record['categories']);
        $this->assertSame(7, $record['inventory_quantity']);
        $this->assertSame(1, $record['inventory_policy']);
        $this->assertTrue($record['in_stock']);
    }
}
