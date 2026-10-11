<?php

namespace Tests\Unit;

use App\Models\CartRecoveryLink;
use App\Services\CartRecoveryService;
use Carbon\Carbon;
use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\Facade;
use Mockery;
use PHPUnit\Framework\TestCase;

class CartRecoveryServiceTest extends TestCase
{
    private Capsule $database;
    private CartRecoveryService $service;

    protected function setUp(): void
    {
        parent::setUp();
        if (! extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('Run with -d extension=php_pdo_sqlite.dll for isolated SQLite tests.');
        }
        $this->database = new Capsule;
        $this->database->addConnection(['driver' => 'sqlite', 'database' => ':memory:']);
        $this->database->setAsGlobal();
        $this->database->bootEloquent();
        $app = $this->database->getContainer();
        $configuration = new \Illuminate\Config\Repository;
        foreach ($app['config']->getAttributes() as $key => $value) {
            $configuration->set($key, $value);
        }
        $app->instance('config', $configuration);
        Container::setInstance($app);
        $app->instance('db', $this->database->getDatabaseManager());
        $app->bind('db.schema', fn () => $this->database->schema());
        $app['config']->set('app.url', 'https://99autoparts.ca/core');
        $app['config']->set('services.klaviyo.enabled', true);
        $logger = Mockery::mock();
        $logger->shouldReceive('warning')->zeroOrMoreTimes();
        $app->instance('log', $logger);
        Facade::setFacadeApplication($app);
        Carbon::setTestNow('2026-09-15 12:00:00');
        (require __DIR__.'/../../database/migrations/2026_09_15_000000_create_cart_recovery_links_table.php')->up();
        $this->database->schema()->create('items', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug');
            $table->string('photo')->nullable();
            $table->string('item_type')->default('normal');
            $table->integer('status')->default(1);
            $table->integer('stock')->default(10);
            $table->decimal('discount_price')->default(25);
        });
        $this->database->schema()->create('attributes', function (Blueprint $table) {
            $table->id(); $table->integer('item_id'); $table->string('name');
        });
        $this->database->schema()->create('attribute_options', function (Blueprint $table) {
            $table->id(); $table->integer('attribute_id'); $table->string('name');
            $table->decimal('price')->default(5); $table->integer('stock')->default(10);
        });
        $this->database->table('items')->insert([
            ['id' => 1, 'name' => 'Oil Filter', 'slug' => 'oil-filter', 'photo' => 'filter.jpg'],
            ['id' => 2, 'name' => 'Brake Pads', 'slug' => 'brake-pads', 'photo' => 'pads.jpg'],
        ]);
        $this->service = new CartRecoveryService;
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication(null);
        Container::setInstance(null);
        if (isset($this->database)) {
            $this->database->getDatabaseManager()->disconnect();
        }
        Mockery::close();
        parent::tearDown();
    }

    private function cart(): array
    {
        return ['1-' => ['qty' => 2, 'options_id' => [], 'name' => 'Oil Filter', 'main_price' => 12,
            'item_type' => 'normal', 'email' => 'private@example.com', 'billing_address' => 'PRIVATE', 'item_l_k' => 'SECRET']];
    }

    private function token(array $created): string
    {
        return basename(parse_url($created['url'], PHP_URL_PATH));
    }

    public function test_snapshot_excludes_personal_information_prices_and_license_data(): void
    {
        $this->assertSame([['id' => 1, 'qty' => 2, 'options' => []]], $this->service->snapshot($this->cart()));
    }

    public function test_link_uses_public_root_and_database_only_stores_token_hash(): void
    {
        $created = $this->service->create($this->service->snapshot($this->cart()));
        $token = $this->token($created);
        $this->assertStringStartsWith('https://99autoparts.ca/cart/recover/', $created['url']);
        $stored = CartRecoveryLink::first();
        $this->assertSame(hash('sha256', $token), $stored->token_hash);
        $this->assertStringNotContainsString($token, $stored->toJson());
        $this->assertNotNull($this->service->find($token));
    }

    public function test_rejects_unknown_malformed_and_expired_links(): void
    {
        $created = $this->service->create($this->service->snapshot($this->cart()));
        $this->assertNull($this->service->find(str_repeat('a', 64)));
        $this->assertNull($this->service->find('../bad'));
        Carbon::setTestNow('2026-09-22 12:00:00');
        $this->assertNull($this->service->find($this->token($created)));
    }

    public function test_restore_reloads_current_price_not_the_saved_cart_price(): void
    {
        $result = $this->service->restore($this->service->snapshot($this->cart()));
        $this->assertSame(25.0, $result['cart']['1-']['main_price']);
        $this->assertSame(2, $result['cart']['1-']['qty']);
        $this->assertSame(0, $result['skipped']);
        $this->assertArrayNotHasKey('email', $result['cart']['1-']);
    }

    public function test_skips_deleted_disabled_and_insufficient_stock_items(): void
    {
        $this->database->table('items')->where('id', 1)->update(['status' => 0]);
        $this->database->table('items')->where('id', 2)->update(['stock' => 1]);
        $result = $this->service->restore([
            ['id' => 1, 'qty' => 2, 'options' => []], ['id' => 2, 'qty' => 2, 'options' => []],
            ['id' => 99, 'qty' => 1, 'options' => []],
        ]);
        $this->assertSame([], $result['cart']);
        $this->assertSame(3, $result['skipped']);
    }

    public function test_option_must_belong_to_the_product_and_remain_in_stock(): void
    {
        $this->database->table('attributes')->insert(['id' => 1, 'item_id' => 2, 'name' => 'Size']);
        $this->database->table('attribute_options')->insert(['id' => 7, 'attribute_id' => 1, 'name' => 'Large', 'stock' => 1, 'price' => 9]);
        $result = $this->service->restore([
            ['id' => 1, 'qty' => 1, 'options' => [7]], ['id' => 2, 'qty' => 2, 'options' => [7]],
            ['id' => 2, 'qty' => 1, 'options' => []],
        ]);
        $this->assertSame([], $result['cart']);
        $this->assertSame(3, $result['skipped']);
        $valid = $this->service->restore([['id' => 2, 'qty' => 1, 'options' => [7]]]);
        $this->assertSame(9.0, $valid['cart']['2-Large']['attribute_price']);
        $this->assertSame([7], $valid['cart']['2-Large']['options_id']);
    }

    public function test_stock_is_shared_across_lines_for_the_same_product(): void
    {
        $result = $this->service->restore([
            ['id' => 1, 'qty' => 6, 'options' => []], ['id' => 1, 'qty' => 6, 'options' => []],
        ]);
        $this->assertSame(6, $result['cart']['1-']['qty']);
        $this->assertSame(1, $result['skipped']);
    }

    public function test_repeated_clicks_rebuild_identical_quantities_instead_of_incrementing(): void
    {
        $items = $this->service->snapshot($this->cart());
        $this->assertSame($this->service->restore($items), $this->service->restore($items));
    }

    public function test_event_contains_all_cart_items_and_reuses_unchanged_snapshot(): void
    {
        $session = new Store('test', new ArraySessionHandler(120));
        $cart = $this->cart();
        $cart['2-'] = ['qty' => 1, 'options_id' => [], 'name' => 'Brake Pads', 'main_price' => 20, 'item_type' => 'normal'];
        $session->put('cart', $cart);
        $first = $this->service->eventProperties($session);
        $second = $this->service->eventProperties($session);
        $this->assertTrue($first['CartRecoveryAvailable']);
        $this->assertCount(2, $first['Items']);
        $this->assertSame(44.0, $first['$value']);
        $this->assertSame($first['CheckoutURL'], $second['CheckoutURL']);
        $this->assertSame(1, CartRecoveryLink::count());
        $cart['1-']['qty'] = 3;
        $session->put('cart', $cart);
        $changed = $this->service->eventProperties($session);
        $this->assertNotSame($first['CheckoutURL'], $changed['CheckoutURL']);
        $this->assertSame(2, CartRecoveryLink::count());
    }

    public function test_missing_migration_does_not_break_checkout_and_disabled_tracking_does_not_write(): void
    {
        $session = new Store('test', new ArraySessionHandler(120));
        $session->put('cart', $this->cart());
        config(['services.klaviyo.enabled' => false]);
        $this->assertFalse($this->service->eventProperties($session)['CartRecoveryAvailable']);
        $this->assertSame(0, CartRecoveryLink::count());
        $this->database->schema()->drop('cart_recovery_links');
        config(['services.klaviyo.enabled' => true]);
        $this->assertFalse($this->service->eventProperties($session)['CartRecoveryAvailable']);
        $this->assertSame($this->cart(), $session->get('cart'));
    }

    public function test_migration_rolls_back_its_own_table_only(): void
    {
        (require __DIR__.'/../../database/migrations/2026_09_15_000000_create_cart_recovery_links_table.php')->down();
        $this->assertFalse($this->database->schema()->hasTable('cart_recovery_links'));
        $this->assertTrue($this->database->schema()->hasTable('items'));
    }

    private function httpSession(): Store
    {
        $session = new Store('test', new ArraySessionHandler(120));
        $request = \Illuminate\Http\Request::create('https://99autoparts.ca/');
        $urls = new \Illuminate\Routing\UrlGenerator(new \Illuminate\Routing\RouteCollection, $request);
        $redirect = new \Illuminate\Routing\Redirector($urls);
        $redirect->setSession($session);
        $views = Mockery::mock(\Illuminate\Contracts\View\Factory::class);
        $view = Mockery::mock(\Illuminate\Contracts\View\View::class);
        $view->shouldReceive('render')->andReturn('confirmation');
        $views->shouldReceive('make')->andReturn($view);
        app()->instance('redirect', $redirect);
        app()->instance(\Illuminate\Contracts\Routing\ResponseFactory::class,
            new \Illuminate\Routing\ResponseFactory($views, $redirect));

        return $session;
    }

    public function test_get_does_not_change_cart_or_consume_link_and_has_private_headers(): void
    {
        $session = $this->httpSession();
        $session->put('cart', ['existing' => 'keep']);
        $token = $this->token($this->service->create($this->service->snapshot($this->cart())));
        $controller = new \App\Http\Controllers\Front\CartRecoveryController;
        $response = $controller->show($token, $this->service);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertSame('no-referrer', $response->headers->get('Referrer-Policy'));
        $this->assertSame(['existing' => 'keep'], $session->get('cart'));
        $this->assertNotNull($this->service->find($token));
    }

    public function test_confirmation_replaces_cart_and_clears_old_payment_and_discount_state(): void
    {
        $session = $this->httpSession();
        $session->put('cart', ['existing' => 'replace']);
        foreach (['coupon', 'order_data', 'order_input_data', 'order_payment_id', 'stripe_payment_intent_id'] as $key) {
            $session->put($key, 'old');
        }
        $session->put('auth_marker', 'preserve');
        $request = \Illuminate\Http\Request::create('https://99autoparts.ca/cart/recover/test', 'POST');
        $request->setLaravelSession($session);
        $token = $this->token($this->service->create($this->service->snapshot($this->cart())));
        $response = (new \App\Http\Controllers\Front\CartRecoveryController)->restore($request, $token, $this->service);
        $this->assertSame('https://99autoparts.ca/cart', $response->getTargetUrl());
        $this->assertSame(2, $session->get('cart')['1-']['qty']);
        foreach (['coupon', 'order_data', 'order_input_data', 'order_payment_id', 'stripe_payment_intent_id'] as $key) {
            $this->assertFalse($session->has($key));
        }
        $this->assertSame('preserve', $session->get('auth_marker'));
    }

    public function test_unavailable_saved_cart_preserves_current_cart_and_invalid_link_returns_gone(): void
    {
        $session = $this->httpSession();
        $session->put('cart', ['existing' => 'keep']);
        $request = \Illuminate\Http\Request::create('https://99autoparts.ca/', 'POST');
        $request->setLaravelSession($session);
        $token = $this->token($this->service->create($this->service->snapshot($this->cart())));
        $this->database->table('items')->where('id', 1)->delete();
        $controller = new \App\Http\Controllers\Front\CartRecoveryController;
        $controller->restore($request, $token, $this->service);
        $this->assertSame(['existing' => 'keep'], $session->get('cart'));
        $this->assertSame(410, $controller->restore($request, str_repeat('b', 64), $this->service)->getStatusCode());
    }

    public function test_recovery_post_requires_the_current_sessions_csrf_token(): void
    {
        $application = Mockery::mock(\Illuminate\Foundation\Application::class);
        $application->shouldReceive('runningInConsole')->andReturnFalse();
        $middleware = new class($application, new \Illuminate\Encryption\Encrypter(random_bytes(32), 'AES-256-CBC')) extends \App\Http\Middleware\VerifyCsrfToken {
            protected $addHttpCookie = false;
        };
        $session = new Store('test', new ArraySessionHandler(120));
        $session->put('_token', 'session-specific-token');
        $request = \Illuminate\Http\Request::create('https://99autoparts.ca/cart/recover/'.str_repeat('a', 64), 'POST');
        $request->setLaravelSession($session);
        try {
            $middleware->handle($request, fn () => new \Illuminate\Http\Response('restored'));
            $this->fail('Recovery POST without CSRF must be rejected.');
        } catch (\Illuminate\Session\TokenMismatchException) {
            $this->addToAssertionCount(1);
        }
        $request->request->set('_token', 'session-specific-token');
        $this->assertSame('restored', $middleware->handle($request, fn () => new \Illuminate\Http\Response('restored'))->getContent());
    }

    public function test_snapshot_rejects_negative_quantity_and_non_physical_items(): void
    {
        foreach ([['qty' => -2, 'type' => 'normal'], ['qty' => 1, 'type' => 'license']] as $line) {
            try {
                $this->service->snapshot(['1-' => $line]);
                $this->fail('Invalid recovery cart must be rejected.');
            } catch (\InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }
}
