<?php

namespace App\Services;

use App\Helpers\PriceHelper;
use App\Models\Order;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FacebookConversionApi
{
    public function trackPurchase(
        Order $order,
        array $cart,
        ?string $email,
        ?string $phone,
        ?string $clientIp,
        ?string $userAgent,
        ?string $eventId = null
    ): bool {
        $pixelId = config('services.facebook.pixel_id');
        $token = config('services.facebook.conversion_api_token');

        if (!$pixelId || !$token) {
            Log::info('Facebook CAPI skipped: missing pixel id or token.', [
                'order_id' => $order->id,
                'transaction_number' => $order->transaction_number,
            ]);

            return false;
        }

        $eventId = $eventId ?: $this->purchaseEventId($order);
        $contents = [];
        $numItems = 0;
        $billingInfo = json_decode((string) $order->billing_info, true) ?: [];
        $shippingInfo = json_decode((string) $order->shipping_info, true) ?: [];
        $user = $order->user;

        foreach ($cart as $key => $item) {
            $quantity = (int) ($item['qty'] ?? 1);
            $contents[] = [
                'id' => (string) ($item['id'] ?? $item['item_id'] ?? $key),
                'quantity' => $quantity,
                'item_price' => (float) ($item['main_price'] ?? $item['price'] ?? 0),
            ];
            $numItems += $quantity;
        }

        $payload = [
            'data' => [[
                'event_name' => 'Purchase',
                'event_time' => time(),
                'event_id' => $eventId,
                'action_source' => 'website',
                'event_source_url' => url()->current(),
                'user_data' => array_filter([
                    'em' => $this->hashEmail($email ?: ($billingInfo['bill_email'] ?? $shippingInfo['ship_email'] ?? $user->email ?? null)),
                    'ph' => $this->hashPhone($phone ?: ($billingInfo['bill_phone'] ?? $shippingInfo['ship_phone'] ?? $user->phone ?? null)),
                    'fn' => $this->hashText($billingInfo['bill_first_name'] ?? $shippingInfo['ship_first_name'] ?? $user->first_name ?? null),
                    'ln' => $this->hashText($billingInfo['bill_last_name'] ?? $shippingInfo['ship_last_name'] ?? $user->last_name ?? null),
                    'ct' => $this->hashText($billingInfo['bill_city'] ?? $shippingInfo['ship_city'] ?? $user->bill_city ?? $user->ship_city ?? null),
                    'st' => $this->hashText($billingInfo['bill_province'] ?? $shippingInfo['ship_province'] ?? $user->bill_province ?? $user->ship_province ?? null),
                    'zp' => $this->hashText($billingInfo['bill_zip'] ?? $shippingInfo['ship_zip'] ?? $user->bill_zip ?? $user->ship_zip ?? null),
                    'country' => $this->hashCountry($billingInfo['bill_country'] ?? $shippingInfo['ship_country'] ?? $user->bill_country ?? $user->ship_country ?? null),
                    'external_id' => $this->hashText($order->user_id ? 'user_' . $order->user_id : ($billingInfo['bill_email'] ?? $email)),
                    'client_ip_address' => $clientIp,
                    'client_user_agent' => $userAgent,
                    'fbp' => request()->cookie('_fbp'),
                    'fbc' => request()->cookie('_fbc'),
                ]),
                'custom_data' => [
                    'currency' => $order->currency_sign ?: 'CAD',
                    'value' => (float) PriceHelper::OrderTotal($order, 'trns'),
                    'order_id' => (string) $order->transaction_number,
                    'content_type' => 'product',
                    'contents' => $contents,
                    'num_items' => $numItems,
                ],
            ]],
            'access_token' => $token,
        ];

        try {
            $response = Http::timeout(10)->post(
                "https://graph.facebook.com/v19.0/{$pixelId}/events",
                $payload
            );

            if ($response->failed()) {
                Log::warning('Facebook CAPI purchase failed.', [
                    'order_id' => $order->id,
                    'transaction_number' => $order->transaction_number,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return false;
            }

            Log::info('Facebook CAPI purchase sent.', [
                'order_id' => $order->id,
                'transaction_number' => $order->transaction_number,
                'event_id' => $eventId,
            ]);

            return true;
        } catch (\Throwable $e) {
            Log::warning('Facebook CAPI purchase exception: ' . $e->getMessage(), [
                'order_id' => $order->id,
                'transaction_number' => $order->transaction_number,
            ]);

            return false;
        }
    }

    public function purchaseEventId(Order $order): string
    {
        return 'purchase_' . ($order->transaction_number ?: $order->id);
    }

    private function hashEmail(?string $value): ?string
    {
        $value = trim(strtolower((string) $value));

        return $value === '' ? null : hash('sha256', $value);
    }

    private function hashPhone(?string $value): ?string
    {
        $value = preg_replace('/\D+/', '', (string) $value);

        return $value === '' ? null : hash('sha256', $value);
    }

    private function hashText(?string $value): ?string
    {
        $value = preg_replace('/\s+/', '', trim(strtolower((string) $value)));

        return $value === '' ? null : hash('sha256', $value);
    }

    private function hashCountry(?string $value): ?string
    {
        $value = trim(strtolower((string) $value));
        $countries = [
            'canada' => 'ca',
            'ca' => 'ca',
            'united states' => 'us',
            'united states of america' => 'us',
            'usa' => 'us',
            'us' => 'us',
        ];

        return $this->hashText($countries[$value] ?? $value);
    }
}
