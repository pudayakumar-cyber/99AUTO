<?php

namespace Tests\Unit;

use App\Http\Controllers\Back\ProductMetadataController;
use App\Http\Middleware\Permission;
use App\Http\Middleware\Demo;
use App\Models\Brand;
use App\Models\Admin;
use App\Models\Item;
use App\Support\ProductMetaDescription;
use App\Support\ProductMetaTitle;
use Illuminate\Container\Container;
use Illuminate\Auth\AuthManager;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Events\Dispatcher;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;
use Illuminate\Routing\Router;
use Illuminate\Routing\ImplicitRouteBinding;
use Illuminate\Routing\UrlGenerator;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Config\Repository as ConfigRepository;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Illuminate\View\Compilers\BladeCompiler;
use PHPUnit\Framework\TestCase;

class ProductMetadataAdminTest extends TestCase
{
    private Router $router;
    private Capsule $database;

    protected function setUp(): void
    {
        parent::setUp();
        $app = new Container();
        Container::setInstance($app);
        Facade::setFacadeApplication($app);
        if (! class_exists('Auth', false)) {
            class_alias(Auth::class, 'Auth');
        }
        $app->instance('auth', new AuthManager($app));
        $this->router = new Router(new Dispatcher($app), $app);
        $app->instance('router', $this->router);
        Route::middleware('web')->namespace('App\\Http\\Controllers')->group(dirname(__DIR__, 2).'/routes/web.php');
        $this->router->getRoutes()->refreshNameLookups();
        $request = Request::create('/admin/item/17/metadata', 'POST');
        $app->instance('request', $request);
        $session = new Store('metadata_test', new ArraySessionHandler(60));
        $session->start();
        $app->instance('session', $session);
        $request->setLaravelSession($session);
        $app->instance('translator', new Translator(new ArrayLoader(), 'en'));
        $url = new UrlGenerator($this->router->getRoutes(), $request);
        $app->instance('url', $url);
        $app->instance('redirect', new Redirector($url));
        $app->make('redirect')->setSession($session);
        $app->instance('validator', new Factory(new Translator(new ArrayLoader(), 'en'), $app));

        $this->database = new Capsule($app);
        $this->database->addConnection(['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
        $this->database->setAsGlobal();
        $this->database->bootEloquent();
        $app->instance('db', $this->database->getDatabaseManager());
        $this->database->schema()->create('items', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->string('sku');
            $table->decimal('discount_price', 10, 2);
            $table->integer('stock');
            $table->string('photo');
            $table->string('item_type')->nullable();
            $table->string('seo_title', 255)->nullable();
            $table->text('seo_meta_description')->nullable();
        });
        $this->database->table('items')->insert([
            'id' => 17, 'name' => 'Original product', 'sku' => 'PN-17',
            'discount_price' => 39.95, 'stock' => 7, 'photo' => 'original.jpg',
        ]);
    }

    protected function tearDown(): void
    {
        \Mockery::close();
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication(null);
        parent::tearDown();
    }

    public function test_metadata_routes_are_item_bound_and_within_admin_product_permissions(): void
    {
        foreach (['GET', 'POST'] as $method) {
            $route = $this->router->getRoutes()->match(Request::create('/admin/item/17/metadata', $method));
            $this->assertSame('17', $route->parameter('item'));
            $this->assertContains('web', $route->gatherMiddleware());
            $this->assertContains('adminlocalize', $route->gatherMiddleware());
            $this->assertContains('demo', $route->gatherMiddleware());
            $this->assertContains('permissions:Manage Products', $route->gatherMiddleware());
            $this->assertStringContainsString('ProductMetadataController@', $route->getActionName());
        }

        $this->assertContains('auth:admin', array_column((new ProductMetadataController())->getMiddleware(), 'middleware'));
    }

    public function test_patch_cannot_bypass_demo_mode_on_metadata_endpoint(): void
    {
        $this->expectException(MethodNotAllowedHttpException::class);
        $this->router->getRoutes()->match(Request::create('/admin/item/17/metadata', 'PATCH'));
    }

    public function test_demo_mode_blocks_metadata_post_but_allows_editor_get(): void
    {
        Container::getInstance()->instance('config', new ConfigRepository(['services' => ['demo' => ['enabled' => true]]]));
        foreach (['POST' => false, 'GET' => true] as $method => $allowed) {
            $request = Request::create('/admin/item/17/metadata', $method, [], [], [], ['HTTP_REFERER' => 'http://localhost/admin/item']);
            $route = $this->router->getRoutes()->match($request);
            $route->bind($request);
            $request->setRouteResolver(fn () => $route);
            $reached = false;
            $response = (new Demo())->handle($request, function () use (&$reached) {
                $reached = true;
                return 'allowed';
            });
            $this->assertSame($allowed, $reached, $method);
            if (! $allowed) {
                $this->assertNotSame('allowed', $response);
                $this->assertSame('This action is not allowed in demo mode', Container::getInstance()->make('session')->get('error'));
            }
        }
    }

    public function test_edit_returns_the_dedicated_view_for_the_bound_product(): void
    {
        $item = Item::findOrFail(17);
        $view = $this->createMock(View::class);
        $factory = $this->createMock(ViewFactory::class);
        $factory->expects($this->once())->method('make')
            ->with('back.item.metadata', ['item' => $item], [])->willReturn($view);
        Container::getInstance()->instance(ViewFactory::class, $factory);

        $this->assertSame($view, (new ProductMetadataController())->edit($item));
    }

    public function test_product_permissions_reject_an_unauthenticated_admin(): void
    {
        $guard = $this->createMock(Guard::class);
        $guard->method('check')->willReturn(false);
        Auth::shouldReceive('guard')->with('admin')->once()->andReturn($guard);

        $response = (new Permission())->handle(Request::create('/admin/item/17/metadata'),
            fn () => $this->fail('Guest reached the metadata controller'), 'Manage Products');

        $this->assertSame('/admin', parse_url($response->getTargetUrl(), PHP_URL_PATH));
    }

    public function test_product_permissions_reject_an_admin_without_product_access(): void
    {
        $guard = $this->createMock(Guard::class);
        $guard->method('check')->willReturn(true);
        $guard->method('user')->willReturn(new Admin(['role_id' => 0]));
        Auth::shouldReceive('guard')->with('admin')->andReturn($guard);

        $response = (new Permission())->handle(Request::create('/admin/item/17/metadata'),
            fn () => $this->fail('Unauthorized admin reached the metadata controller'), 'Manage Products');

        $this->assertSame('/admin', parse_url($response->getTargetUrl(), PHP_URL_PATH));
    }

    public function test_product_edit_links_to_metadata_before_its_update_form(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2).'/resources/views/back/item/edit.blade.php');
        $link = strpos($source, "route('back.item.metadata.edit', ['item' => \$item->id])");
        $form = strpos($source, '<form class="admin-form"');

