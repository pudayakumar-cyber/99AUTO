<?php

namespace Tests\Unit;

use App\Support\StorefrontAssetPolicy;
use PHPUnit\Framework\TestCase;

class StorefrontAssetPolicyTest extends TestCase
{
    /**
     * @dataProvider checkoutRoutes
     */
    public function test_it_loads_stripe_on_checkout_payment_routes(string $routeName): void
    {
        $this->assertTrue(StorefrontAssetPolicy::loadsStripe($routeName));
    }

    public static function checkoutRoutes(): array
    {
        return [
            'single-page checkout' => ['front.checkout'],
            'review and payment checkout' => ['front.checkout.payment'],
        ];
    }

    /**
     * @dataProvider nonCheckoutRoutes
     */
    public function test_it_does_not_load_stripe_outside_checkout(?string $routeName): void
    {
        $this->assertFalse(StorefrontAssetPolicy::loadsStripe($routeName));
    }

    public static function nonCheckoutRoutes(): array
    {
        return [
            'homepage' => ['front.index'],
            'catalog' => ['front.catalog'],
            'product' => ['front.product'],
            'unresolved route' => [null],
        ];
    }
}
