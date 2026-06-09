@extends('master.front')

@section('page_type', 'checkout')

@section('title')
    {{ __('Billing') }}
@endsection

@section('content')
    <!-- Page Title-->
    <div class="page-title">
        <div class="container">
            <div class="column">
                <ul class="breadcrumbs">
                    <li><a href="{{ route('front.index') }}">{{ __('Home') }}</a> </li>
                    <li class="separator"></li>
                    <li>{{ __('Billing address') }}</li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Page Content-->
    <div class="container padding-bottom-3x mb-1 checkut-page">
        <div class="row">
            <!-- Billing Adress-->
            <div class="col-xl-9 col-lg-8">
                <div class="steps flex-sm-nowrap mb-5">
                    <a class="step active" href="{{ route('front.checkout.billing') }}">
                        <h4 class="step-title">1. {{ __('Billing Address') }}:</h4>
                    </a>
                    <a class="step" href="javascript:;">
                        <h4 class="step-title">2. {{ __('Shipping Address') }}:</h4>
                    </a>
                    <a class="step" href="{{ route('front.checkout.payment') }}">
                        <h4 class="step-title">3. {{ __('Review and pay') }}</h4>
                    </a>
                </div>
                <div class="card">
                    <div class="card-body">
                        <h6>{{ __('Billing Address') }}</h6>

                        <form id="checkoutBilling" action="{{ route('front.checkout.store') }}" method="POST">
                            @csrf
                            <div class="row">
                                <div class="col-sm-6">
                                    <div class="form-group">
                                        <label for="checkout-fn">{{ __('First Name') }}*</label>
                                        <input class="form-control {{ $errors->has('bill_first_name') ? 'requireInput' : '' }}" name="bill_first_name" type="text" 
                                            id="checkout-fn" value="{{ isset($user) ? $user->first_name : '' }}">
                                    </div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="form-group">
                                        <label for="checkout-ln">{{ __('Last Name') }}*</label>
                                        <input class="form-control {{ $errors->has('bill_last_name') ? 'requireInput' : '' }}" name="bill_last_name" type="text" 
                                            id="checkout-ln" value="{{ isset($user) ? $user->last_name : '' }}">
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-sm-6">
                                    <div class="form-group">
                                        <label for="checkout_email_billing">{{ __('E-mail Address') }}*</label>
                                        <input class="form-control {{ $errors->has('bill_email') ? 'requireInput' : '' }}" name="bill_email" type="email" 
                                            id="checkout_email_billing" value="{{ isset($user) ? $user->email : '' }}">
                                    </div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="form-group">
                                        <label for="checkout-phone">{{ __('Phone Number') }}*</label>
                                        <input class="form-control {{ $errors->has('bill_phone') ? 'requireInput' : '' }}" name="bill_phone" type="tel" id="checkout-phone"
                                             value="{{ isset($user) ? $user->phone : '' }}">
                                    </div>
                                </div>
                            </div>
                            @if (PriceHelper::CheckDigital())
                                
                                <div class="row">
                                    <div class="col-sm-12">
                                        <div class="form-group">
                                            <label for="checkout-address1">{{ __('Address') }}*</label>
                                            <input class="form-control {{ $errors->has('bill_address1') ? 'requireInput' : '' }}" name="bill_address1"  type="text"
                                                id="checkout-address1"
                                                value="{{ isset($user) ? $user->bill_address1 : '' }}">
                                        </div>
                                    </div>
                                    
                                </div>
                                <div class="row">
                                    <div class="col-sm-6">
                                        <div class="form-group">
                                            <label for="checkout-zip">{{ __('Zip Code') }}*</label>
                                            <input class="form-control {{ $errors->has('bill_zip') ? 'requireInput' : '' }}" name="bill_zip" type="text" id="checkout-zip"
                                                value="{{ isset($user) ? $user->bill_zip : '' }}">
                                        </div>
                                    </div>
                                    <div class="col-sm-6">
                                        <div class="form-group">
                                            <label for="checkout-city">{{ __('City') }}*</label>
                                            <input class="form-control {{ $errors->has('bill_city') ? 'requireInput' : '' }}" name="bill_city" type="text" 
                                                id="checkout-city" value="{{ isset($user) ? $user->bill_city : '' }}">
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-sm-12">
                                        <div class="form-group">
                                            <label for="checkout-province">{{ __('Province / State') }}*</label>
                                            <input class="form-control {{ $errors->has('bill_province') ? 'requireInput' : '' }}" name="bill_province" type="text"
                                                id="checkout-province" value="{{ isset($user) ? $user->bill_province : '' }}">
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-sm-12">
                                        <div class="form-group">
                                            <label for="billing-country">{{ __('Country') }}</label>
                                            <select class="form-control {{ $errors->has('bill_country') ? 'requireInput' : '' }}"  name="bill_country"
                                                id="billing-country">
                                                <option selected>{{ __('Choose Country') }}</option>
