<?php

namespace App\Http\Middleware;

use Closure;
use Spatie\CookieConsent\CookieConsentMiddleware;

class CookieConsent extends CookieConsentMiddleware
{
    public function handle($request, Closure $next)
    {
        // Global middleware runs before route matching; inspect the route afterward.
        $response = $next($request);
        if ($request->routeIs('front.cart.recover', 'front.cart.recover.restore')) {
            return $response;
        }

        return parent::handle($request, fn () => $response);
    }
}
