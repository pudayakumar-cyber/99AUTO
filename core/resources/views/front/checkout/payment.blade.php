@extends('master.front')
@section('page_type', 'checkout')
@section('title')
    {{ __('Payment') }}
@endsection
@section('content')
    <!-- Page Title-->
    <div class="page-title">
        <div class="container">
            <div class="column">
                <ul class="breadcrumbs">
                    <li><a href="{{ route('front.index') }}">{{ __('Home') }}</a> </li>
                    <li class="separator"></li>
                    <li>{{ __('Review your order and pay') }}</li>
                </ul>
            </div>
        </div>
    </div>
    <!-- Page Content-->
    <div class="container padding-bottom-3x mb-1 checkut-page">
        <div class="row">
            <!-- Payment Methode-->
            <div class="col-xl-9 col-lg-8">
                <div class="steps flex-sm-nowrap mb-5"> <a class="step" href="{{ route('front.checkout.billing') }}">
                        <h4 class="step-title"><i class="icon-check-circle"></i>1. {{ __('Invoice to') }}:</h4>
                    </a> <a class="step" href="{{ route('front.checkout.shipping') }}">
                        <h4 class="step-title"><i class="icon-check-circle"></i>2. {{ __('Ship to') }}:</h4>
                    </a> <a class="step active" href="{{ route('front.checkout.payment') }}">
                        <h4 class="step-title">3. {{ __('Review and pay') }}</h4>
                    </a>
                </div>
                <div class="card">
                    <div class="card-body">
                        <h6 class="pb-2 widget-title2">{{ __('Review Your Order') }} :</h6>
                        
                        <div class="row">
                            <div class="col-sm-6 mb-4">
                                <h6 class="fz-16-bold">{{ __('Invoice address') }} :</h6>
                                @php

                                    $ship = Session::get('shipping_address');
                                    $bill = Session::get('billing_address');
                                @endphp
                                <ul class="list-unstyled">
                                    <li><span class="text-muted pay-label">{{ __('Name') }}:
                                        </span>{{ $ship['ship_first_name'] }} {{ $ship['ship_last_name'] }}</li>
                                    @if (PriceHelper::CheckDigital())
                                        <li><span class="text-muted pay-label">{{ __('Address') }}:
                                            </span>{{ $ship['ship_address1'] }} {{ @$ship['ship_address2'] }}</li>
                                    @endif
                                    <li><span class="text-muted pay-label">{{ __('Phone') }}: </span>{{ $ship['ship_phone'] }}
                                    </li>
                                </ul>
                            </div>
                            <div class="col-sm-6  mb-4">
                                <h6 class="fz-16-bold">{{ __('Shipping address') }} :</h6>
                                <ul class="list-unstyled">
                                    <li><span class="text-muted pay-label">{{ __('Name') }}:
                                        </span>{{ $bill['bill_first_name'] }} {{ $bill['bill_last_name'] }}</li>
                                    @if (PriceHelper::CheckDigital())
                                        <li><span class="text-muted pay-label">{{ __('Address') }}:
                                            </span>{{ $ship['ship_address1'] }} {{ @$ship['ship_address2'] }}</li>
                                    @endif
                                    <li><span class="text-muted pay-label">{{ __('Phone') }}: </span>{{ $bill['bill_phone'] }}
                                    </li>
                                </ul>

                              
                               
                            </div>
                        </div>
                        @if (PriceHelper::CheckDigital() == true)
                        <h6 class="pb-2 widget-title2">{{ __('Shipping Options') }} :</h6>
                        @endif
                        <div class="row">
                            <div class="col-sm-6  mb-4">
                                 @if (PriceHelper::CheckDigital() == true)
                                    
                            
                                    <select name="shipping_id" class="form-control" id="shipping_id_select" required>
                                        <option value="" selected disabled>{{ __('Select Shipping Method') }}</option>
                                        @foreach ($checkout_shipping_services as $shipping)
                                            @if ($shipping->id == 1 && isset($free_shipping) &&  $free_shipping->minimum_price <= $cart_total)
                                                <option value="{{ $shipping->id }}"
                                                    data-title="{{ $shipping->title }}"
                                                    data-price="{{ $shipping->price }}"
                                                    data-href="{{ route('front.shipping.setup') }}">{{ $shipping->title }}
                                                </option>
                                            @else
                                                @if ($shipping->id != 1)
                                                    <option value="{{ $shipping->id }}"
                                                        data-title="{{ $shipping->title }}"
                                                        data-price="{{ $shipping->price }}"
                                                        data-href="{{ route('front.shipping.setup') }}">{{ $shipping->title }}
                                                        ({{ PriceHelper::setCurrencyPrice($shipping->price) }})
                                                    </option>
                                                @endif
                                            @endif
                                        @endforeach
                                    </select>

                                    <small class="text-primary shipping_message">{{ __('Please select shipping method') }}</small>
                                    @error('shipping_id')
                                        <p class="text-danger shipping_message">{{ $message }}</p>
                                    @enderror

                                @endif
                            </div>
                            <div class="col-sm-6  mb-4">
                                @if (PriceHelper::CheckDigital() == true)
                                    
                                
                                @if ($checkout_states->count() > 0)
                                    <select name="state_id" class="form-control" id="state_id_select" required>
                                        <option value="" selected disabled>{{ __('Select Shipping State') }}</option>
                                        @foreach ($checkout_states as $state)
                                            <option value="{{ $state->id }}"
                                                data-href="{{ route('front.state.setup') }}"
                                                {{ Auth::check() && Auth::user()->state_id == $state->id ? 'selected' : '' }}>
                                                {{ $state->name }}
                                                @if ($state->type == 'fixed')
                                                    ({{ PriceHelper::setCurrencyPrice($state->price) }})
                                                @else
                                                    ({{ $state->price }}%)
                                                @endif

                                            </option>
                                        @endforeach
                                    </select>
                                    <small class="text-primary state_message">{{ __('Please select shipping state') }}</small>
                                    @error('state_id')
                                        <p class="text-danger state_message">{{ $message }}</p>
                                    @enderror
                                @endif
                            @endif
                            </div>
                        </div>
                        <h6 class="pb-2 widget-title2">{{ __('Pay With') }} :</h6>
                        <div class="row mt-4">
                            <div class="col-12">
                                <div class="payment-methods">
                                    @foreach ($checkout_payment_gateways as $gateway)
                                        @if (PriceHelper::CheckDigitalPaymentGateway())
                                            @if ($gateway->unique_keyword != 'cod')
                                                <div class="single-payment-method">
                                                    <a class="text-decoration-none " href="#" data-bs-toggle="modal"
                                                        data-bs-target="#{{ $gateway->unique_keyword }}">
                                                        <img class=""
                                                            src="{{ url('/core/public/storage/images/' . $gateway->photo) }}"
                                                            alt="{{ $gateway->name }}" title="{{ $gateway->name }}">
                                                        <p>{{ $gateway->name }}</p>
                                                    </a>
                                                </div>
                                            @endif
                                        @else
                                            @if ($gateway->name == 'Stripe' || $gateway->name == 'Cash On Delivery')
                                                <div class="single-payment-method">
                                                    <a class="text-decoration-none" href="#" data-bs-toggle="modal"
                                                        data-bs-target="#{{ $gateway->unique_keyword }}">
                                                        <img class=""
                                                            src="{{ url('/core/public/storage/images/' . $gateway->photo) }}"
                                                            alt="{{ $gateway->name }}" title="{{ $gateway->name }}">
                                                        <p>{{ $gateway->name }}</p>
                                                    </a>
                                                </div>
                                            @endif
                                        @endif
                                    @endforeach

                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                @include('includes.checkout_modal')

            </div>
            <!-- Sidebar  -->
            <div class="col-xl-3 col-lg-4">
                @include('includes.checkout_sitebar',$cart)
            </div>
        </div>
    </div>

