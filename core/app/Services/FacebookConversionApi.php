<?php

namespace App\Services;

use App\Helpers\PriceHelper;
use App\Models\Item;
use App\Models\Order;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class FacebookConversionApi
{
    public function trackPurchase(
        Order $order,
        array $cart,
        ?string $email,
        ?string $phone,
        ?string $clientIp,
        ?string $userAgent,
        ?string $eventId = null,
        ?string $eventSourceUrl = null
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

        $userData = array_filter([
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
        ]);

        $payload = [
            'data' => [[
                'event_name' => 'Purchase',
                'event_time' => time(),
                'event_id' => $eventId,
                'action_source' => 'website',
                'event_source_url' => $eventSourceUrl ?: url()->current(),
                'user_data' => $userData,
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

        if (config('services.facebook.test_event_code')) {
            $payload['test_event_code'] = config('services.facebook.test_event_code');
        }

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
                'event_source_url' => $eventSourceUrl ?: url()->current(),
                'user_data_keys' => array_keys($userData),
                'contents_count' => count($contents),
                'response' => $response->json(),
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

    public function viewContentEventId(Item $item): string
    {
        return 'viewcontent_' . $item->id . '_' . (string) Str::uuid();
    }

    public function trackViewContent(Item $item, string $eventId): bool
    {
        $pixelId = config('services.facebook.pixel_id');
        $token = config('services.facebook.conversion_api_token');

        if (!$pixelId || !$token) {
            Log::info('Facebook CAPI ViewContent skipped: missing pixel id or token.', [
                'item_id' => $item->id,
                'event_id' => $eventId,
            ]);

            return false;
        }

        $user = auth()->user();
        $itemId = (string) ($item->id ?? $item->prod_number);
        $price = (float) ($item->discount_price ?? $item->previous_price ?? 0);
        $userData = array_filter([
            'em' => $this->hashEmail(optional($user)->email),
            'ph' => $this->hashPhone(optional($user)->phone),
            'fn' => $this->hashText(optional($user)->first_name),
            'ln' => $this->hashText(optional($user)->last_name),
            'ct' => $this->hashText(optional($user)->bill_city ?? optional($user)->ship_city),
            'st' => $this->hashText(optional($user)->bill_province ?? optional($user)->ship_province),
            'zp' => $this->hashText(optional($user)->bill_zip ?? optional($user)->ship_zip),
            'country' => $this->hashCountry(optional($user)->bill_country ?? optional($user)->ship_country),
            'external_id' => $user ? $this->hashText('user_' . $user->id) : null,
            'client_ip_address' => request()->ip(),
            'client_user_agent' => request()->header('User-Agent'),
            'fbp' => request()->cookie('_fbp'),
            'fbc' => request()->cookie('_fbc'),
        ]);

        $payload = [
            'data' => [[
                'event_name' => 'ViewContent',
                'event_time' => time(),
                'event_id' => $eventId,
                'action_source' => 'website',
                'event_source_url' => route('front.product', $item->slug),
                'user_data' => $userData,
                'custom_data' => [
                    'content_type' => 'product',
                    'content_ids' => [$itemId],
                    'content_name' => (string) $item->name,
                    'content_category' => (string) optional($item->category)->name,
                    'currency' => 'CAD',
                    'value' => $price,
                    'contents' => [[
                        'id' => $itemId,
                        'quantity' => 1,
                        'item_price' => $price,
                    ]],
                ],
            ]],
            'access_token' => $token,
        ];

        if (config('services.facebook.test_event_code')) {
            $payload['test_event_code'] = config('services.facebook.test_event_code');
        }

        try {
            $response = Http::timeout(5)->post(
                "https://graph.facebook.com/v19.0/{$pixelId}/events",
                $payload
            );

            if ($response->failed()) {
                Log::warning('Facebook CAPI ViewContent failed.', [
                    'item_id' => $item->id,
                    'event_id' => $eventId,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return false;
            }

            Log::info('Facebook CAPI ViewContent sent.', [
                'item_id' => $item->id,
                'event_id' => $eventId,
                'user_data_keys' => array_keys($userData),
                'response' => $response->json(),
            ]);

            return true;
        } catch (\Throwable $e) {
            Log::warning('Facebook CAPI ViewContent exception: ' . $e->getMessage(), [
                'item_id' => $item->id,
                'event_id' => $eventId,
            ]);

            return false;
        }
    }

    public function addToCartEventId(Item $item): string
    {
        return 'addtocart_' . $item->id . '_' . (string) Str::uuid();
    }

    public function trackAddToCart(Item $item, int $quantity, string $eventId): bool
    {
        $pixelId = config('services.facebook.pixel_id');
        $token = config('services.facebook.conversion_api_token');

        if (!$pixelId || !$token) {
            Log::info('Facebook CAPI AddToCart skipped: missing pixel id or token.', [
                'item_id' => $item->id,
                'event_id' => $eventId,
            ]);

            return false;
        }

        $user = auth()->user();
        $itemId = (string) ($item->id ?? $item->prod_number);
        $price = (float) ($item->discount_price ?? $item->previous_price ?? 0);
        $userData = array_filter([
            'em' => $this->hashEmail(optional($user)->email),
            'ph' => $this->hashPhone(optional($user)->phone),
            'fn' => $this->hashText(optional($user)->first_name),
            'ln' => $this->hashText(optional($user)->last_name),
            'ct' => $this->hashText(optional($user)->bill_city ?? optional($user)->ship_city),
            'st' => $this->hashText(optional($user)->bill_province ?? optional($user)->ship_province),
            'zp' => $this->hashText(optional($user)->bill_zip ?? optional($user)->ship_zip),
            'country' => $this->hashCountry(optional($user)->bill_country ?? optional($user)->ship_country),
            'external_id' => $user ? $this->hashText('user_' . $user->id) : null,
            'client_ip_address' => request()->ip(),
            'client_user_agent' => request()->header('User-Agent'),
            'fbp' => request()->cookie('_fbp'),
            'fbc' => request()->cookie('_fbc'),
        ]);

        $payload = [
            'data' => [[
                'event_name' => 'AddToCart',
                'event_time' => time(),
                'event_id' => $eventId,
                'action_source' => 'website',
                'event_source_url' => route('front.product', $item->slug),
                'user_data' => $userData,
                'custom_data' => [
                    'content_type' => 'product',
                    'content_ids' => [$itemId],
                    'content_name' => (string) $item->name,
                    'content_category' => (string) optional($item->category)->name,
                    'currency' => 'CAD',
                    'value' => $price * max(1, $quantity),
                    'contents' => [[
                        'id' => $itemId,
                        'quantity' => max(1, $quantity),
                        'item_price' => $price,
                    ]],
                    'num_items' => max(1, $quantity),
                ],
            ]],
            'access_token' => $token,
        ];

        if (config('services.facebook.test_event_code')) {
            $payload['test_event_code'] = config('services.facebook.test_event_code');
        }

        try {
            $response = Http::timeout(5)->post(
                "https://graph.facebook.com/v19.0/{$pixelId}/events",
                $payload
            );

            if ($response->failed()) {
                Log::warning('Facebook CAPI AddToCart failed.', [
                    'item_id' => $item->id,
                    'event_id' => $eventId,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return false;
            }

            Log::info('Facebook CAPI AddToCart sent.', [
                'item_id' => $item->id,
                'event_id' => $eventId,
                'user_data_keys' => array_keys($userData),
                'response' => $response->json(),
            ]);

            return true;
        } catch (\Throwable $e) {
            Log::warning('Facebook CAPI AddToCart exception: ' . $e->getMessage(), [
                'item_id' => $item->id,
                'event_id' => $eventId,
            ]);

            return false;
        }
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
