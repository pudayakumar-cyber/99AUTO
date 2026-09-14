<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Services\CartRecoveryService;
use App\Support\KlaviyoUrl;
use Illuminate\Http\Request;

class CartRecoveryController extends Controller
{
    public function show(string $token, CartRecoveryService $recovery)
    {
        $link = $recovery->find($token);

        return response()->view('front.catalog.recover', [
            'available' => $link !== null,
            'action' => KlaviyoUrl::to('/cart/recover/'.$token),
            'shopUrl' => KlaviyoUrl::to('/catalog'),
        ], $link ? 200 : 410)->withHeaders($this->headers());
    }

    public function restore(Request $request, string $token, CartRecoveryService $recovery)
    {
        $link = $recovery->find($token);
        if (! $link) {
            return $this->show($token, $recovery);
        }
        $result = $recovery->restore($link->items);
        if ($result['cart'] === []) {
            return redirect(KlaviyoUrl::to('/cart'))
                ->with('error', 'The saved items are no longer available. Your current cart has been kept.')
                ->withHeaders($this->headers());
        }
        $session = $request->session();
        $session->put('cart', $result['cart']);
        $session->forget(['coupon', 'shipping', 'shipping_address', 'shipping_id', 'shipping_price',
            'payment_id', 'stripe_payment_intent_id', 'order_data', 'order_input_data', 'order_payment_id',
            'discount', 'checkout_event_id', 'klaviyo_cart_recovery']);

        return redirect(KlaviyoUrl::to('/cart'))
            ->with('success', $result['skipped'] > 0
                ? 'Available saved items were restored at current prices. Some items are no longer available.'
                : 'Your saved cart was restored at current prices. Review it before checkout.')
            ->withHeaders($this->headers());
    }

    private function headers(): array
    {
        return ['Cache-Control' => 'private, no-store', 'Referrer-Policy' => 'no-referrer',
            'X-Robots-Tag' => 'noindex, nofollow',
            'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; form-action 'self'; frame-ancestors 'none'; base-uri 'none'"];
    }
}
