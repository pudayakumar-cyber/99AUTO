@extends('master.front')

@section('title')
    {{ __('Order Success') }}
@endsection

@section('content')
    <!-- Page Title-->
    <div class="page-title">
        <div class="container">
            <div class="column">
                <ul class="breadcrumbs">
                    <li><a href="{{ route('front.index') }}">{{ __('Home') }}</a> </li>
                    <li class="separator"></li>
                    <li>{{ __('Success') }}</li>
                </ul>
            </div>
        </div>
    </div>
    <!-- Page Content-->
    <div class="container padding-bottom-3x mb-1">
        <div class="card text-center">
            <div class="card-body">
                <h3 class="card-title text-success">{{ __('Thank you for your order') }}!</h3>
                <p class="card-text">{{ __('Your order has been placed and will be processed as soon as possible.') }}</p>
                <p class="card-text">{{ __('Make sure you make note of your order number, which is') }} <span
                        class="text-medium">{{ $order->transaction_number }}</span></p>
                <p class="card-text">{{ __('You will be receiving an email shortly with confirmation of your order.') }}

                </p>
                <div class="padding-top-1x padding-bottom-1x">

                    <a class="btn btn-primary m-4" href="{{ route('front.catalog') }}"><span><i
                                class="icon-package pr-2"></i> {{ __('View our products again') }}</span></a>

                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
@php
    $googleAdsPurchaseSendTo = 'AW-' . config('services.google.ads_conversion_id') . '/' . config('services.google.ads_purchase_label');
    $billingInfo = json_decode((string) $order->billing_info, true) ?: [];
    $shippingInfo = json_decode((string) $order->shipping_info, true) ?: [];
    $googleNormalize = function ($value) {
        $value = preg_replace('/\s+/', '', strtolower(trim((string) $value)));
        return $value === '' ? null : hash('sha256', $value);
    };
    $googleNormalizeEmail = function ($value) {
        $value = strtolower(trim((string) $value));
        return $value === '' ? null : hash('sha256', $value);
    };
    $googleNormalizePhone = function ($value) {
        $value = preg_replace('/\D+/', '', (string) $value);
        return $value === '' ? null : hash('sha256', $value);
    };
    $googleNormalizeCountry = function ($value) {
        $value = strtolower(trim((string) $value));
        $countries = [
            'canada' => 'ca',
            'ca' => 'ca',
            'united states' => 'us',
            'united states of america' => 'us',
            'usa' => 'us',
            'us' => 'us',
        ];
        return strtoupper($countries[$value] ?? $value);
    };
    $googleEnhancedConversionData = array_filter([
        'sha256_email_address' => $googleNormalizeEmail($billingInfo['bill_email'] ?? $shippingInfo['ship_email'] ?? optional($order->user)->email),
        'sha256_phone_number' => $googleNormalizePhone($billingInfo['bill_phone'] ?? $shippingInfo['ship_phone'] ?? optional($order->user)->phone),
        'address' => array_filter([
            'sha256_first_name' => $googleNormalize($billingInfo['bill_first_name'] ?? $shippingInfo['ship_first_name'] ?? optional($order->user)->first_name),
            'sha256_last_name' => $googleNormalize($billingInfo['bill_last_name'] ?? $shippingInfo['ship_last_name'] ?? optional($order->user)->last_name),
            'city' => $billingInfo['bill_city'] ?? $shippingInfo['ship_city'] ?? optional($order->user)->bill_city ?? optional($order->user)->ship_city,
            'region' => $billingInfo['bill_province'] ?? $shippingInfo['ship_province'] ?? optional($order->user)->bill_province ?? optional($order->user)->ship_province,
            'postal_code' => $billingInfo['bill_zip'] ?? $shippingInfo['ship_zip'] ?? optional($order->user)->bill_zip ?? optional($order->user)->ship_zip,
            'country' => $googleNormalizeCountry($billingInfo['bill_country'] ?? $shippingInfo['ship_country'] ?? optional($order->user)->bill_country ?? optional($order->user)->ship_country),
        ]),
    ]);
    $purchaseItems = [];
    foreach ($cart as $key => $item) {
        $purchaseItems[] = [
            'item_id' => (string) ($item['id'] ?? $item['item_id'] ?? $key),
            'item_name' => $item['name'] ?? '',
            'quantity' => (int) ($item['qty'] ?? 1),
            'price' => (float) ($item['main_price'] ?? $item['price'] ?? 0),
        ];
    }
@endphp
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (typeof window.paTrack !== 'function') {
        return;
    }

    if (typeof window.gtag === 'function' && Object.keys(@json($googleEnhancedConversionData)).length) {
        window.gtag('set', 'user_data', @json($googleEnhancedConversionData));
    }

    window.paTrack('Purchase', {
        content_type: 'product',
        content_ids: @json($cart_content_ids),
        value: {{ $order_value }},
        currency: '{{ $currency }}',
        num_items: {{ $num_items }},
        transaction_id: '{{ $order->transaction_number }}',
        event_id: '{{ $event_id }}',
        items: @json($purchaseItems)
    }, 'purchase', {
        eventId: '{{ $event_id }}',
        googleAdsSendTo: '{{ $googleAdsPurchaseSendTo }}',
        forceGoogleDirect: true
    });
});
</script>
@endsection
