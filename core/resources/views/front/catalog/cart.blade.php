@extends('master.front')
@section('page_type', 'cart')
@section('title')
    {{__('Cart')}}
@endsection
@section('meta')
<meta name="keywords" content="{{$setting->meta_keywords}}">
<meta name="description" content="{{$setting->meta_description}}">
@endsection
@section('content')
    <!-- Page Title-->
<div class="page-title">
    <div class="container">
        <div class="row">
            <div class="col-lg-12">
                <ul class="breadcrumbs">
                    <li><a href="{{route('front.index')}}">{{__('Home')}}</a> </li>
                    <li class="separator"></li>
                    <li>{{__('Cart')}}</li>
                  </ul>
            </div>
        </div>
    </div>
  </div>

  @if(Session::has('cart') && count(Session::get('cart')) > 0)
  <div class="container  padding-bottom-3x mb-1">

    <!-- Shopping Cart-->
    <div id="view_cart_load">
        @include('includes.cart')
    </div>

</div>
  @else
  <div class="container">
    <div class="card text-center">
      <div class="card-body">
        <h3 class="card-title">{{__('Your shopping cart is empty.')}}</h3>
       <a class="btn btn-outline-primary m-4" href="{{route('front.catalog')}}"><i class="icon-package pr-2"></i>{{__('View our products')}}</a></div>
      </div>
    </div>
  @endif
  <!-- Page Content-->

@php
    $cart = Session::has('cart') ? Session::get('cart') : [];
    $itemIds = [];
    foreach ($cart as $key => $line) {
        $itemId = \PriceHelper::GetItemId($key);
        if ($itemId) {
            $itemIds[] = $itemId;
        }
    }
    $cartDbItems = \App\Models\Item::with(['category', 'subcategory', 'childcategory', 'brand'])->whereIn('id', $itemIds)->get()->keyBy('id');
    $cartItems = [];
    $cartValue = 0;
    foreach ($cart as $key => $line) {
        $itemId = \PriceHelper::GetItemId($key);
        $dbItem = $cartDbItems->get($itemId);
        $categoryName = $dbItem && $dbItem->category ? $dbItem->category->name : '';
        $subcategoryName = $dbItem && $dbItem->subcategory ? $dbItem->subcategory->name : '';
        $childcategoryName = $dbItem && $dbItem->childcategory ? $dbItem->childcategory->name : '';
        $brandName = $dbItem && $dbItem->brand ? $dbItem->brand->name : '';
        $cartQty = (int) ($line['qty'] ?? 1);
        $price = (float) ($line['main_price'] ?? 0);

        $attributeNames = [];
        if (isset($line['attribute']['option_name']) && is_array($line['attribute']['option_name'])) {
            foreach ($line['attribute']['option_name'] as $optName) {
                $attributeNames[] = $optName;
            }
        }
        $itemVariant = implode(', ', $attributeNames);

        $cartItems[] = [
            'item_id' => (string)$itemId,
            'item_name' => (string)($line['name'] ?? ''),
            'item_brand' => (string)$brandName,
            'item_category' => (string)$categoryName,
            'item_category2' => (string)$subcategoryName,
            'item_category3' => (string)$childcategoryName,
            'item_variant' => (string)$itemVariant,
            'quantity' => $cartQty,
            'price' => $price,
            'google_business_vertical' => 'retail',
            'sku' => (string) ($dbItem && $dbItem->sku ? $dbItem->sku : ($dbItem && $dbItem->prod_number ? $dbItem->prod_number : $itemId)),
            'mpn' => (string) ($dbItem && $dbItem->prod_number ? $dbItem->prod_number : ''),
            'manufacturer' => (string)$brandName,
            'part_typefitment' => (string) ($childcategoryName ?: ($subcategoryName ?: $categoryName)),
        ];
        $cartValue += $price * $cartQty;
    }
    $cartValue = $cartValue - (Session::has('coupon') ? Session::get('coupon')['discount'] : 0);
@endphp

@section('script')
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (typeof window.paTrack === 'function' && @json(count($cartItems) > 0)) {
        window.paTrack('ViewCart', {
            value: {{ $cartValue }},
            currency: 'CAD',
            items: @json($cartItems)
        }, 'view_cart');
    }
});
</script>
@endsection

@endsection

