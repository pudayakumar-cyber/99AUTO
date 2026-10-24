<?php

namespace App\Http\Middleware;

use App\Models\PromoCode;
use App\Repositories\Front\CartRepository;
use App\Services\MarketingCouponEligibility;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class RevalidateMarketingCoupon
{
    // Only payment initiation: never change a paid gateway callback's snapshot.
    public const ROUTES = [
        'front.checkout.submit', 'front.stripe.create.intent',
        'front.paytm.submit', 'front.razorpay.submit', 'front.flutterwave.submit',
        'front.mercadopago.submit', 'front.authorize.submit',
        'front.sslcommerz.submit', 'front.paytab.submit',
    ];

    public function __construct(private MarketingCouponEligibility $eligibility) {}

    public function handle(Request $request, Closure $next)
    {
        if ($request->routeIs(...self::ROUTES) && $request->user()
            && app(\App\Services\StripeCheckoutAttemptStore::class)->activeAttempt((int) $request->user()->getKey())) {
            if (!$request->routeIs('front.stripe.create.intent')) {
                throw ValidationException::withMessages(['payment' => 'A Stripe payment is unresolved. Confirm or cancel it before starting another payment.']);
            }
            // Its personal offer is already reserved; the immutable checkout store
            // validates the retry instead of consuming/recalculating that offer again.
            return $next($request);
        }
        if (! $request->routeIs(...self::ROUTES) || ! $request->session()->has('coupon')) {
            return $next($request);
        }
        $snapshot = $request->session()->get('coupon');
        $coupon = PromoCode::find(data_get($snapshot, 'code.id'));
        if (! $coupon || ! $this->eligibility->allows($coupon, $request->user())) {
            $request->session()->forget('coupon');
            throw ValidationException::withMessages(['coupon' =>
                'Your coupon is no longer available. Please review your cart before paying.']);
        }
        $subtotal = 0;
        foreach ($request->session()->get('cart', []) as $item) {
            $subtotal += ((float) ($item['main_price'] ?? 0) + (float) ($item['attribute_price'] ?? 0))
                * (int) ($item['qty'] ?? 0);
        }
        $discount = (new CartRepository)->getDiscount($coupon->discount, $coupon->type, $subtotal)['sub'];
        $request->session()->put('coupon', ['code' => $coupon, 'discount' => $discount]);
        if (abs((float) data_get($snapshot, 'discount', 0) - $discount) > 0.00001) {
            throw ValidationException::withMessages(['coupon' =>
                'Your discount has changed. Please review your cart before paying.']);
        }

        return $next($request);
    }
}