@php
    $checkoutContentIds = [];
    $checkoutItems = [];
    $checkoutNumItems = 0;
    $cartTotal = 0;
    foreach ($cart as $key => $line) {
        $checkoutItemId = (string) ($line['id'] ?? $line['item_id'] ?? \PriceHelper::GetItemId($key) ?? $key);
        $checkoutQty = (int) ($line['qty'] ?? 1);
        $checkoutContentIds[] = $checkoutItemId;
        
        $dbItem = \App\Models\Item::with(['category', 'subcategory', 'childcategory', 'brand'])->find($checkoutItemId);
        $categoryName = $dbItem && $dbItem->category ? $dbItem->category->name : '';
        $subcategoryName = $dbItem && $dbItem->subcategory ? $dbItem->subcategory->name : '';
        $childcategoryName = $dbItem && $dbItem->childcategory ? $dbItem->childcategory->name : '';
        $brandName = $dbItem && $dbItem->brand ? $dbItem->brand->name : '';

        $attributeNames = [];
        if (isset($line['attribute']['option_name']) && is_array($line['attribute']['option_name'])) {
            foreach ($line['attribute']['option_name'] as $optName) {
                $attributeNames[] = $optName;
            }
        }
        $itemVariant = implode(', ', $attributeNames);

        $getFallbackVehicle = function ($item) {
            if (!$item || empty($item->details)) return null;
            $details = html_entity_decode((string) $item->details, ENT_QUOTES, 'UTF-8');
            preg_match_all('/<tr>(.*?)<\/tr>/si', $details, $rows);
            $yearsList = [];
            $makesList = [];
            $modelsList = [];
            $trimsList = [];
            foreach ($rows[1] ?? [] as $rowHtml) {
                preg_match_all('/<td[^>]*>(.*?)<\/td>/si', $rowHtml, $cols);
                if (count($cols[1] ?? []) < 3) {
                    continue;
                }
                [$yearsCell, $makeCell, $modelCell] = array_map(
                    fn ($v) => trim(strip_tags((string) $v)),
                    array_slice($cols[1], 0, 3)
                );
                if ($yearsCell === '' || $makeCell === '' || $modelCell === '') continue;
                
                $lowerYear = strtolower($yearsCell);
                if ($lowerYear === 'year' || $lowerYear === 'years' || $lowerYear === 'fitment') {
                    continue;
                }
                foreach (explode(',', $yearsCell) as $p) {
                    $pTrim = trim($p);
                    if ($pTrim !== '') {
                        $yearsList[] = $pTrim;
                    }
                }
                if ($makeCell !== '') {
                    $makesList[] = $makeCell;
                }
                if ($modelCell !== '') {
                    $modelsList[] = $modelCell;
                }
                if (isset($cols[1][3])) {
                    $trimCell = trim(strip_tags((string) $cols[1][3]));
                    if ($trimCell !== '') {
                        $trimsList[] = $trimCell;
                    }
                }
            }
            $yearsList = array_values(array_unique($yearsList));
            $makesList = array_values(array_unique($makesList));
            $modelsList = array_values(array_unique($modelsList));
            $trimsList = array_values(array_unique($trimsList));
            if (empty($yearsList) && empty($makesList) && empty($modelsList)) {
                return null;
            }
            return [
                'year' => implode(', ', $yearsList),
                'make' => implode(', ', $makesList),
                'model' => implode(', ', $modelsList),
                'trim' => implode(', ', $trimsList)
            ];
        };

        $fallbackVehicle = $getFallbackVehicle($dbItem);

        $checkoutItems[] = [
            'item_id' => $checkoutItemId,
            'item_name' => $line['name'] ?? '',
            'item_brand' => $brandName,
            'item_category' => $categoryName,
            'item_category2' => $subcategoryName,
            'item_category3' => $childcategoryName,
            'item_variant' => $itemVariant,
            'quantity' => $checkoutQty,
            'price' => (float) ($line['main_price'] ?? 0),
            'google_business_vertical' => 'retail',
            'sku' => (string) ($dbItem && $dbItem->sku ? $dbItem->sku : ($dbItem && $dbItem->prod_number ? $dbItem->prod_number : $checkoutItemId)),
            'mpn' => (string) ($dbItem && $dbItem->prod_number ? $dbItem->prod_number : ''),
            'manufacturer' => $brandName,
            'part_typefitment' => (string) ($childcategoryName ?: ($subcategoryName ?: $categoryName)),
            'vehicle_year' => $fallbackVehicle ? (string) $fallbackVehicle['year'] : '',
            'vehicle_make' => $fallbackVehicle ? (string) $fallbackVehicle['make'] : '',
            'vehicle_model' => $fallbackVehicle ? (string) $fallbackVehicle['model'] : '',
            'vehicle_trim' => $fallbackVehicle ? (string) $fallbackVehicle['trim'] : '',
            'part_type' => (string) ($childcategoryName ?: ($subcategoryName ?: $categoryName)),
        ];
        $checkoutNumItems += $checkoutQty;
        $cartTotal += ($line['main_price'] + ($line['attribute_price'] ?? 0)) * $checkoutQty;
    }
    $checkoutValue = (float) ($cartTotal - (Session::has('coupon') ? Session::get('coupon')['discount'] : 0));

    $billingAddress = Session::get('billing_address') ?? [];
    $shippingAddress = Session::get('shipping_address') ?? [];
    
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

    $rawEmail = $billingAddress['bill_email'] ?? $shippingAddress['ship_email'] ?? (Auth::check() ? Auth::user()->email : '');
    $rawPhone = $billingAddress['bill_phone'] ?? $shippingAddress['ship_phone'] ?? (Auth::check() ? Auth::user()->phone : '');
    $rawFirstName = $billingAddress['bill_first_name'] ?? $shippingAddress['ship_first_name'] ?? (Auth::check() ? Auth::user()->first_name : '');
    $rawLastName = $billingAddress['bill_last_name'] ?? $shippingAddress['ship_last_name'] ?? (Auth::check() ? Auth::user()->last_name : '');
    
    $rawStreet = trim(($billingAddress['bill_address1'] ?? '') . ' ' . ($billingAddress['bill_address2'] ?? ''));
    if (empty($rawStreet)) {
        $rawStreet = trim(($shippingAddress['ship_address1'] ?? '') . ' ' . ($shippingAddress['ship_address2'] ?? ''));
    }
    if (empty($rawStreet) && Auth::check() && Auth::user()) {
        $rawStreet = trim((Auth::user()->bill_address1 ?? '') . ' ' . (Auth::user()->bill_address2 ?? '') . ' ' . (Auth::user()->ship_address1 ?? '') . ' ' . (Auth::user()->ship_address2 ?? ''));
    }

    $rawCity = $billingAddress['bill_city'] ?? $shippingAddress['ship_city'] ?? (Auth::check() ? (Auth::user()->bill_city ?? Auth::user()->ship_city) : '');
    $rawRegion = $billingAddress['bill_province'] ?? $shippingAddress['ship_province'] ?? (Auth::check() ? (Auth::user()->bill_province ?? Auth::user()->ship_province) : '');
    $rawPostalCode = $billingAddress['bill_zip'] ?? $shippingAddress['ship_zip'] ?? (Auth::check() ? (Auth::user()->bill_zip ?? Auth::user()->ship_zip) : '');
    $rawCountry = $billingAddress['bill_country'] ?? $shippingAddress['ship_country'] ?? (Auth::check() ? (Auth::user()->bill_country ?? Auth::user()->ship_country) : '');

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
@endphp

