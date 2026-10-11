<?php

namespace Tests\Unit;

use App\Models\Item;
use App\Support\ProductUrl;
use Illuminate\Container\Container;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Routing\RouteCollection;
use Illuminate\Routing\UrlGenerator;
use PHPUnit\Framework\TestCase;

class ProductUrlTest extends TestCase
{
    protected function tearDown(): void
    {
        Container::setInstance(null);
        parent::tearDown();
    }

    public function test_products_sharing_a_slug_get_distinct_public_urls(): void
    {
        $routes = new RouteCollection;
        $routes->add((new Route('GET', 'product/{slug}', fn () => null))->name('front.product'));
        $generator = new UrlGenerator($routes, Request::create('https://99autoparts.ca/'));
        $app = new Container;
        $app->instance('url', $generator);
        Container::setInstance($app);

        $first = new Item;
        $first->setRawAttributes(['id' => 31, 'slug' => 'brake-pads']);
        $second = new Item;
        $second->setRawAttributes(['id' => 32, 'slug' => 'brake-pads']);

        $this->assertSame('https://99autoparts.ca/product/brake-pads?item_id=31', ProductUrl::for($first));
        $this->assertSame('https://99autoparts.ca/product/brake-pads?item_id=32', ProductUrl::for($second));
        $this->assertSame('https://99autoparts.ca/product/brake-pads?item_id=32', ProductUrl::forSlugAndId('brake-pads', 32));
    }
}
