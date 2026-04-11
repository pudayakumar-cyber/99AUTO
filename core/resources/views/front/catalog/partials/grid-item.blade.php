<div class="col-xxl-3 col-md-4 col-6">
    <div class="product-card" data-fitment-rows='@json($extractItemFitmentRows($item))'>
        @if ($item->is_stock())
            <div class="product-badge
                @if($item->is_type == 'feature')
                bg-warning
                @elseif($item->is_type == 'new')
                bg-danger
                @elseif($item->is_type == 'top')
                bg-info
                @elseif($item->is_type == 'best')
                bg-dark
                @elseif($item->is_type == 'flash_deal')
                bg-success
                @endif
                "> {{ __($item->is_type != 'undefine' ?  (str_replace('_',' ',__("$item->is_type"))) : '') }}
            </div>
        @else
            <div class="product-badge bg-secondary border-default text-body">{{ __('out of stock') }}</div>
        @endif

        @if ($item->previous_price && $item->previous_price != 0)
            <div class="product-badge product-badge2 bg-info">-{{ PriceHelper::DiscountPercentage($item) }}</div>
        @endif
        <div class="product-thumb">
            <img class="lazy" src="{{ $resolveProductImageUrl($item->thumbnail) }}" data-src="{{ $resolveProductImageUrl($item->thumbnail) }}" alt="{{ $item->name }}" loading="lazy">
            <div class="product-button-group">
                <a class="product-button wishlist_store" href="{{ route('user.wishlist.store', $item->id) }}" title="{{ __('Wishlist') }}"><i class="icon-heart"></i></a>
                <a class="product-button product_compare" href="javascript:;" data-target="{{ route('fornt.compare.product', $item->id) }}" title="{{ __('Compare') }}"><i class="icon-repeat"></i></a>
                @include('includes.item_footer', ['sitem' => $item])
            </div>
        </div>
        <div class="product-card-body">
            <div class="product-category">
                <a href="{{ route('front.catalog') . '?category=' . $item->category->slug }}">{{ $item->category->name }}</a>
            </div>
            <h3 class="product-title"><a href="{{ route('front.product', $item->slug) }}">
                {{ Str::limit(collect([
                    optional($item->brand)->name ?: null,
                    $item->product_part_number ?: $item->prod_number ?: null,
                    $item->name,
                ])->filter(fn ($v) => trim((string) $v) !== '')->implode(' - '), 38) }}
            </a></h3>
            <div class="rating-stars">
                {!! Helper::renderStarRating($item->reviews_avg_rating) !!}
            </div>
            <h4 class="product-price">
                @if ($item->previous_price != 0)
                    <del>{{ PriceHelper::setPreviousPrice($item->previous_price) }}</del>
                @endif
                {{ PriceHelper::grandCurrencyPrice($item) }}
            </h4>
            <div class="small mt-1 js-fitment-status d-none" aria-live="polite"></div>
        </div>
    </div>
</div>
