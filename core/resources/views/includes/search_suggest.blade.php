
@php
    $resolveSearchImageUrl = function (?string $rawPath): string {
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

<div class="s-r-inner">
    @foreach ($items as $item)
    <div class="product-card p-col">
        <a class="product-thumb" href="{{route('front.product',$item->slug)}}">
            <img class="lazy" alt="{{ $item->name }}" src="{{ $resolveSearchImageUrl($item->thumbnail ?? '') }}" style=""></a>
        <div class="product-card-body">
            <h3 class="product-title"><a href="{{route('front.product',$item->slug)}}">
                {{ Str::limit($item->name, 35) }}
            </a></h3>
            <div class="rating-stars">
                {!! Helper::renderStarRating($item->reviews->avg('rating')) !!}
            </div>
            <h4 class="product-price">
                {{PriceHelper::grandCurrencyPrice($item)}}
            </h4>
        </div>
    </div>
    @endforeach
    
</div>
<!-- <div class="bottom-area">
    <a id="view_all_search_" href="javascript:;">{{ __('View all result') }}</a>
</div> -->