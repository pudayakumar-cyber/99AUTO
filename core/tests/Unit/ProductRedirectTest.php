<?php

namespace Tests\Unit;

use App\Http\Controllers\Front\FrontendController;
use Carbon\Carbon;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;
use Illuminate\Routing\Route;
use Illuminate\Routing\RouteCollection;
use Illuminate\Routing\UrlGenerator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ProductRedirectTest extends TestCase
{
    private Capsule $database;
    private Container $app;

    protected function setUp(): void
    {
        parent::setUp();
        if (! extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('PDO SQLite is required for isolated redirect tests.');
        }
        $this->database = new Capsule;
        $this->database->addConnection(['driver' => 'sqlite', 'database' => ':memory:']);
        $this->database->setAsGlobal();
        $this->database->bootEloquent();
        $this->app = new class extends Container {
            public function abort(int $code): never
            {
                throw new HttpException($code);
            }
        };
        $this->app->instance('config', new Repository($this->database->getContainer()['config']->getAttributes()));
        $this->app->instance('db', $this->database->getDatabaseManager());
        Container::setInstance($this->app);

        $this->database->schema()->create('items', function (Blueprint $table) {
            $table->id();
            $table->string('slug');
            $table->integer('status');
        });
        $this->database->schema()->create('reviews', function (Blueprint $table) {
            $table->id();
            $table->integer('item_id');
            $table->integer('status');
            $table->decimal('rating');
        });
        $this->database->table('items')->insert([
            ['id' => 31, 'slug' => 'new-brake-pads', 'status' => 1],
            ['id' => 32, 'slug' => 'hidden-part', 'status' => 0],
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

    private function request(string $uri): void
    {
        $request = Request::create($uri);
        $routes = new RouteCollection;
        $routes->add((new Route('GET', 'product/{slug}', fn () => null))->name('front.product'));
        $url = new UrlGenerator($routes, $request);
        $this->app->instance('request', $request);
        $this->app->instance('url', $url);
        $this->app->instance('redirect', new Redirector($url));
    }

    public function test_stale_slug_with_valid_product_id_redirects_to_current_url(): void
    {
        $this->request('https://99autoparts.ca/product/old-brake-pads?item_id=31');
        $controller = (new \ReflectionClass(FrontendController::class))->newInstanceWithoutConstructor();
        $response = $controller->product('old-brake-pads');
        $this->assertSame(301, $response->getStatusCode());
        $this->assertSame('https://99autoparts.ca/product/new-brake-pads?item_id=31', $response->getTargetUrl());
    }

    public function test_unknown_inactive_and_malformed_product_ids_stay_not_found(): void
    {
        foreach (['item_id=999', 'item_id=32', 'item_id[]=31', 'item_id=1.5'] as $query) {
            $this->request('https://99autoparts.ca/product/old-brake-pads?'.$query);
            try {
                $controller = (new \ReflectionClass(FrontendController::class))->newInstanceWithoutConstructor();
                $controller->product('old-brake-pads');
                $this->fail('Expected a 404 for '.$query);
            } catch (HttpException $exception) {
                $this->assertSame(404, $exception->getStatusCode());
            }
        }
    }
}
