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
                    'em' => $this->hash($email),
                    'ph' => $this->hash($phone),
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

    private function hash(?string $value): ?string
    {
        $value = trim(strtolower((string) $value));

        return $value === '' ? null : hash('sha256', $value);
    }
}
