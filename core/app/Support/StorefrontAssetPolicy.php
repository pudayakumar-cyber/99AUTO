<?php

namespace App\Support;

final class StorefrontAssetPolicy
{
    private const STRIPE_ROUTES = [
        'front.checkout',
        'front.checkout.payment',
    ];

    public static function loadsStripe(?string $routeName): bool
    {
        return in_array($routeName, self::STRIPE_ROUTES, true);
    }
}
