@extends('master.front')

@section('page_type', 'order_confirmation')

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

    $rawEmail = $billingInfo['bill_email'] ?? $shippingInfo['ship_email'] ?? optional($order->user)->email ?? '';
    $rawPhone = $billingInfo['bill_phone'] ?? $shippingInfo['ship_phone'] ?? optional($order->user)->phone ?? '';
    $rawFirstName = $billingInfo['bill_first_name'] ?? $shippingInfo['ship_first_name'] ?? optional($order->user)->first_name ?? '';
    $rawLastName = $billingInfo['bill_last_name'] ?? $shippingInfo['ship_last_name'] ?? optional($order->user)->last_name ?? '';
    $rawStreet = trim(($billingInfo['bill_address1'] ?? '') . ' ' . ($billingInfo['bill_address2'] ?? ''));
    if (empty($rawStreet)) {
        $rawStreet = trim(($shippingInfo['ship_address1'] ?? '') . ' ' . ($shippingInfo['ship_address2'] ?? ''));
    }
    if (empty($rawStreet) && $order->user) {
        $rawStreet = trim(($order->user->bill_address1 ?? '') . ' ' . ($order->user->bill_address2 ?? '') . ' ' . ($order->user->ship_address1 ?? '') . ' ' . ($order->user->ship_address2 ?? ''));
    }
    $rawCity = $billingInfo['bill_city'] ?? $shippingInfo['ship_city'] ?? optional($order->user)->bill_city ?? optional($order->user)->ship_city ?? '';
    $rawRegion = $billingInfo['bill_province'] ?? $shippingInfo['ship_province'] ?? optional($order->user)->bill_province ?? optional($order->user)->ship_province ?? '';
    $rawPostalCode = $billingInfo['bill_zip'] ?? $shippingInfo['ship_zip'] ?? optional($order->user)->bill_zip ?? optional($order->user)->ship_zip ?? '';
    $rawCountry = $billingInfo['bill_country'] ?? $shippingInfo['ship_country'] ?? optional($order->user)->bill_country ?? optional($order->user)->ship_country ?? '';

    $customerData = array_filter([
        'email' => strtolower(trim($rawEmail)),
        'phone_number' => preg_replace('/\D+/', '', $rawPhone),
        'first_name' => trim($rawFirstName),
        'last_name' => trim($rawLastName),
        'street' => trim($rawStreet),
        'city' => trim($rawCity),
        'region' => trim($rawRegion),
        'postal_code' => trim($rawPostalCode),
        'country' => $googleNormalizeCountry($rawCountry),
    ]);

    $subtotal = 0;
    foreach ($cart as $item) {
        $subtotal += ($item['main_price'] + ($item['attribute_price'] ?? 0)) * $item['qty'];
    }
    $discountInfo = json_decode((string) $order->discount, true) ?: [];
    $subtotal = $subtotal - ($discountInfo['discount'] ?? 0);
    $subtotal = round($subtotal * $order->currency_value, 2);

    $shippingDetails = json_decode((string) $order->shipping, true) ?: [];
    $shippingPrice = (float) ($shippingDetails['price'] ?? 0);
    $shippingPrice = round($shippingPrice * $order->currency_value, 2);
    $shippingTier = $shippingDetails['title'] ?? '';

    $taxVal = (float) ($order->tax ?? 0);
    $stateTaxVal = (float) ($order->state_price ?? 0);
    $totalTax = round(($taxVal + $stateTaxVal) * $order->currency_value, 2);

    $getFallbackVehicle = function ($item) {
        if (!$item || empty($item->details)) return null;
        $details = html_entity_decode((string) $item->details, ENT_QUOTES, 'UTF-8');
        preg_match_all('/<tr>(.*?)<\/tr>/si', $details, $rows);
        foreach ($rows[1] ?? [] as $rowHtml) {
            preg_match_all('/<td[^>]*>(.*?)<\/td>/si', $rowHtml, $cols);
            if (count($cols[1] ?? []) < 3) {
                continue;
            }
            [$yearsCell, $makeCell, $modelCell] = array_map(
                fn ($v) => trim(strip_tags((string) $v)),
                $cols[1]
            );
            if ($yearsCell === '' || $makeCell === '' || $modelCell === '') continue;
            
            $lowerYear = strtolower($yearsCell);
            if ($lowerYear === 'year' || $lowerYear === 'years' || $lowerYear === 'fitment') {
                continue;
            }
            $years = array_values(array_filter(array_map('trim', explode(',', $yearsCell))));
            $firstYear = $years[0] ?? $yearsCell;
            $yearsRange = explode('-', $firstYear);
            $year = trim($yearsRange[0]);

            $trimCell = isset($cols[1][3]) ? trim(strip_tags((string) $cols[1][3])) : '';

            return [
                'year' => $year,
                'make' => $makeCell,
                'model' => $modelCell,
                'trim' => $trimCell
            ];
        }
        return null;
    };

    $purchaseItems = [];
    $itemIds = [];
    foreach ($cart as $key => $item) {
        $itemId = \PriceHelper::GetItemId($key);
        if ($itemId) {
            $itemIds[] = $itemId;
        }
    }
    $purchaseDbItems = \App\Models\Item::with(['category', 'subcategory', 'childcategory', 'brand'])->whereIn('id', $itemIds)->get()->keyBy('id');

    foreach ($cart as $key => $item) {
        $itemId = \PriceHelper::GetItemId($key);
        $dbItem = $purchaseDbItems->get($itemId);
        $categoryName = $dbItem && $dbItem->category ? $dbItem->category->name : '';
        $subcategoryName = $dbItem && $dbItem->subcategory ? $dbItem->subcategory->name : '';
        $childcategoryName = $dbItem && $dbItem->childcategory ? $dbItem->childcategory->name : '';
        $brandName = $dbItem && $dbItem->brand ? $dbItem->brand->name : '';

        $attributeNames = [];
        if (isset($item['attribute']['option_name']) && is_array($item['attribute']['option_name'])) {
            foreach ($item['attribute']['option_name'] as $optName) {
                $attributeNames[] = $optName;
            }
        }
        $itemVariant = implode(', ', $attributeNames);

        $fallbackVehicle = $getFallbackVehicle($dbItem);

        $purchaseItems[] = [
            'item_id' => (string) $itemId,
            'item_name' => $item['name'] ?? '',
            'item_brand' => $brandName,
            'item_category' => (string) $categoryName,
            'item_category2' => (string) $subcategoryName,
            'item_category3' => (string) $childcategoryName,
            'item_variant' => $itemVariant,
            'quantity' => (int) ($item['qty'] ?? 1),
            'price' => (float) ($item['main_price'] ?? $item['price'] ?? 0),
            'google_business_vertical' => 'retail',
            'sku' => (string) ($dbItem && $dbItem->sku ? $dbItem->sku : ($dbItem && $dbItem->prod_number ? $dbItem->prod_number : $itemId)),
            'mpn' => (string) ($dbItem && $dbItem->prod_number ? $dbItem->prod_number : ''),
            'manufacturer' => $brandName,
            'part_typefitment' => (string) ($childcategoryName ?: ($subcategoryName ?: $categoryName)),
            'vehicle_year' => $fallbackVehicle ? (string) $fallbackVehicle['year'] : '',
            'vehicle_make' => $fallbackVehicle ? (string) $fallbackVehicle['make'] : '',
            'vehicle_model' => $fallbackVehicle ? (string) $fallbackVehicle['model'] : '',
            'vehicle_trim' => $fallbackVehicle ? (string) $fallbackVehicle['trim'] : '',
            'part_type' => (string) ($childcategoryName ?: ($subcategoryName ?: $categoryName)),
        ];
    }
@endphp
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (typeof window.paTrack !== 'function') {
        return;
    }

    if (!@json($fire_purchase_event ?? false)) {
        return;
    }

    window.paTrack('Purchase', {
        content_type: 'product',
        content_ids: @json($cart_content_ids),
        value: {{ $subtotal }},
        currency: '{{ $currency }}',
        num_items: {{ $num_items }},
        transaction_id: '{{ $order->transaction_number }}',
        tax: {{ $totalTax }},
        shipping: {{ $shippingPrice }},
        shipping_tier: '{{ $shippingTier }}',
        payment_type: '{{ $order->payment_method }}',
        event_id: '{{ $event_id }}',
        customer_data: @json($customerData),
        items: @json($purchaseItems)
    }, 'purchase', {
        eventId: '{{ $event_id }}',
        googleAdsSendTo: '{{ $googleAdsPurchaseSendTo }}'
    });
});
</script>
@endsection