@section('script')
<script>
document.addEventListener('DOMContentLoaded', function () {
    var checkoutItems = @json($checkoutItems);
    var customerData = @json($customerData);
    var checkoutValue = {{ $checkoutValue }};

    // Expose to window scope for Stripe Elements script to merge
    window.checkoutItems = checkoutItems;
    window.checkoutCustomerData = customerData;
    window.checkoutValue = checkoutValue;

    function trackShippingInfo(shippingTier, shippingPrice) {
        if (typeof window.paTrack === 'function') {
            window.paTrack('AddShippingInfo', {
                value: checkoutValue,
                currency: 'CAD',
                shipping_tier: shippingTier,
                shipping: parseFloat(shippingPrice || 0),
                customer_data: customerData,
                items: checkoutItems
            }, 'add_shipping_info');
        }
    }

    // Check if shipping method is already selected on page load and track it
    var initialSelectedOption = document.querySelector('#shipping_id_select option:selected') || document.querySelector('#shipping_id_select option[selected]');
    if (!initialSelectedOption) {
        var $selOpt = jQuery('#shipping_id_select option:selected');
        if ($selOpt.length && $selOpt.val() !== "") {
            initialSelectedOption = $selOpt[0];
        }
    }
    if (initialSelectedOption && initialSelectedOption.value !== "") {
        var initialTier = initialSelectedOption.getAttribute('data-title') || initialSelectedOption.textContent.split('(')[0].trim();
        var initialPrice = initialSelectedOption.getAttribute('data-price') || 0;
        trackShippingInfo(initialTier, initialPrice);
    }

    // Listen to changes on shipping method selection
    jQuery(document).on('change', '#shipping_id_select', function () {
        var selectedOption = jQuery(this).find('option:selected');
        if (selectedOption.length && selectedOption.val() !== "") {
            var tier = selectedOption.attr('data-title') || selectedOption.text().split('(')[0].trim();
            var price = selectedOption.attr('data-price') || 0;
            trackShippingInfo(tier, price);
        }
    });

    // Listen to form submit in payment modals to trigger AddPaymentInfo (excluding Stripe)
    jQuery(document).on('submit', '.modal form', function (e) {
        var form = this;
        if (form.id === 'stripe-payment-form') {
            return;
        }

        if (form.paymentInfoTracked) {
            return;
        }

        form.paymentInfoTracked = true;

        var modal = jQuery(form).closest('.modal');
        var gatewayKeyword = modal.attr('id') || 'other';

        var gatewayNames = {
            'paypal': 'PayPal',
            'stripe': 'Stripe',
            'authorize': 'Authorize.net',
            'mollie': 'Mollie',
            'paystack': 'Paystack',
            'razorpay': 'Razorpay',
            'flutterwave': 'Flutterwave',
            'paytm': 'Paytm',
            'sslcommerz': 'SSLCommerz',
            'mercadopago': 'MercadoPago',
            'paytabs': 'Paytabs',
            'cod': 'Cash On Delivery',
            'bank': 'Bank Transfer'
        };
        var paymentType = gatewayNames[gatewayKeyword.toLowerCase()] || gatewayKeyword;

        if (typeof window.paTrack === 'function') {
            window.paTrack('AddPaymentInfo', {
                value: checkoutValue,
                currency: 'CAD',
                payment_type: paymentType,
                customer_data: customerData,
                items: checkoutItems
            }, 'add_payment_info');
        }
    });
});
</script>
@endsection

@endsection
