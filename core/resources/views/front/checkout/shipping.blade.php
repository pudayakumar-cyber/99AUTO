@extends('master.front')

@section('page_type', 'checkout')

@section('title')
    {{ __('Shipping') }}
@endsection
@section('content')
    <!-- Page Title-->
    <div class="page-title">
        <div class="container">
            <div class="column">
                <ul class="breadcrumbs">
                    <li><a href="{{ route('front.index') }}">{{ __('Home') }}</a> </li>
                    <li class="separator"></li>
                    <li>{{ __('Shipping address') }}</li>
                </ul>
            </div>
        </div>
    </div>
    <!-- Page Content-->
    <div class="container padding-bottom-3x mb-1  checkut-page">
        <div class="row">
            <!-- Shipping Adress-->
            <div class="col-xl-9 col-lg-8">
                <div class="steps flex-sm-nowrap mb-5">
                    <a class="step" href="{{ route('front.checkout.billing') }}">
                        <h4 class="step-title">1. {{ __('Billing Address') }}:</h4>
                    </a>
                    <a class="step active" href="javascript:;">
                        <h4 class="step-title">2. {{ __('Shipping Address') }}:</h4>
                    </a>
                    <a class="step" href="{{ route('front.checkout.payment') }}">
                        <h4 class="step-title">3. {{ __('Review and pay') }}</h4>
                    </a>
                </div>
                <div class="card">
                    <div class="card-body">
                        <h6>{{ __('Shipping Address') }}</h6>

                        <form id="checkoutShipping" action="{{ route('front.checkout.shipping.store') }}" method="POST">
                            @csrf
                            <div class="row">
                                <div class="col-sm-6">
                                    <div class="form-group">
                                        <label for="checkout-fn">{{ __('First Name') }}*</label>
                                        <input class="form-control {{ $errors->has('ship_first_name') ? 'requireInput' : '' }}" name="ship_first_name" type="text" id="checkout-fn"
                                            value="{{ isset($user) ? $user->first_name : '' }}">
                                    </div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="form-group">
                                        <label for="checkout-ln">{{ __('Last Name') }}*</label>
                                        <input class="form-control {{ $errors->has('ship_last_name') ? 'requireInput' : '' }}" name="ship_last_name" type="text" id="checkout-ln"
                                            value="{{ isset($user) ? $user->last_name : '' }}">
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-sm-6">
                                    <div class="form-group">
                                        <label for="checkout-email">{{ __('E-mail Address') }}*</label>
                                        <input class="form-control {{ $errors->has('ship_email') ? 'requireInput' : '' }}" name="ship_email" type="email" id="checkout-email"
                                            value="{{ isset($user) ? $user->email : '' }}">
                                    </div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="form-group">
                                        <label for="checkout-phone">{{ __('Phone Number') }}*</label>
                                        <input class="form-control {{ $errors->has('ship_phone') ? 'requireInput' : '' }}" name="ship_phone" type="tel" id="checkout-phone"
                                            value="{{ isset($user) ? $user->phone : '' }}">
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-sm-12">
                                    <div class="form-group">
                                        <label for="checkout-address1">{{ __('Address') }} *</label>
                                        <input class="form-control {{ $errors->has('ship_address1') ? 'requireInput' : '' }}" name="ship_address1"  type="text"
                                            id="checkout-address1" value="{{ isset($user) ? $user->ship_address1 : '' }}">
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-sm-6">
                                    <div class="form-group">
                                        <label for="checkout-zip">{{ __('Zip Code') }} *</label>
                                        <input class="form-control {{ $errors->has('ship_zip') ? 'requireInput' : '' }}" name="ship_zip" type="text" id="checkout-zip"
                                            value="{{ isset($user) ? $user->ship_zip : '' }}">
                                    </div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="form-group">
                                        <label for="checkout-city">{{ __('City') }} *</label>
                                        <input class="form-control {{ $errors->has('ship_city') ? 'requireInput' : '' }}" name="ship_city" type="text"
                                            id="checkout-city" value="{{ isset($user) ? $user->ship_city : '' }}">
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-sm-12">
                                    <div class="form-group">
                                        <label for="shipping-province">{{ __('Province / State') }} *</label>
                                        <input class="form-control {{ $errors->has('ship_province') ? 'requireInput' : '' }}" name="ship_province" type="text"
                                            id="shipping-province" value="{{ isset($user) ? $user->ship_province : '' }}">
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-sm-12">
                                    <div class="form-group">
                                        <label for="shipping-country">{{ __('Country') }}</label>
                                        <select class="form-control" name="ship_country"  id="shipping-country">
                                            <option selected>{{ __('Choose Country') }}</option>
                                            @foreach ($checkout_countries as $country)
                                                <option value="{{ $country->name }}"
                                                    {{ isset($user) && $user->ship_country == $country->name ? 'selected' : '' }}>
                                                    {{ $country->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                            </div>


                            <div class="d-flex justify-content-between paddin-top-1x mt-4">
                                <a class="btn btn-primary btn-sm" href="{{ route('front.cart') }}"><span
                                        class="hidden-xs-down"><i class="icon-arrow-left"></i>
                                        {{ __('Back To Cart') }}</span>
                                </a><button class="btn btn-primary  btn-sm" type="submit"><span
                                        class="hidden-xs-down">{{ __('Continue') }}</span><i
                                        class="icon-arrow-right"></i></button>
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
    foreach ($cart as $key => $line) {
        $checkoutItemId = (string) ($line['id'] ?? $line['item_id'] ?? \PriceHelper::GetItemId($key) ?? $key);
        $checkoutQty = (int) ($line['qty'] ?? 1);
        $checkoutContentIds[] = $checkoutItemId;
        
        $dbItem = \App\Models\Item::with(['category', 'subcategory', 'childcategory', 'brand'])->find($checkoutItemId);
        $categoryName = $dbItem && $dbItem->category ? $dbItem->category->name : '';
        $subcategoryName = $dbItem && $dbItem->subcategory ? $dbItem->subcategory->name : '';
        $childcategoryName = $dbItem && $dbItem->childcategory ? $dbItem->childcategory->name : '';
        $brandName = $dbItem && $dbItem->brand ? $dbItem->brand->name : '';

        $checkoutItems[] = [
            'item_id' => $checkoutItemId,
            'item_name' => $line['name'] ?? '',
            'item_brand' => $brandName,
            'item_category' => $categoryName,
            'item_category2' => $subcategoryName,
            'item_category3' => $childcategoryName,
            'quantity' => $checkoutQty,
            'price' => (float) ($line['main_price'] ?? 0),
            'google_business_vertical' => 'retail',
            'sku' => (string) ($dbItem && $dbItem->sku ? $dbItem->sku : ($dbItem && $dbItem->prod_number ? $dbItem->prod_number : $checkoutItemId)),
            'mpn' => (string) ($dbItem && $dbItem->prod_number ? $dbItem->prod_number : ''),
            'manufacturer' => $brandName,
            'part_typefitment' => (string) ($childcategoryName ?: ($subcategoryName ?: $categoryName)),
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
        window.paTrack('AddShippingInfo', {
            value: {{ $checkoutValue }},
            currency: 'CAD',
            items: @json($checkoutItems)
        }, 'add_shipping_info');
    }
});
</script>
@endsection

@endsection
