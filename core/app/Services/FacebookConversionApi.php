<?php

namespace App\Services;

use App\Helpers\PriceHelper;
use App\Models\Item;
use App\Models\Order;
use App\Models\State;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
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
        $contentIds = [];
        $contents = [];
        $numItems = 0;
        $billingInfo = json_decode((string) $order->billing_info, true) ?: [];
        $shippingInfo = json_decode((string) $order->shipping_info, true) ?: [];
        $user = $order->user;

        foreach ($cart as $key => $item) {
            $quantity = (int) ($item['qty'] ?? 1);
            $itemId = (string) ($item['id'] ?? $item['item_id'] ?? $this->cartItemIdFromKey($key));

            $contentIds[] = $itemId;
            $contents[] = [
                'id' => $itemId,
                'quantity' => $quantity,
                'item_price' => (float) ($item['main_price'] ?? $item['price'] ?? 0),
            ];
            $numItems += $quantity;
        }

        $userData = $this->buildUserData([
            'billing' => $billingInfo,
            'shipping' => $shippingInfo,
            'user' => $user,
            'email' => $email,
            'phone' => $phone,
            'external_id' => $order->user_id ? 'user_' . $order->user_id : ($billingInfo['bill_email'] ?? $email),
            'client_ip_address' => $clientIp,
            'client_user_agent' => $userAgent,
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
                    'content_ids' => $contentIds,
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

    public function shouldTrackBrowserEvent(): bool
    {
        $request = request();
        $userAgent = strtolower((string) $request->userAgent());

        if (!$request->isMethod('GET') || $request->ajax() || $request->prefetch() || $userAgent === '') {
            return false;
        }

        $blockedAgents = [
            'bot',
            'crawl',
            'spider',
            'slurp',
            'facebookexternalhit',
            'facebot',
            'google-structured-data-testing-tool',
            'lighthouse',
            'pagespeed',
            'pingdom',
            'uptimerobot',
            'semrush',
            'ahrefs',
            'mj12bot',
            'dotbot',
            'bingpreview',
            'whatsapp',
            'telegrambot',
            'discordbot',
            'linkedinbot',
            'twitterbot',
        ];

        foreach ($blockedAgents as $blockedAgent) {
            if (strpos($userAgent, $blockedAgent) !== false) {
                return false;
            }
        }

        return true;
    }

    public function trackPageView(string $eventId, ?string $eventSourceUrl = null): bool
    {
        if (!$this->shouldTrackBrowserEvent()) {
            Log::info('Facebook CAPI PageView skipped: non-browser request.', [
                'event_id' => $eventId,
                'user_agent' => request()->userAgent(),
            ]);

            return false;
        }

        $pixelId = config('services.facebook.pixel_id');
        $token = config('services.facebook.conversion_api_token');

        if (!$pixelId || !$token) {
            Log::info('Facebook CAPI PageView skipped: missing pixel id or token.', [
                'event_id' => $eventId,
            ]);

            return false;
        }

        $userData = $this->buildUserData();

        $payload = [
            'data' => [[
                'event_name' => 'PageView',
                'event_time' => time(),
                'event_id' => $eventId,
                'action_source' => 'website',
                'event_source_url' => $eventSourceUrl ?: url()->current(),
                'user_data' => $userData,
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
                Log::warning('Facebook CAPI PageView failed.', [
                    'event_id' => $eventId,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return false;
            }

            Log::info('Facebook CAPI PageView sent.', [
                'event_id' => $eventId,
                'user_data_keys' => array_keys($userData),
                'response' => $response->json(),
            ]);

            return true;
        } catch (\Throwable $e) {
            Log::warning('Facebook CAPI PageView exception: ' . $e->getMessage(), [
                'event_id' => $eventId,
            ]);

            return false;
        }
    }

    public function trackViewContent(Item $item, string $eventId): bool
    {
        if (!$this->shouldTrackBrowserEvent()) {
            Log::info('Facebook CAPI ViewContent skipped: non-browser request.', [
                'item_id' => $item->id,
                'event_id' => $eventId,
                'user_agent' => request()->userAgent(),
            ]);

            return false;
        }

        $pixelId = config('services.facebook.pixel_id');
        $token = config('services.facebook.conversion_api_token');

        if (!$pixelId || !$token) {
            Log::info('Facebook CAPI ViewContent skipped: missing pixel id or token.', [
                'item_id' => $item->id,
                'event_id' => $eventId,
            ]);

            return false;
        }

        $itemId = (string) ($item->id ?? $item->prod_number);
        $price = (float) ($item->discount_price ?? $item->previous_price ?? 0);
        $userData = $this->buildUserData();

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

    public function initiateCheckoutEventId(): string
    {
        return 'initiatecheckout_' . (string) Str::uuid();
    }

    public function addPaymentInfoEventId(): string
    {
        return 'addpaymentinfo_' . (string) Str::uuid();
    }

    public function trackInitiateCheckout(array $cart, string $eventId, float $value, string $currency = 'CAD'): bool
    {
        $pixelId = config('services.facebook.pixel_id');
        $token = config('services.facebook.conversion_api_token');

        if (!$pixelId || !$token) {
            Log::info('Facebook CAPI InitiateCheckout skipped: missing pixel id or token.', [
                'event_id' => $eventId,
            ]);

            return false;
        }

        $contentIds = [];
        $contents = [];
        $numItems = 0;

        foreach ($cart as $key => $item) {
            $quantity = max(1, (int) ($item['qty'] ?? 1));
            $itemId = (string) ($item['id'] ?? $item['item_id'] ?? $this->cartItemIdFromKey($key));
            $price = (float) ($item['main_price'] ?? $item['price'] ?? 0);

            $contentIds[] = $itemId;
            $contents[] = [
                'id' => $itemId,
                'quantity' => $quantity,
                'item_price' => $price,
            ];
            $numItems += $quantity;
        }

        $userData = $this->buildUserData();

        $payload = [
            'data' => [[
                'event_name' => 'InitiateCheckout',
                'event_time' => time(),
                'event_id' => $eventId,
                'action_source' => 'website',
                'event_source_url' => route('front.checkout.billing'),
                'user_data' => $userData,
                'custom_data' => [
                    'content_type' => 'product',
                    'content_ids' => $contentIds,
                    'currency' => $currency,
                    'value' => $value,
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
            $response = Http::timeout(5)->post(
                "https://graph.facebook.com/v19.0/{$pixelId}/events",
                $payload
            );

            if ($response->failed()) {
                Log::warning('Facebook CAPI InitiateCheckout failed.', [
                    'event_id' => $eventId,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return false;
            }

            Log::info('Facebook CAPI InitiateCheckout sent.', [
                'event_id' => $eventId,
                'user_data_keys' => array_keys($userData),
                'contents_count' => count($contents),
                'response' => $response->json(),
            ]);

            return true;
        } catch (\Throwable $e) {
            Log::warning('Facebook CAPI InitiateCheckout exception: ' . $e->getMessage(), [
                'event_id' => $eventId,
            ]);

            return false;
        }
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

        $itemId = (string) ($item->id ?? $item->prod_number);
        $price = (float) ($item->discount_price ?? $item->previous_price ?? 0);
        $userData = $this->buildUserData();

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

    public function trackAddPaymentInfo(
        array $cart,
        string $eventId,
        float $value,
        string $currency = 'CAD',
        string $paymentType = 'Stripe'
    ): bool {
        $pixelId = config('services.facebook.pixel_id');
        $token = config('services.facebook.conversion_api_token');

        if (!$pixelId || !$token) {
            Log::info('Facebook CAPI AddPaymentInfo skipped: missing pixel id or token.', [
                'event_id' => $eventId,
            ]);

            return false;
        }

        $billingInfo = Session::get('billing_address', []);
        $shippingInfo = Session::get('shipping_address', []);
        $contentIds = [];
        $contents = [];
        $numItems = 0;

        foreach ($cart as $key => $item) {
            $quantity = max(1, (int) ($item['qty'] ?? 1));
            $itemId = (string) ($item['id'] ?? $item['item_id'] ?? $this->cartItemIdFromKey($key));
            $price = (float) ($item['main_price'] ?? $item['price'] ?? 0);

            $contentIds[] = $itemId;
            $contents[] = [
                'id' => $itemId,
                'quantity' => $quantity,
                'item_price' => $price,
            ];
            $numItems += $quantity;
        }

        $userData = $this->buildUserData([
            'billing' => $billingInfo,
            'shipping' => $shippingInfo,
        ]);

        $payload = [
            'data' => [[
                'event_name' => 'AddPaymentInfo',
                'event_time' => time(),
                'event_id' => $eventId,
                'action_source' => 'website',
                'event_source_url' => route('front.checkout.payment'),
                'user_data' => $userData,
                'custom_data' => [
                    'content_type' => 'product',
                    'content_ids' => $contentIds,
                    'currency' => $currency,
                    'value' => $value,
                    'contents' => $contents,
                    'num_items' => $numItems,
                    'payment_type' => $paymentType,
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
                Log::warning('Facebook CAPI AddPaymentInfo failed.', [
                    'event_id' => $eventId,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return false;
            }

            Log::info('Facebook CAPI AddPaymentInfo sent.', [
                'event_id' => $eventId,
                'user_data_keys' => array_keys($userData),
                'contents_count' => count($contents),
                'response' => $response->json(),
            ]);

            return true;
        } catch (\Throwable $e) {
            Log::warning('Facebook CAPI AddPaymentInfo exception: ' . $e->getMessage(), [
                'event_id' => $eventId,
            ]);

            return false;
        }
    }

    private function stateValue(array $billingInfo = [], array $shippingInfo = [], $user = null): ?string
    {
        $state = $billingInfo['bill_province']
            ?? $billingInfo['bill_state']
            ?? $shippingInfo['ship_province']
            ?? $shippingInfo['ship_state']
            ?? optional($user)->bill_province
            ?? optional($user)->ship_province
            ?? null;

        $stateId = $billingInfo['state_id']
            ?? $shippingInfo['state_id']
            ?? optional($user)->state_id
            ?? null;

        if (!$state && $stateId) {
            $state = optional(State::find($stateId))->name ?: (string) $stateId;
        }

        return $state;
    }

    public function rememberCheckoutIdentity(array $billingInfo = [], array $shippingInfo = []): void
    {
        $identity = array_filter(array_merge(
            Session::get('facebook_checkout_identity', []),
            [
                'email' => $billingInfo['bill_email'] ?? $shippingInfo['ship_email'] ?? null,
                'phone' => $billingInfo['bill_phone'] ?? $shippingInfo['ship_phone'] ?? null,
                'first_name' => $billingInfo['bill_first_name'] ?? $shippingInfo['ship_first_name'] ?? null,
                'last_name' => $billingInfo['bill_last_name'] ?? $shippingInfo['ship_last_name'] ?? null,
                'city' => $billingInfo['bill_city'] ?? $shippingInfo['ship_city'] ?? null,
                'state' => $this->stateValue($billingInfo, $shippingInfo, auth()->user()),
                'zip' => $billingInfo['bill_zip'] ?? $shippingInfo['ship_zip'] ?? null,
                'country' => $billingInfo['bill_country'] ?? $shippingInfo['ship_country'] ?? null,
            ]
        ));

        if (!empty($identity)) {
            Session::put('facebook_checkout_identity', $identity);
        }
    }

    private function buildUserData(array $context = []): array
    {
        $billingInfo = $context['billing'] ?? Session::get('billing_address', []);
        $shippingInfo = $context['shipping'] ?? Session::get('shipping_address', []);
        $sessionIdentity = Session::get('facebook_checkout_identity', []);
        $user = $context['user'] ?? auth()->user();
        $email = $context['email'] ?? null;
        $phone = $context['phone'] ?? null;
        $externalId = $context['external_id'] ?? ($user ? 'user_' . $user->id : ($sessionIdentity['email'] ?? $billingInfo['bill_email'] ?? null));

        return array_filter([
            'em' => $this->hashEmail($email ?: ($billingInfo['bill_email'] ?? $shippingInfo['ship_email'] ?? $sessionIdentity['email'] ?? optional($user)->email)),
            'ph' => $this->hashPhone($phone ?: ($billingInfo['bill_phone'] ?? $shippingInfo['ship_phone'] ?? $sessionIdentity['phone'] ?? optional($user)->phone)),
            'fn' => $this->hashText($billingInfo['bill_first_name'] ?? $shippingInfo['ship_first_name'] ?? $sessionIdentity['first_name'] ?? optional($user)->first_name),
            'ln' => $this->hashText($billingInfo['bill_last_name'] ?? $shippingInfo['ship_last_name'] ?? $sessionIdentity['last_name'] ?? optional($user)->last_name),
            'ct' => $this->hashText($billingInfo['bill_city'] ?? $shippingInfo['ship_city'] ?? $sessionIdentity['city'] ?? optional($user)->bill_city ?? optional($user)->ship_city),
            'st' => $this->hashText($this->stateValue($billingInfo, $shippingInfo, $user) ?? ($sessionIdentity['state'] ?? null)),
            'zp' => $this->hashText($billingInfo['bill_zip'] ?? $shippingInfo['ship_zip'] ?? $sessionIdentity['zip'] ?? optional($user)->bill_zip ?? optional($user)->ship_zip),
            'country' => $this->hashCountry($billingInfo['bill_country'] ?? $shippingInfo['ship_country'] ?? $sessionIdentity['country'] ?? optional($user)->bill_country ?? optional($user)->ship_country),
            'external_id' => $this->hashText($externalId),
            'client_ip_address' => $context['client_ip_address'] ?? request()->ip(),
            'client_user_agent' => $context['client_user_agent'] ?? request()->header('User-Agent'),
            'fbp' => $this->fbp(),
            'fbc' => $this->fbc(),
        ]);
    }

    private function fbp(): ?string
    {
        $value = request()->cookie('_fbp');
        if ($value) {
            Session::put('facebook_fbp', $value);

            return $value;
        }

        return Session::get('facebook_fbp');
    }

    private function fbc(): ?string
    {
        $cookieValue = request()->cookie('_fbc');
        if ($cookieValue) {
            Session::put('facebook_fbc', $cookieValue);

            return $cookieValue;
        }

        $fbclid = request()->query('fbclid');
        if ($fbclid) {
            $value = 'fb.1.' . time() . '.' . $fbclid;
            Session::put('facebook_fbc', $value);
            Cookie::queue(cookie('_fbc', $value, 60 * 24 * 90, '/', null, request()->isSecure(), false, false, 'Lax'));

            return $value;
        }

        return Session::get('facebook_fbc');
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

    private function cartItemIdFromKey($key): string
    {
        return (string) explode('-', (string) $key)[0];
    }
}
