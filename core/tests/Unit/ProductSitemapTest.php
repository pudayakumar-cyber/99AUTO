<?php

namespace Tests\Unit;

use App\Services\ProductSitemap;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Routing\RouteCollection;
use Illuminate\Routing\UrlGenerator;
use PHPUnit\Framework\TestCase;

class ProductSitemapTest extends TestCase
{
    private Capsule $database;

    protected function setUp(): void
    {
        parent::setUp();
        if (! extension_loaded('pdo_sqlite') || ! function_exists('simplexml_load_string')) {
            $this->markTestSkipped('PDO SQLite and SimpleXML are required.');
        }
        $this->database = new Capsule;
        $this->database->addConnection(['driver' => 'sqlite', 'database' => ':memory:']);
        $this->database->setAsGlobal();
        $this->database->bootEloquent();

        $routes = new RouteCollection;
        foreach ([
            ['product/{slug}', 'front.product'],
            ['sitemap.xml', 'front.sitemap'],
            ['sitemaps/pages.xml', 'front.sitemap.pages'],
            ['sitemaps/products-{page}.xml', 'front.sitemap.products'],
            ['/', 'front.index'],
            ['catalog', 'front.catalog'],
        ] as [$path, $name]) {
            $routes->add((new Route('GET', $path, fn () => null))->name($name));
        }
        $app = new Container;
        $app->instance('config', new Repository($this->database->getContainer()['config']->getAttributes()));
        $app->instance('url', new UrlGenerator($routes, Request::create('https://99autoparts.ca/')));
        Container::setInstance($app);

        $this->database->schema()->create('items', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->nullable();
            $table->integer('status');
            $table->timestamp('updated_at')->nullable();
        });
        $this->database->table('items')->insert([
            ['id' => 1, 'slug' => 'brake-pads', 'status' => 1, 'updated_at' => '2026-09-01 12:00:00'],
            ['id' => 2, 'slug' => 'brake-pads', 'status' => 1, 'updated_at' => null],
            ['id' => 3, 'slug' => 'hidden', 'status' => 0, 'updated_at' => null],
            ['id' => 4, 'slug' => null, 'status' => 1, 'updated_at' => null],
        ]);
    }

    protected function tearDown(): void
    {
        Container::setInstance(null);
        if (isset($this->database)) {
            $this->database->getDatabaseManager()->disconnect();
        }
        parent::tearDown();
    }

    private function xml(callable $render): \SimpleXMLElement
    {
        ob_start();
        $render();
        $xml = ob_get_clean();
        $this->assertNotFalse(simplexml_load_string($xml));

        return simplexml_load_string($xml);
    }

    public function test_product_sitemap_contains_only_active_canonical_urls(): void
    {
        $sitemap = new ProductSitemap;
        $this->assertSame(2, $sitemap->productCount());
        $xml = $this->xml(fn () => $sitemap->productsPage(1));
        $this->assertCount(2, $xml->url);
        $this->assertSame('https://99autoparts.ca/product/brake-pads?item_id=1', (string) $xml->url[0]->loc);
        $this->assertSame('https://99autoparts.ca/product/brake-pads?item_id=2', (string) $xml->url[1]->loc);
        $this->assertNotSame('', (string) $xml->url[0]->lastmod);
    }

    public function test_index_lists_pages_and_numbered_product_sitemaps(): void
    {
        $sitemap = new class extends ProductSitemap {
            public function productCount(): int { return 10001; }
        };
        $index = $this->xml(fn () => $sitemap->index());
        $this->assertCount(3, $index->sitemap);
        $this->assertSame('https://99autoparts.ca/sitemaps/pages.xml', (string) $index->sitemap[0]->loc);
        $this->assertSame('https://99autoparts.ca/sitemaps/products-2.xml', (string) $index->sitemap[2]->loc);
        $pages = $this->xml(fn () => $sitemap->pages());
        $this->assertSame('https://99autoparts.ca', (string) $pages->url[0]->loc);
        $this->assertSame('https://99autoparts.ca/catalog', (string) $pages->url[1]->loc);
    }
}