@foreach ($checkout_countries as $country)
                                                   <option value="{{ $country->name }}"
                                                        {{ isset($user) && $user->bill_country == $country->name ? 'selected' : '' }}>
                                                        {{ $country->name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            @endif

                            <div class="form-group">
                                <div class="custom-control custom-checkbox">
                                    <input class="custom-control-input" type="checkbox" id="same_address"
                                        name="same_ship_address" {{ Session::has('shipping_address') ? 'checked' : '' }}>
                                    <label class="custom-control-label"
                                        for="same_address">{{ __('Same as billing address') }}</label>
                                </div>
                            </div>

                            @if ($setting->is_privacy_trams == 1)
                                <div class="form-group">
                                    <div class="custom-control custom-checkbox">
                                        <input class="custom-control-input" type="checkbox" id="trams__condition">
                                        <label class="custom-control-label" for="trams__condition">This site is protected
                                            by reCAPTCHA and the <a href="{{ $setting->policy_link }}"
                                                target="_blank">Privacy Policy</a> and <a
                                                href="{{ $setting->terms_link }}" target="_blank">Terms of Service</a>
                                            apply.</label>
                                    </div>
                                </div>
                            @endif

                            <div class="d-flex justify-content-between paddin-top-1x mt-4">
                                <a class="btn btn-primary btn-sm" href="{{ route('front.cart') }}"><span
                                        class="hidden-xs-down"><i
                                            class="icon-arrow-left"></i>{{ __('Back To Cart') }}</span></a>
                                @if ($setting->is_privacy_trams == 1)
                                    <button disabled id="continue__button" class="btn btn-primary  btn-sm"
                                        type="button"><span class="hidden-xs-down">{{ __('Continue') }}</span><i
                                            class="icon-arrow-right"></i></button>
                                @else
                                    <button class="btn btn-primary btn-sm" type="submit"><span
                                            class="hidden-xs-down">{{ __('Continue') }}</span><i
                                            class="icon-arrow-right"></i></button>
                                @endif
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <!-- Sidebar  -->
            <div class="col-xl-3 col-lg-4">
                @include('includes.checkout_sitebar', $cart)
            </div>
        </div>
    </div>

@php
    $checkoutContentIds = [];
    $checkoutItems = [];
    $checkoutNumItems = 0;
    $cartTotal = 0;

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
@endphp

@section('script')
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (typeof window.paTrack === 'function') {
        window.paTrack('InitiateCheckout', {
            content_type: 'product',
            content_ids: @json($checkoutContentIds),
            num_items: {{ (int) $checkoutNumItems }},
            value: {{ $checkoutValue }},
            currency: 'CAD',
            items: @json($checkoutItems)
        }, 'begin_checkout', { eventId: '{{ Session::get('checkout_event_id') }}' });
    }

    function googleNormalizeCountry(value) {
        if (!value) return '';
        var val = value.toLowerCase().trim();
        var countries = {
            'canada': 'CA',
            'ca': 'CA',
            'united states': 'US',
            'united states of america': 'US',
            'usa': 'US',
            'us': 'US'
        };
        return countries[val] || value.toUpperCase().trim();
    }

    jQuery('#checkoutBilling').on('submit', function (e) {
        var sameAddress = jQuery('#same_address').is(':checked');
        if (!sameAddress) {
            return;
        }

        var form = this;
        if (form.shippingInfoTracked) {
            return;
        }

        var email = jQuery('#checkout_email_billing').val() || '';
        var phone = jQuery('#checkout-phone').val() || '';
        var firstName = jQuery('#checkout-fn').val() || '';
        var lastName = jQuery('#checkout-ln').val() || '';
        var address1 = jQuery('#checkout-address1').val() || '';
        var city = jQuery('#checkout-city').val() || '';
        var zip = jQuery('#checkout-zip').val() || '';
        var province = jQuery('#checkout-province').val() || '';
        var country = jQuery('#billing-country').val() || '';

        if (!email.trim() || !phone.trim() || !firstName.trim() || !lastName.trim() || !address1.trim() || !city.trim() || !zip.trim()) {
            return;
        }

        e.preventDefault();

        var customerData = {
            email: email.toLowerCase().trim(),
            phone_number: phone.replace(/\D+/g, ''),
            first_name: firstName.trim(),
            last_name: lastName.trim(),
            street: address1.trim(),
            city: city.trim(),
            region: province.trim(),
            postal_code: zip.trim(),
            country: googleNormalizeCountry(country)
        };

        if (typeof window.paTrack === 'function') {
            window.paTrack('AddShippingInfo', {
                value: {{ $checkoutValue }},
                currency: 'CAD',
                customer_data: customerData,
                items: @json($checkoutItems)
            }, 'add_shipping_info');
        }

        form.shippingInfoTracked = true;
        setTimeout(function () {
            form.submit();
        }, 200);
    });
});
</script>
@endsection

@endsection
