@php
    $cart = Session::has('cart') ? Session::get('cart') : [];
    $total = 0;
    $option_price = 0;
    $cartTotal = 0;
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
@endphp

<div class="card border-0">
    <div class="card-body">
        <div class="table-responsive shopping-cart">
            <table class="table table-bordered">

                <thead>
                    <tr>
                        <th>{{ __('Product Name') }}</th>
                        <th>{{ __('Product Price') }}</th>
                        <th class="text-center">{{ __('Quantity') }}</th>
                        <th class="text-center">{{ __('Subtotal') }}</th>
                        <th class="text-center"><a class="btn btn-sm btn-primary"
                                href="{{ route('front.cart.clear') }}"><span>{{ __('Clear Cart') }}</span></a></th>
                    </tr>
                </thead>

                <tbody id="cart_view_load" data-target="{{ route('cart.get.load') }}">

                    @foreach ($cart as $key => $item)
                        @php
                            
                            $cartTotal += ($item['main_price'] + $total + $item['attribute_price']) * $item['qty'];
                        @endphp
                        <tr>
                            <td>
                                <div class="product-item"><a class="product-thumb"
                                        href="{{ route('front.product', $item['slug']) }}"><img
                                            src="{{ $resolveCartImageUrl($item['photo'] ?? '') }}" alt="{{ $item['name'] }}"></a>
                                    <div class="product-info">
                                        <h4 class="product-title"><a href="{{ route('front.product', $item['slug']) }}">
                                                {{ Str::limit($item['name'], 45) }}

                                            </a></h4>

                                        @foreach ($item['attribute']['option_name'] as $optionkey => $option_name)
                                            <span><em>{{ $item['attribute']['names'][$optionkey] }}:</em>
                                                {{ $option_name }}
                                                ({{ PriceHelper::setCurrencyPrice($item['attribute']['option_price'][$optionkey]) }})</span>
                                        @endforeach
                                    </div>
                                </div>
                            </td>
                            <td class="text-center text-lg">{{ PriceHelper::setCurrencyPrice($item['main_price']) }}
                            </td>

                            <td class="text-center">
                                @if ($item['item_type'] == 'normal')
                                    <div class="qtySelector product-quantity">
                                        <span class="decreaseQtycart cartsubclick" data-id="{{ $key }}"
                                            data-target="{{ PriceHelper::GetItemId($key) }}"><i
                                                class="fas fa-minus"></i></span>
                                        <input type="text" disabled class="qtyValue cartcart-amount"
                                            value="{{ $item['qty'] }}">
                                        <span class="increaseQtycart cartaddclick" data-id="{{ $key }}"
                                            data-target="{{ PriceHelper::GetItemId($key) }}"
                                            data-item="{{ implode(',', $item['options_id']) }}"><i
                                                class="fas fa-plus"></i></span>
                                        <input type="hidden" value="3333" id="current_stock">
                                    </div>
                                @endif

                            </td>
                            <td class="text-center text-lg">
                                {{ PriceHelper::setCurrencyPrice($item['main_price'] * $item['qty']) }}</td>

                            <td class="text-center"><a class="remove-from-cart"
                                    href="{{ route('front.cart.destroy', $key) }}" data-toggle="tooltip"
                                    title="Remove item"><i class="icon-x"></i></a></td>
                        </tr>
                    @endforeach
                    @php
                        $checkoutContentIds = [];
                        $checkoutNumItems = 0;
                        foreach ($cart as $key => $line) {
                            // Adjust if your session keys differ (e.g. $line['item_id'])
                            $checkoutContentIds[] = (string) ($line['id'] ?? $line['item_id'] ?? \PriceHelper::GetItemId($key) ?? $key);
                            $checkoutNumItems += (int) ($line['qty'] ?? 1);
                        }
                        $checkoutValue = (float) ($cartTotal - (Session::has('coupon') ? Session::get('coupon')['discount'] : 0));
                    @endphp

                </tbody>
            </table>
        </div>
    </div>
</div>


<div class="card border-0 mt-4">
    <div class="card-body">
        <div class="shopping-cart-footer">
            <!-- <div class="column">
                <form class="coupon-form" method="post" id="coupon_form" action="{{ route('front.promo.submit') }}">
                    @csrf
                    <input class="form-control form-control-sm" name="code" type="text"
                        placeholder="{{ __('Coupon code') }}" required>
                    <button class="btn btn-primary btn-sm"
                        type="submit"><span>{{ __('Apply Coupon') }}</span></button>
                </form>
            </div> -->

            <div class="text-right text-lg column {{ Session::has('coupon') ? '' : 'd-none' }}"><span
                    class="text-muted">{{ __('Discount') }}
                    ({{ Session::has('coupon') ? Session::get('coupon')['code']['title'] : '' }}) : </span><span
                    class="text-gray-dark">{{ PriceHelper::setCurrencyPrice(Session::has('coupon') ? Session::get('coupon')['discount'] : 0) }}</span>
                    <a class="remove-from-cart btn btn-danger btn-sm "
                                    href="{{ route('front.promo.destroy') }}" data-toggle="tooltip"
                                    title="Remove item"><i class="icon-x"></i></a>
            </div>

            <div class="text-right column text-lg"><span class="text-muted">{{ __('Subtotal') }}: </span><span
                    class="text-gray-dark">{{ PriceHelper::setCurrencyPrice($cartTotal - (Session::has('coupon') ? Session::get('coupon')['discount'] : 0)) }}</span>
            </div>


        </div>
        <div class="shopping-cart-footer">
            <div class="column"><a class="btn btn-primary " href="{{ route('front.catalog') }}"><span><i
                            class="icon-arrow-left"></i> {{ __('Back to Shopping') }}</span></a></div>
            <div class="column">
                <a class="btn btn-primary"
                id="meta-pixel-checkout"
                href="{{ route('front.checkout.billing') }}">
                    <span>{{ __('Checkout') }}</span>
                </a>
            </div>
        </div>
    </div>
</div>
</div>
@if(count($cart) > 0)
<script>
document.addEventListener('DOMContentLoaded', function () {
    var el = document.getElementById('meta-pixel-checkout');
    if (!el) return;

    el.addEventListener('click', function () {
        if (typeof fbq !== 'function') {
            console.error('fbq NOT LOADED (InitiateCheckout)');
            return;
        }

        fbq('track', 'InitiateCheckout', {
            content_type: 'product',
            content_ids: @json($checkoutContentIds),
            num_items: {{ (int) $checkoutNumItems }},
            value: {{ $checkoutValue }},
            currency: 'CAD'
        });
    });
});
</script>
@endif