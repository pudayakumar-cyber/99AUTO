<?php

namespace App\Services;

use App\Helpers\EmailHelper;
use App\Helpers\PriceHelper;
use App\Models\Order;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;

class OrderMarketingTracker
{
    public function preparePurchaseViewData(Order $order): array
    {
        $cart = $this->cart($order);

        return [
            'cart' => $cart,
            'cart_content_ids' => $this->contentIds($cart),
            'order_value' => (float) PriceHelper::OrderTotal($order, 'trns'),
            'currency' => $order->currency_sign ?: 'CAD',
            'num_items' => $this->numItems($cart),
            'event_id' => $this->purchaseEventId($order),
        ];
    }

    public function trackPurchase(Order $order, ?array $cart = null, ?string $eventId = null): bool
    {
        $cart = $cart ?: $this->cart($order);
        $eventId = $eventId ?: $this->purchaseEventId($order);
        $sessionKey = 'facebook_capi_purchase_sent_' . $order->id;

        if (Session::get($sessionKey)) {
            Log::info('Marketing purchase tracking skipped: CAPI already sent in session.', [
                'order_id' => $order->id,
                'transaction_number' => $order->transaction_number,
                'event_id' => $eventId,
            ]);

            return true;
        }

        $billingInfo = $this->billingInfo($order);
        $sent = (new FacebookConversionApi())->trackPurchase(
            $order,
            $cart,
            $billingInfo['bill_email'] ?? EmailHelper::getEmail(),
            $billingInfo['bill_phone'] ?? null,
            request()->ip(),
            request()->header('User-Agent'),
            $eventId,
            route('front.checkout.success')
        );

        if ($sent) {
            Session::put($sessionKey, true);
        }

        Log::info('Marketing purchase tracking completed.', [
            'order_id' => $order->id,
            'transaction_number' => $order->transaction_number,
            'event_id' => $eventId,
            'facebook_capi_sent' => $sent,
            'value' => (float) PriceHelper::OrderTotal($order, 'trns'),
            'currency' => $order->currency_sign ?: 'CAD',
            'num_items' => $this->numItems($cart),
            'content_ids' => $this->contentIds($cart),
        ]);

        return $sent;
    }

    public function purchaseEventId(Order $order): string
    {
        return (new FacebookConversionApi())->purchaseEventId($order);
    }

    private function cart(Order $order): array
    {
        return json_decode((string) $order->cart, true) ?: [];
    }

    private function billingInfo(Order $order): array
    {
        return json_decode((string) $order->billing_info, true) ?: [];
    }

    private function contentIds(array $cart): array
    {
        $contentIds = [];

        foreach ($cart as $key => $item) {
            $contentIds[] = (string) ($item['id'] ?? $item['item_id'] ?? explode('-', (string) $key)[0]);
        }

        return $contentIds;
    }

    private function numItems(array $cart): int
    {
        $numItems = 0;

        foreach ($cart as $item) {
            $numItems += (int) ($item['qty'] ?? 1);
        }

        return $numItems;
    }
}