        $this->assertNotFalse($link);
        $this->assertNotFalse($form);
        $this->assertLessThan($form, $link);
    }

    public function test_each_special_product_edit_links_to_metadata_outside_its_update_form(): void
    {
        foreach (['digital', 'license', 'affiliate'] as $type) {
            $source = file_get_contents(dirname(__DIR__, 2)."/resources/views/back/item/{$type}/edit.blade.php");
            $link = strpos($source, "route('back.item.metadata.edit', ['item' => \$item->id])");
            $form = strpos($source, '<form class="admin-form"');
            $this->assertNotFalse($link, "Missing metadata link on {$type} edit");
            $this->assertNotFalse($form);
            $this->assertLessThan($form, $link, "Metadata link must be outside {$type} form");
        }
    }

    public function test_metadata_return_link_uses_product_type_and_unknown_types_return_to_index(): void
    {
        foreach ([
            'normal' => 'http://localhost/admin/item/17/edit',
            'digital' => 'http://localhost/admin/digital/edit/17',
            'license' => 'http://localhost/admin/license/edit/17',
            'affiliate' => 'http://localhost/admin/affiliate/17/edit',
            'unrecognized' => 'http://localhost/admin/item',
        ] as $type => $expected) {
            $item = Item::findOrFail(17);
            $item->item_type = $type;
            $item->setRelation('brand', new Brand());
            $this->assertStringContainsString('href="'.$expected.'"', $this->renderMetadata($item), $type);
        }
    }

    public function test_unknown_null_product_type_returns_to_safe_product_index(): void
    {
        $item = Item::findOrFail(17);
        $item->setRelation('brand', new Brand());
        $this->assertStringContainsString('href="http://localhost/admin/item"', $this->renderMetadata($item));
    }

    public function test_update_persists_only_trimmed_validated_metadata_on_the_bound_item(): void
    {
        $item = Item::findOrFail(17);
        $request = Request::create('/admin/item/17/metadata', 'POST', [
            'seo_title' => '  Custom title  ',
            'seo_meta_description' => "  Plain description  \n",
            'name' => 'UNAUTHORIZED', 'sku' => 'BAD', 'discount_price' => 0,
            'stock' => 0, 'photo' => 'bad.jpg',
        ]);

        $response = (new ProductMetadataController())->update($request, $item);

        $this->assertSame('/admin/item/17/metadata', parse_url($response->getTargetUrl(), PHP_URL_PATH));
        $this->assertSame([
            'id' => 17, 'name' => 'Original product', 'sku' => 'PN-17',
            'discount_price' => 39.95, 'stock' => 7, 'photo' => 'original.jpg',
            'item_type' => null,
            'seo_title' => 'Custom title', 'seo_meta_description' => 'Plain description',
        ], (array) $this->database->table('items')->where('id', 17)->first());
    }

    public function test_blank_metadata_clears_both_custom_fields_without_changing_product_data(): void
    {
        $this->database->table('items')->where('id', 17)->update([
            'seo_title' => 'Old title', 'seo_meta_description' => 'Old description',
        ]);

        (new ProductMetadataController())->update(Request::create('/admin/item/17/metadata', 'POST', [
            'seo_title' => "  \t ", 'seo_meta_description' => "\n  ",
        ]), Item::findOrFail(17));

        $saved = (array) $this->database->table('items')->where('id', 17)->first();
        $this->assertNull($saved['seo_title']);
        $this->assertNull($saved['seo_meta_description']);
        $this->assertSame('Original product', $saved['name']);
        $this->assertSame(7, $saved['stock']);
    }

    public function test_maximum_plain_text_lengths_are_accepted(): void
    {
        (new ProductMetadataController())->update(Request::create('/admin/item/17/metadata', 'POST', [
            'seo_title' => str_repeat('T', 255),
            'seo_meta_description' => str_repeat('D', 1000),
        ]), Item::findOrFail(17));

        $saved = (array) $this->database->table('items')->where('id', 17)->first();
        $this->assertSame(255, mb_strlen($saved['seo_title']));
        $this->assertSame(1000, mb_strlen($saved['seo_meta_description']));
    }

    public function test_successful_save_flashes_a_confirmation(): void
    {
        (new ProductMetadataController())->update(Request::create('/admin/item/17/metadata', 'POST', [
            'seo_title' => 'Valid', 'seo_meta_description' => null,
        ]), Item::findOrFail(17));

        $this->assertNotEmpty(Container::getInstance()->make('session')->get('success'));
    }

    public function test_deleted_bound_product_is_not_reported_as_success(): void
    {
        $staleItem = Item::findOrFail(17);
        $this->database->table('items')->where('id', 17)->delete();
        $this->expectException(ModelNotFoundException::class);
        try {
            (new ProductMetadataController())->update(Request::create('/admin/item/17/metadata', 'POST', [
                'seo_title' => 'New title', 'seo_meta_description' => 'New description',
            ]), $staleItem);
        } finally {
            $this->assertNull(Container::getInstance()->make('session')->get('success'));
            $this->assertSame(0, $this->database->table('items')->where('id', 17)->count());
        }
    }

    public function test_no_op_update_on_existing_product_is_still_successful(): void
    {
        $this->database->table('items')->where('id', 17)->update([
            'seo_title' => 'Already saved', 'seo_meta_description' => 'Already saved description',
        ]);
        $response = (new ProductMetadataController())->update(Request::create('/admin/item/17/metadata', 'POST', [
            'seo_title' => 'Already saved', 'seo_meta_description' => 'Already saved description',
        ]), Item::findOrFail(17));
        $this->assertSame('/admin/item/17/metadata', parse_url($response->getTargetUrl(), PHP_URL_PATH));
        $this->assertNotEmpty(Container::getInstance()->make('session')->get('success'));
        $this->assertSame(1, $this->database->table('items')->where('id', 17)->count());
    }

    public function test_missing_item_is_not_bound_to_another_product(): void
    {
        $request = Request::create('/admin/item/18/metadata', 'POST');
        $route = $this->router->getRoutes()->match($request);
        $route->bind($request);
        $this->expectException(ModelNotFoundException::class);
        ImplicitRouteBinding::resolveForRoute(Container::getInstance(), $route);
    }

    public function test_metadata_form_escapes_custom_copy_and_has_separate_csrf_post_action(): void
    {
        $item = Item::findOrFail(17);
        $item->setRelation('brand', new Brand());
        $item->seo_title = '" onfocus="alert(1)';
        $item->seo_meta_description = '</textarea><script>alert(2)</script>';

        $html = $this->renderMetadata($item);

        $this->assertStringContainsString('name="_token"', $html);
        $this->assertStringNotContainsString('name="_method"', $html);
        $this->assertStringContainsString('action="http://localhost/admin/item/17/metadata"', $html);
        $this->assertStringContainsString('value="&quot; onfocus=&quot;alert(1)"', $html);
        $this->assertStringContainsString('&lt;/textarea&gt;&lt;script&gt;alert(2)&lt;/script&gt;', $html);
        $this->assertStringNotContainsString('<script>alert(2)</script>', $html);
        $this->assertStringNotContainsString('<input type="file"', $html);
        $this->assertStringNotContainsString('name="stock"', $html);
    }

    public function test_empty_custom_values_show_fallback_as_placeholder_not_as_a_saved_value(): void
    {
        $item = Item::findOrFail(17);
        $item->setRelation('brand', new Brand());
        $html = $this->renderMetadata($item);

        $this->assertStringContainsString('placeholder="'.e(ProductMetaTitle::for($item)).'"', $html);
        $this->assertStringContainsString('placeholder="'.e(ProductMetaDescription::for($item)).'"', $html);
        $this->assertStringContainsString('name="seo_title"', $html);
        $this->assertStringContainsString('value=""', $html);
        $this->assertStringContainsString('name="seo_meta_description"', $html);
    }

    public function test_validation_feedback_is_escaped_on_the_metadata_form(): void
    {
        $item = Item::findOrFail(17);
        $item->setRelation('brand', new Brand());
        $errors = new ViewErrorBag();
        $errors->put('default', new MessageBag(['seo_title' => '<script>alert(3)</script>']));

        $html = $this->renderMetadata($item, $errors);

        $this->assertStringContainsString('&lt;script&gt;alert(3)&lt;/script&gt;', $html);
        $this->assertStringNotContainsString('<script>alert(3)</script>', $html);
    }

    private function renderMetadata(Item $item, ?ViewErrorBag $errors = null): string
    {
        $errors ??= new ViewErrorBag();
        $path = dirname(__DIR__, 2).'/resources/views/back/item/metadata.blade.php';
        $blade = file_get_contents($path);
        $blade = str_replace(["@extends('master.back')", "@section('content')", '@endsection'], '', $blade);
        $compiled = (new BladeCompiler(new Filesystem(), sys_get_temp_dir()))->compileString($blade);
        ob_start();
        try {
            eval('?>'.$compiled);
            return ob_get_clean();
        } catch (\Throwable $error) {
            ob_end_clean();
            throw $error;
        }
    }

    public function test_unicode_surrounding_spaces_clear_blank_fields_and_preserve_internal_unicode(): void
    {
        $spaces = "\u{00A0}\u{2003}";
        (new ProductMetadataController())->update(Request::create('/admin/item/17/metadata', 'POST', [
            'seo_title' => $spaces,
            'seo_meta_description' => $spaces.'Привет' . "\u{00A0}" . '世界'.$spaces,
        ]), Item::findOrFail(17));
        $saved = (array) $this->database->table('items')->where('id', 17)->first();
        $this->assertNull($saved['seo_title']);
        $this->assertSame('Привет' . "\u{00A0}" . '世界', $saved['seo_meta_description']);
    }

    public function test_unicode_only_description_clears_while_internal_em_space_in_title_is_preserved(): void
    {
        (new ProductMetadataController())->update(Request::create('/admin/item/17/metadata', 'POST', [
            'seo_title' => "\u{2003}Alpha\u{2003}Beta\u{00A0}",
            'seo_meta_description' => "\u{00A0}\u{2003}\u{00A0}",
        ]), Item::findOrFail(17));
        $saved = (array) $this->database->table('items')->where('id', 17)->first();
        $this->assertSame("Alpha\u{2003}Beta", $saved['seo_title']);
        $this->assertNull($saved['seo_meta_description']);
    }

    public function test_malformed_utf8_is_rejected_in_either_metadata_field(): void
    {
        $this->database->table('items')->where('id', 17)->update([
            'seo_title' => 'Original SEO', 'seo_meta_description' => 'Original description',
        ]);
        foreach (['seo_title', 'seo_meta_description'] as $field) {
            $input = ['seo_title' => 'Safe', 'seo_meta_description' => 'Safe'];
            $input[$field] = "Invalid \xC3\x28";
            try {
                (new ProductMetadataController())->update(Request::create('/admin/item/17/metadata', 'POST', $input), Item::findOrFail(17));
                $this->fail("Malformed {$field} saved");
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey($field, $exception->errors());
            }
            $this->assertSame('Original SEO', $this->database->table('items')->where('id', 17)->value('seo_title'));
            $this->assertSame('Original description', $this->database->table('items')->where('id', 17)->value('seo_meta_description'));
        }
    }

    public function test_invalid_metadata_rejects_updates_without_touching_existing_values(): void
    {
        $this->database->table('items')->where('id', 17)->update([
            'seo_title' => 'Original SEO', 'seo_meta_description' => 'Original description',
        ]);
        foreach ([
            ['seo_title' => str_repeat('t', 256), 'seo_meta_description' => 'valid'],
            ['seo_title' => 'valid', 'seo_meta_description' => str_repeat('d', 1001)],
            ['seo_title' => ['not', 'plain text'], 'seo_meta_description' => 'valid'],
            ['seo_title' => 'valid', 'seo_meta_description' => ['not plain text']],
            ['seo_title' => '<script>alert(1)</script>', 'seo_meta_description' => 'valid'],
            ['seo_title' => 'valid', 'seo_meta_description' => '<b>rich copy</b>'],
            ['seo_title' => 'valid'],
        ] as $invalid) {
            try {
                (new ProductMetadataController())->update(
                    Request::create('/admin/item/17/metadata', 'POST', $invalid), Item::findOrFail(17)
                );
                $this->fail('Invalid metadata unexpectedly saved');
            } catch (ValidationException $exception) {
                $this->assertNotEmpty($exception->errors());
            }
            $this->assertSame('Original SEO', $this->database->table('items')->where('id', 17)->value('seo_title'));
            $this->assertSame('Original description', $this->database->table('items')->where('id', 17)->value('seo_meta_description'));
        }
    }
}
