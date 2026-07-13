@php
    $grandSubtotal = 0;
    $qty = 0;
    $option_price = 0;
    $resolveCartImageUrl = function (?string $rawPath): string {
        $rawPath = trim((string) $rawPath);
        if ($rawPath === '') {
            return url('/core/public/storage/images/placeholder.png');
        }

        $pathOnly = parse_url($rawPath, PHP_URL_PATH) ?? $rawPath;
        if (preg_match('~/core/public/storage/images/([^/?#]+)~i', (string) $pathOnly, $m)) {
            return url('/core/public/storage/images/' . $m[1]);
        }
        if (preg_match('~/storage/images/([^/?#]+)~i', (string) $pathOnly, $m)) {
            return url('/core/public/storage/images/' . $m[1]);
        }

        $filename = basename((string) $pathOnly);
        if (trim($filename) === '') {
            return url('/core/public/storage/images/placeholder.png');
        }

        return url('/core/public/storage/images/' . $filename);
    };

    $cartSession = Session::has('cart') && is_array(Session::get('cart')) ? Session::get('cart') : [];
    $itemIds = [];
    foreach ($cartSession as $key => $line) {
        $itemId = \PriceHelper::GetItemId($key);
        if ($itemId) {
            $itemIds[] = $itemId;
        }
    }
    $cartDbItems = count($itemIds)
        ? \App\Models\Item::with(['category', 'subcategory', 'childcategory', 'brand'])->whereIn('id', $itemIds)->get()->keyBy('id')
        : collect();
@endphp
@if (count($cartSession) > 0)
@foreach ($cartSession as $key => $cart)
@php
    $itemId = \PriceHelper::GetItemId($key);
    $dbItem = $cartDbItems->get($itemId);
    $categoryName = $dbItem && $dbItem->category ? $dbItem->category->name : '';
    $subcategoryName = $dbItem && $dbItem->subcategory ? $dbItem->subcategory->name : '';
    $childcategoryName = $dbItem && $dbItem->childcategory ? $dbItem->childcategory->name : '';
    $brandName = $dbItem && $dbItem->brand ? $dbItem->brand->name : '';
    $cartPrice = (float)($cart['main_price'] ?? 0);
    $cartQty = (int)($cart['qty'] ?? 1);

    $attributeNames = [];
    if (isset($cart['attribute']['option_name']) && is_array($cart['attribute']['option_name'])) {
        foreach ($cart['attribute']['option_name'] as $optName) {
            $attributeNames[] = $optName;
        }
    }
    $itemVariant = implode(', ', $attributeNames);

    $cartAttributePrice = (float)($cart['attribute_price'] ?? 0);
    $grandSubtotal += ($cartPrice + $cartAttributePrice) * $cartQty;
@endphp
<div class="entry">
  <div class="entry-thumb"><a href="{{route('front.product',$cart['slug']) . '?item_id=' . $itemId}}"><img src="{{ $resolveCartImageUrl($cart['photo'] ?? '') }}" alt="{{ $cart['name'] }}"></a></div>
  <div class="entry-content">
    <h4 class="entry-title"><a href="{{route('front.product',$cart['slug']) . '?item_id=' . $itemId}}">
        {{ Str::limit($cart['name'], 45) }}
    </a></h4>
    <span class="entry-meta">{{$cartQty}} x {{PriceHelper::setCurrencyPrice($cartPrice)}}</span>
    @foreach (($cart['attribute']['option_name'] ?? []) as $optionkey => $option_name)
    <span class="att"><em>{{ $cart['attribute']['names'][$optionkey] ?? '' }}:</em> {{$option_name}} ({{PriceHelper::setCurrencyPrice($cart['attribute']['option_price'][$optionkey] ?? 0)}})</span>
    @endforeach

 </div>
  <div class="entry-delete">
    <a class="remove-from-cart" href="{{route('front.cart.destroy',$key)}}"
       data-item-id="{{ $itemId }}"
       data-item-name="{{ $cart['name'] ?? '' }}"
       data-item-brand="{{ $brandName }}"
       data-item-category="{{ $categoryName }}"
       data-item-category2="{{ $subcategoryName }}"
       data-item-category3="{{ $childcategoryName }}"
       data-item-price="{{ $cartPrice }}"
       data-item-qty="{{ $cartQty }}"
       data-item-variant="{{ $itemVariant }}">
       <i class="icon-x"></i>
    </a>
  </div>
</div>
@endforeach
<div class="text-right">
<p class="text-gray-dark py-2 mb-0"><span class="text-muted">{{__('Subtotal')}}:</span> {{PriceHelper::setCurrencyPrice($grandSubtotal)}}</p>
</div>
<div class="d-flex justify-content-between">
<div class="w-50 d-block"><a class="btn btn-primary btn-sm  mb-0" href="{{route('front.cart')}}"><span>{{__('Cart')}}</span></a></div>
<div class="w-50 d-block text-end"><a class="btn btn-primary btn-sm  mb-0" href="{{route('front.checkout.billing')}}"><span>{{__('Checkout')}}</span></a></div>
</div>
@else
<div class="cart-empty-state text-center py-4">
    <div class="cart-empty-icon mb-2"><i class="icon-shopping-cart"></i></div>
    <p class="mb-2 text-muted">{{ __('Your cart is empty.') }}</p>
    <a class="btn btn-primary btn-sm" href="{{ route('front.catalog') }}"><span>{{ __('Start Shopping') }}</span></a>
</div>
@endif
