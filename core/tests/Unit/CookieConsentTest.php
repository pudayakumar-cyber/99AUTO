<?php

namespace Tests\Unit;

use App\Http\Middleware\CookieConsent;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Route;
use PHPUnit\Framework\TestCase;

class CookieConsentTest extends TestCase
{
    protected function tearDown(): void
    {
        Container::setInstance(null);
        parent::tearDown();
    }

    public function test_recovery_responses_skip_banner_after_route_matching(): void
    {
        foreach (['front.cart.recover', 'front.cart.recover.restore'] as $name) {
            $request = Request::create('/cart/recover/example');
            $response = new Response('<body>Restore</body>');
            $calls = 0;
            $actual = (new CookieConsent)->handle($request, function ($request) use ($name, $response, &$calls) {
                $calls++;
                $route = (new Route('GET', '/cart/recover/{token}', fn () => null))->name($name);
                $request->setRouteResolver(fn () => $route);
                return $response;
            });
            $this->assertSame($response, $actual);
            $this->assertSame('<body>Restore</body>', $actual->getContent());
            $this->assertSame(1, $calls);
        }
    }

    public function test_normal_pages_keep_vendor_cookie_banner_behavior(): void
    {
        $app = new Container;
        $app->instance('config', new Repository(['cookie-consent' => ['enabled' => true]]));
        Container::setInstance($app);
        $middleware = new class extends CookieConsent {
            protected function addCookieConsentScriptToResponse(Response $response): Response
            {
                return $response->setContent($response->getContent().'BANNER');
            }
        };
        $request = Request::create('/catalog');
        $result = $middleware->handle($request, fn () => new Response('<body>Catalog</body>'));
        $this->assertSame('<body>Catalog</body>BANNER', $result->getContent());
        $app['config']->set('cookie-consent.enabled', false);
        $result = $middleware->handle($request, fn () => new Response('<body>Catalog</body>'));
        $this->assertSame('<body>Catalog</body>', $result->getContent());
    }
}
