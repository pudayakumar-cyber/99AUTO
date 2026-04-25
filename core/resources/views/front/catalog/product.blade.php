@extends('master.front')

@section('title')
    {{ $item->name }}
@endsection

@php
    $displayProductName = collect([
        optional($item->brand)->name ?: null,
        $item->product_part_number ?: $item->prod_number ?: null,
        $item->name,
    ])->filter(fn ($v) => trim((string) $v) !== '')->implode(' - ');

    $resolveProductImageUrl = function (?string $rawPath): string {
        $rawPath = trim((string) $rawPath);
        if ($rawPath === '') {
            return url('/core/public/storage/images/placeholder.png');
        }

        // If thumbnail is already an absolute URL or contains directories, extract filename safely.
        $pathOnly = parse_url($rawPath, PHP_URL_PATH) ?? $rawPath;

        // Prefer extracting from known storage paths.
        // Note: use '~' delimiter so '#' inside the pattern doesn't break PCRE parsing.
        if (preg_match('~/core/public/storage/images/([^/?#]+)~i', (string) $pathOnly, $m)) {
            return url('/core/public/storage/images/' . $m[1]);
        }
        if (preg_match('~/storage/images/([^/?#]+)~i', (string) $pathOnly, $m)) {
            return url('/core/public/storage/images/' . $m[1]);
        }

        // Plain filename or any other relative string.
        $filename = basename((string) $pathOnly);
        if (trim($filename) === '') {
            return url('/core/public/storage/images/placeholder.png');
        }

        return url('/core/public/storage/images/' . $filename);
    };

    $resolveProductImageFallbackUrl = function (?string $rawPath): string {
        $rawPath = trim((string) $rawPath);
        if ($rawPath === '') {
            return url('/core/public/storage/images/placeholder.png');
        }

        $pathOnly = parse_url($rawPath, PHP_URL_PATH) ?? $rawPath;

        // Fallback prefers the non-core public path (if your host serves it).
        if (preg_match('~/storage/images/([^/?#]+)~i', (string) $pathOnly, $m)) {
            return url('/storage/images/' . $m[1]);
        }
        if (preg_match('~/core/public/storage/images/([^/?#]+)~i', (string) $pathOnly, $m)) {
            return url('/storage/images/' . $m[1]);
        }

        $filename = basename((string) $pathOnly);
        if (trim($filename) === '') {
            return url('/core/public/storage/images/placeholder.png');
        }

        return url('/storage/images/' . $filename);
    };
@endphp


@section('meta')
    <meta name="tile" content="{{ $item->title }}">
    <meta name="keywords" content="{{ $item->meta_keywords }}">
    <meta name="description" content="{{ $item->meta_description }}">

    <meta name="twitter:title" content="{{ $item->title }}">
    <meta name="twitter:image" content="{{ $resolveProductImageUrl($item->photo) }}">
    <meta name="twitter:description" content="{{ $item->meta_description }}">

    <meta name="og:title" content="{{ $item->title }}">
    <meta name="og:image" content="{{ $resolveProductImageUrl($item->photo) }}">
    <meta name="og:description" content="{{ $item->meta_description }}">

    {{-- ✅ META PIXEL VIEW CONTENT --}}
    @if($item->is_stock())
    <script>
      document.addEventListener('DOMContentLoaded', function () {
        if (typeof fbq === 'function') {
          console.log('🔥 ViewContent fired');

          fbq('track', 'ViewContent', {
            content_type: 'product',
            content_ids: [{!! json_encode((string)($item->id ?? $item->prod_number ?? '')) !!}],
            content_name: {!! json_encode($item->name ?? '') !!},
            content_category: {!! json_encode(optional($item->category)->name ?? '') !!},
            value: {{ (float) ($item->discount_price ?? $item->previous_price ?? 0) }},
            currency: 'CAD'
          });

        } else {
          console.error('❌ fbq NOT LOADED (ViewContent)');
        }
      });
    </script>
    @endif
@endsection

@section('styleplugins')
    <style>
        .pa-vehicle-fitment .pa-ymm-bar select:disabled {
            opacity: 0.65;
        }
        span.input-group-btn{
            margin-top: 10px!important;
        }
        
        input.form-control{
            margin-top: 7px;
        }
        .pa-highlight-card {
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.06);
        }
        .pa-highlight-icon {
            width: 2.25rem;
            height: 2.25rem;
            font-size: 0.95rem;
        }
        .pa-highlight-bullets li {
            margin-bottom: 0.15rem;
        }
        .pa-details-body {
            font-size: inherit;
        }
        .pa-details-body .pa-fitment-table,
        .pa-details-body table {
            width: 100%;
        }
        .pa-product-accordion .accordion-button:not(.collapsed) {
            box-shadow: none;
        }
        .pa-product-accordion .accordion-body {
            padding-top: 0.75rem;
        }
        .pa-fitment-status {
            display: none;
            margin-top: 0.75rem;
            padding: 0.65rem 0.85rem;
            border: 1px solid #e7b9bc;
            border-radius: 0.35rem;
            background: #fff7f7;
            color: #bf2026;
            font-weight: 600;
            line-height: 1.35;
        }
        .pa-fitment-status i {
            margin-right: 0.4rem;
        }
        .pa-fitment-status.is-fit {
            border-color: #b8ddc0;
            background: #f5fff7;
            color: #1f7a34;
        }
        .pa-fitment-status-top {
            margin: 0.35rem 0 0.85rem;
        }

        @media (min-width: 768px) {
            span.input-group-btn{
                margin-top: 5px;
            }
        }
    </style>
@endsection

<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.ewa-rteLine').forEach(function (el) {
        if (!el.textContent.trim() && el.children.length === 0) {
            el.style.display = 'none';
        }
    });
});
</script>

@section('content')
    <div class="page-title">
        <div class="container">
            <div class="row">
                <div class="col-lg-12">
                    <ul class="breadcrumbs">
                        <li><a href="{{ route('front.index') }}">{{ __('Home') }}</a>
                        </li>
                        <li class="separator"></li>
                        <li><a href="{{ route('front.catalog') }}">{{ __('Shop') }}</a>
                        </li>
                        <li class="separator"></li>
                        <li>{{ $displayProductName }}</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
    <!-- Page Content-->
    <div class="container padding-bottom-1x mb-1">
        <div class="row">
            <!-- Poduct Gallery-->
            <div class="col-xxl-5 col-lg-6 col-md-6">
                <div class="product-gallery">
                    @if ($item->video)
                        <div class="gallery-wrapper">
                            <div class="gallery-item video-btn text-center">
                                <a href="{{ $item->video }}" title="Watch video"></a>
                            </div>
                        </div>
                    @endif
                    @if ($item->is_stock())
                        <span
                            class="product-badge
                        @if ($item->is_type == 'feature') bg-warning
                        @elseif($item->is_type == 'new')
                        bg-success
                        @elseif($item->is_type == 'top')
                        bg-info
                        @elseif($item->is_type == 'best')
                        bg-dark
                        @elseif($item->is_type == 'flash_deal')
                            bg-success @endif
                        ">{{ __($item->is_type != 'undefine' ? ucfirst(str_replace('_', ' ', $item->is_type)) : '') }}</span>
                    @else
                        <span class="product-badge bg-secondary border-default text-body">{{ __('out of stock') }}</span>
                    @endif

                    @if (PriceHelper::showPreviousPrice($item))
                        <div class="product-badge bg-goldenrod  ppp-t"> -{{ PriceHelper::DiscountPercentage($item) }}</div>
                    @endif

                    <div class="product-thumbnails insize">
                        <div class="product-details-slider owl-carousel">
                            <div class="item"><img src="{{ $resolveProductImageUrl($item->photo) }}"
                                    onerror="if(!this.dataset.fallbackDone){this.dataset.fallbackDone=1;this.src='{{ $resolveProductImageFallbackUrl($item->photo) }}';}"
                                    alt="{{ $displayProductName }}" />
                            </div>
                            @foreach ($galleries as $key => $gallery)
                                <div class="item"><img src="{{ $resolveProductImageUrl($gallery->photo) }}"
                                        onerror="if(!this.dataset.fallbackDone){this.dataset.fallbackDone=1;this.src='{{ $resolveProductImageFallbackUrl($gallery->photo) }}';}"
                                        alt="{{ $displayProductName }}" /></div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
            <!-- Product Info-->
            <div class="col-xxl-7 col-lg-6 col-md-6">
                <div class="details-page-top-right-content d-flex align-items-center">
                    <div class="div w-100">
                        <input type="hidden" id="item_id" value="{{ $item->id }}">
                        <input type="hidden" id="demo_price"
                            value="{{ PriceHelper::setConvertPrice($item->discount_price) }}">
                        <input type="hidden" value="{{ PriceHelper::setCurrencySign() }}" id="set_currency">
                        <input type="hidden" value="{{ PriceHelper::setCurrencyValue() }}" id="set_currency_val">
                        <input type="hidden" value="{{ $setting->currency_direction }}" id="currency_direction">
                        <h4 class="mb-2 p-title-main">{{ $displayProductName }}</h4>
                        <div class="mb-3">
                            <div class="rating-stars d-inline-block gmr-3">
                                {!! Helper::renderStarRating($item->reviews_avg_rating) !!}
                            </div>
                            @if ($item->is_stock())
                                <span class="text-success  d-inline-block">{{ __('In Stock') }} <b>({{ $item->stock }}
                                        @lang('items'))</b></span>
                            @else
                                <span class="text-danger  d-inline-block">{{ __('Out of stock') }}</span>
                            @endif
                        </div>
                        <div id="product_fitment_status_top" class="pa-fitment-status pa-fitment-status-top" role="alert"></div>


                        @if ($item->is_type == 'flash_deal')
                            @if (date('d-m-y') != \Carbon\Carbon::parse($item->date)->format('d-m-y'))
                                <div class="countdown countdown-alt mb-3" data-date-time="{{ $item->date }}">
                                </div>
                            @endif
                        @endif

                        <span class="h3 d-block price-area">
                            @if (PriceHelper::showPreviousPrice($item))
                                <small
                                    class="d-inline-block"><del>{{ PriceHelper::setPreviousPrice($item->previous_price) }}</del></small>
                            @endif
                            <span id="main_price" class="main-price">{{ PriceHelper::grandCurrencyPrice($item) }}</span>
                        </span>

                        <p class="text-muted">{!! html_entity_decode($item->sort_details) !!} <a href="#details"
                                class="scroll-to">{{ __('Read more') }}</a></p>

                        <div class="row margin-top-1x">
                            @foreach ($attributes as $attribute)
                                @if ($attribute->options->count() != 0)
                                    <div class="col-sm-6">
                                        <div class="form-group">
                                            <label for="{{ $attribute->name }}">{{ $attribute->name }}</label>
                                            <select class="form-control attribute_option" id="{{ $attribute->name }}">
                                                @foreach ($attribute->options->where('stock', '!=', '0') as $option)
                                                    <option value="{{ $option->name }}" data-type="{{ $attribute->id }}"
                                                        data-href="{{ $option->id }}"
                                                        data-target="{{ PriceHelper::setConvertPrice($option->price) }}">
                                                        {{ $option->name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                @endif
                            @endforeach
                        </div>
                        <div class="row align-items-end pb-4">
                            <div class="col-sm-12">
                                @if ($item->item_type == 'normal')
                                    <div class="qtySelector product-quantity">
                                        <span style="padding:10px" class="decreaseQty subclick"><i class="fas fa-minus "></i></span>
                                        <input type="text" class="qtyValue cart-amount" value="1" id="product-quantity">
                                        <span style="padding:10px" class="increaseQty addclick"><i class="fas fa-plus"></i></span>
                                        <input type="hidden" value="3333" id="current_stock">
                                    </div>
                                @endif
                                <div class="p-action-button">
                                    @if ($item->item_type != 'affiliate')
                                        @if ($item->is_stock())
                                            <button class="btn btn-primary m-0 a-t-c-mr add-to-cart" 
                                                id="add_to_cart"
                                                type="button"
                                                data-id="{{ $item->id ?? $item->prod_number }}"
                                                data-name="{{ $item->name }}"
                                                data-price="{{ (float) ($item->discount_price ?? $item->previous_price ?? 0) }}">
                                                <i class="icon-bag"></i><span>{{ __('Add to Cart') }}</span>
                                            </button>
                                            <button class="btn btn-primary m-0" id="but_to_cart"><i
                                                    class="icon-bag"></i><span>{{ __('Buy Now') }}</span></button>
                                        @else
                                            <button class="btn btn-primary m-0"><i
                                                    class="icon-bag"></i><span>{{ __('Out of stock') }}</span></button>
                                        @endif
                                    @else
                                        <a href="{{ $item->affiliate_link }}" target="_blank"
                                            class="btn btn-primary m-0"><span><i
                                                    class="icon-bag"></i>{{ __('Buy Now') }}</span></a>
                                    @endif
                                    @if($item->is_stock())
                                        <script>
                                        document.addEventListener('DOMContentLoaded', function () {
                                        const btn = document.getElementById('add_to_cart');
                                        if (!btn) return;

                                        btn.addEventListener('click', function () {
                                            if (typeof fbq !== 'function') {
                                            console.error('fbq NOT LOADED (AddToCart)');
                                            return;
                                            }

                                            const qtyInput = document.getElementById('product-quantity');
                                            const qty = qtyInput ? (parseInt(qtyInput.value, 10) || 1) : 1;

                                            fbq('track', 'AddToCart', {
                                            content_type: 'product',
                                            content_ids: [@json((string)($item->id ?? $item->prod_number ?? ''))],
                                            content_name: @json($item->name ?? ''),
                                            content_category: @json(optional($item->category)->name ?? ''),
                                            value: {{ (float) ($item->discount_price ?? $item->previous_price ?? 0) }},
                                            currency: 'CAD',
                                            num_items: qty
                                            });
                                        });
                                        });
                                        </script>
                                    @endif
                                </div>

                            </div>
                        </div>

                        <div class="div">
                            <div class="t-c-b-area">
                                @if ($item->brand_id)
                                    <div class="pt-1 mb-1"><span class="text-medium">{{ __('Brand') }}:</span>
                                        <a
                                            href="{{ route('front.catalog') . '?brand=' . $item->brand->slug }}">{{ $item->brand->name }}</a>
                                    </div>
                                @endif

                                <div class="pt-1 mb-1"><span class="text-medium">{{ __('Categories') }}:</span>
                                    <a
                                        href="{{ route('front.catalog') . '?category=' . $item->category->slug }}">{{ $item->category->name }}</a>
                                    @if ($item->subcategory->name)
                                        /
                                    @endif
                                    <a
                                        href="{{ route('front.catalog') . '?subcategory=' . $item->subcategory->slug }}">{{ $item->subcategory->name }}</a>
                                    @if ($item->childcategory->name)
                                        /
                                    @endif
                                    <a
                                        href="{{ route('front.catalog') . '?childcategory=' . $item->childcategory->slug }}">{{ $item->childcategory->name }}</a>
                                </div>
                                <div class="pt-1 mb-1"><span class="text-medium">{{ __('Tags') }}:</span>
                                    @if ($item->tags)
                                        @foreach (explode(',', $item->tags) as $tag)
                                            @if ($loop->last)
                                                <a
                                                    href="{{ route('front.catalog') . '?tag=' . $tag }}">{{ $tag }}</a>
                                            @else
                                                <a
                                                    href="{{ route('front.catalog') . '?tag=' . $tag }}">{{ $tag }}</a>,
                                            @endif
                                        @endforeach
                                    @endif
                                </div>
                                @if ($item->item_type == 'normal')
                                    <div class="pt-1 mb-4"><span class="text-medium">{{ __('SKU') }}:</span>
                                        #{{ $item->sku }}</div>
                                @endif
                            </div>

                            <div class="mt-4 p-d-f-area">
                                <div class="left">
                                    <a class="btn btn-primary btn-sm wishlist_store wishlist_text"
                                        href="{{ route('user.wishlist.store', $item->id) }}"><span><i
                                                class="icon-heart"></i></span>
                                        @if (Auth::check() &&
                                                App\Models\Wishlist::where('user_id', Auth::user()->id)->where('item_id', $item->id)->exists())
                                            <span>{{ __('Added To Wishlist') }}</span>
                                        @else
                                            <span class="wishlist1">{{ __('Wishlist') }}</span>
                                            <span class="wishlist2 d-none">{{ __('Added To Wishlist') }}</span>
                                        @endif
                                    </a>
                                    <button class="btn btn-primary btn-sm  product_compare"
                                        data-target="{{ route('fornt.compare.product', $item->id) }}"><span><i
                                                class="icon-repeat"></i>{{ __('Compare') }}</span></button>
                                </div>

                                <div class="d-flex align-items-center">
                                    <span class="text-muted mr-1">{{ __('Share') }}: </span>
                                    <div class="d-inline-block a2a_kit">
                                        <a class="facebook  a2a_button_facebook" href="">
                                            <span><i style="line-height: 2;" class="fab fa-facebook-f"></i></span>
                                        </a>
                                        <a class="twitter  a2a_button_twitter" href="">
                                            <span><i style="line-height: 2;" class="fab fa-twitter"></i></span>
                                        </a>
                                        <a class="linkedin  a2a_button_linkedin" href="">
                                            <span><i style="line-height: 2;" class="fab fa-linkedin-in"></i></span>
                                        </a>
                                        <a class="pinterest   a2a_button_pinterest" href="">
                                            <span><i style="line-height: 2;" class="fab fa-pinterest"></i></span>
                                        </a>
                                    </div>
                                    <script async src="https://static.addtoany.com/menu/page.js"></script>
                                </div>

                            </div>
                        </div>
                    </div>
                </div>
            </div>
            </div>

            <div class="row">
                <div class="col-12 padding-top-3x mb-3" id="details">

                    <div class="pa-vehicle-fitment card border-0 shadow-sm mb-4">
                        <div class="card-body">
                            <h5 class="h6 mb-2 text-medium">{{ __('Vehicle Fitment') }}</h5>
                            <p class="small text-muted mb-3 mb-md-2">
                                <i class="fas fa-info-circle me-1"></i>{{ __('Select Year, Make, and Model to match your vehicle when browsing parts.') }}
                            </p>
                            <div class="pa-ymm-bar d-flex flex-wrap gap-2 align-items-stretch">
                                <select id="product_ymm_year" class="form-control form-control-sm flex-grow-1" style="min-width: 8rem;">
                                    <option value="">{{ __('Year') }}</option>
                                </select>
                                <select id="product_ymm_make" class="form-control form-control-sm flex-grow-1" style="min-width: 8rem;" disabled>
                                    <option value="">{{ __('Make') }}</option>
                                </select>
                                <select id="product_ymm_model" class="form-control form-control-sm flex-grow-1" style="min-width: 8rem;" disabled>
                                    <option value="">{{ __('Model') }}</option>
                                </select>
                            </div>
                            <div class="mt-3 d-flex flex-wrap gap-2 align-items-center">
                                <a href="#" id="product_ymm_shop_link" class="btn btn-sm btn-primary disabled"
                                    tabindex="-1" aria-disabled="true">{{ __('Shop with this vehicle') }}</a>
                                <span id="product_ymm_hint" class="small text-muted"></span>
                            </div>
                            <div id="product_fitment_status" class="pa-fitment-status" role="alert"></div>
                        </div>
                    </div>

                    @php
                        $highlightCards = [];
                        if (! empty($sec_name) && ! empty($sec_details) && is_array($sec_name) && is_array($sec_details)) {
                            $n = min(count($sec_name), count($sec_details));
                            for ($i = 0; $i < $n; $i++) {
                                $highlightCards[] = ['title' => $sec_name[$i], 'detail' => $sec_details[$i]];
                            }
                        }
                        $paHighlightIcons = ['fa-gauge-high', 'fa-road', 'fa-gears', 'fa-clipboard-check', 'fa-spray-can', 'fa-car-side'];

                        $rawDetailsFull = html_entity_decode($item->details ?? '', ENT_QUOTES, 'UTF-8');
                        $fitmentHtml = '';
                        $overviewHtml = $rawDetailsFull;
                        if (preg_match('/<table[^>]*class="[^"]*\bpa-fitment-table\b[^"]*"[^>]*>[\s\S]*?<\/table>/i', $rawDetailsFull, $ftMatch)) {
                            $fitmentHtml = $ftMatch[0];
                            $overviewHtml = trim(str_replace($fitmentHtml, '', $rawDetailsFull));
                            $overviewHtml = preg_replace(
                                '/<h[23][^>]*>\s*(?:Fitting\s+Vehicles?|Fitting\s+Vehicle(?:\(s\))?|Vehicle\s+Fitment)[^<]*<\/h[23]>\s*/iu',
                                '',
                                $overviewHtml
                            );
                            $overviewHtml = trim(preg_replace('/^(?:\s*<br\s*\/?>\s*)+/i', '', $overviewHtml));
                        } elseif (preg_match('/<h[23][^>]*>\s*(?:Fitting\s+Vehicles?|Fitting\s+Vehicle(?:\(s\))?)\s*<\/h[23]>[\s\S]*?(<table[\s\S]*?<\/table>)/iu', $rawDetailsFull, $ftAlt)) {
                            $fitmentHtml = $ftAlt[1];
                            $overviewHtml = trim(preg_replace(
                                '/<h[23][^>]*>\s*(?:Fitting\s+Vehicles?|Fitting\s+Vehicle(?:\(s\))?)\s*<\/h[23]>[\s\S]*?<table[\s\S]*?<\/table>/iu',
                                '',
                                $rawDetailsFull
                            ));
                            $overviewHtml = trim(preg_replace('/^(?:\s*<br\s*\/?>\s*)+/i', '', $overviewHtml));
                        }
                        $hasHighlights = count($highlightCards) > 0;
                        $hasFitmentTable = $fitmentHtml !== '';
                        $accIdx = -1;
                    @endphp

                    <div class="accordion pa-product-accordion card border-0 shadow-sm" id="accordionProduct">

                        @if ($hasHighlights)
                            @php $accIdx++; @endphp
                            <div class="accordion-item border-start-0 border-end-0 border-top-0">
                                <h2 class="accordion-header" id="headingPaHighlights">
                                    <button class="accordion-button {{ $accIdx > 0 ? 'collapsed' : '' }}" type="button"
                                        data-bs-toggle="collapse" data-bs-target="#collapsePaHighlights"
                                        aria-expanded="{{ $accIdx === 0 ? 'true' : 'false' }}" aria-controls="collapsePaHighlights">
                                        <span class="fw-bold text-medium">{{ __('Product Highlights') }}</span>
                                    </button>
                                </h2>
                                <div id="collapsePaHighlights" class="accordion-collapse collapse {{ $accIdx === 0 ? 'show' : '' }}"
                                    aria-labelledby="headingPaHighlights">
                                    <div class="accordion-body">
                                        <div class="row row-cols-1 row-cols-md-2 g-3">
                                            @foreach ($highlightCards as $idx => $card)
                                                @php
                                                    $raw = trim(strip_tags($card['detail']));
                                                    $parts = array_map('trim', preg_split('/[,;\n\r]+/', $raw, -1, PREG_SPLIT_NO_EMPTY));
                                                    $subtitle = $parts[0] ?? '';
                                                    $bullets = array_slice($parts, 1);
                                                    if (count($parts) === 1) {
                                                        $bullets = [];
                                                    }
                                                    $icon = $paHighlightIcons[$idx % count($paHighlightIcons)];
                                                @endphp
                                                <div class="col">
                                                    <div class="pa-highlight-card h-100 border rounded p-3 bg-white">
                                                        <div class="d-flex align-items-start gap-2">
                                                            <div class="pa-highlight-icon flex-shrink-0 rounded d-flex align-items-center justify-content-center bg-light text-muted">
                                                                <i class="fas {{ $icon }}"></i>
                                                            </div>
                                                            <div class="flex-grow-1 min-w-0">
                                                                <div class="text-uppercase small fw-bold text-medium mb-1">{{ $card['title'] }}</div>
                                                                @if ($subtitle)
                                                                    <div class="small text-muted mb-2">{{ $subtitle }}</div>
                                                                @endif
                                                                @if (count($bullets))
                                                                    <ul class="small text-muted ps-3 mb-0 pa-highlight-bullets">
                                                                        @foreach (array_slice($bullets, 0, 8) as $b)
                                                                            <li>{{ $b }}</li>
                                                                        @endforeach
                                                                    </ul>
                                                                @elseif ($raw !== '')
                                                                    <p class="small text-muted mb-0">{{ $raw }}</p>
                                                                @endif
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endif

                        @php
                            $accIdx++;
                            $overviewExpanded = ! $hasHighlights;
                        @endphp
                        <div class="accordion-item border-start-0 border-end-0 {{ $hasHighlights ? '' : 'border-top-0' }}">
                            <h2 class="accordion-header" id="headingPaOverview">
                                <button class="accordion-button {{ $overviewExpanded ? '' : 'collapsed' }}" type="button"
                                    data-bs-toggle="collapse" data-bs-target="#collapsePaOverview"
                                    aria-expanded="{{ $overviewExpanded ? 'true' : 'false' }}" aria-controls="collapsePaOverview">
                                    <span class="fw-bold text-medium">{{ __('Product Overview') }}</span>
                                </button>
                            </h2>
                            <div id="collapsePaOverview" class="accordion-collapse collapse {{ $overviewExpanded ? 'show' : '' }}"
                                aria-labelledby="headingPaOverview">
                                <div class="accordion-body pa-details-body">
                                    @if (!empty(optional($item->brand)->photo))
                                        <p class="mb-3">
                                            <img src="{{ $resolveProductImageUrl($item->brand->photo) }}"
                                                alt="{{ $item->brand->name ?? __('Brand') }}"
                                                style="max-height:32px;width:auto">
                                        </p>
                                    @endif
                                    @if (trim(strip_tags($overviewHtml)) !== '')
                                        {!! $overviewHtml !!}
                                    @else
                                        <p class="text-muted mb-0">{{ __('No description available.') }}</p>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <div class="accordion-item border-start-0 border-end-0">
                            <h2 class="accordion-header" id="headingPaFitting">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                                    data-bs-target="#collapsePaFitting" aria-expanded="false" aria-controls="collapsePaFitting">
                                    <span class="fw-bold text-medium">{{ __('Fitting Vehicle(s)') }}</span>
                                </button>
                            </h2>
                            <div id="collapsePaFitting" class="accordion-collapse collapse" aria-labelledby="headingPaFitting">
                                <div class="accordion-body pa-details-body">
                                    @if ($hasFitmentTable)
                                        <div class="table-responsive">{!! $fitmentHtml !!}</div>
                                    @else
                                        <p class="text-muted small mb-0">{{ __('No fitting vehicle table is listed for this product. Use the vehicle selector above to check compatibility when browsing.') }}</p>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <div class="accordion-item border-start-0 border-end-0 border-bottom-0">
                            <h2 class="accordion-header" id="headingPaFaq">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                                    data-bs-target="#collapsePaFaq" aria-expanded="false" aria-controls="collapsePaFaq">
                                    <span class="fw-bold text-medium">{{ __('Frequently Asked Questions') }}</span>
                                </button>
                            </h2>
                            <div id="collapsePaFaq" class="accordion-collapse collapse" aria-labelledby="headingPaFaq">
                                <div class="accordion-body">
                                    <p class="text-muted small mb-2">{{ __('For answers about ordering, shipping, returns, and more, visit our help center.') }}</p>
                                    <a href="{{ route('front.faq') }}" class="btn btn-sm btn-primary">{{ __('View all FAQs') }}</a>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>


    <!-- Reviews-->
    <div class="container  review-area">
        <div class="row">
            <div class="col-lg-12">
                <div class="section-title">
                    <h2 class="h3">{{ __('Latest Reviews') }}</h2>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-md-8">
                @forelse ($reviews as $review)
                    <div class="single-review">
                        <div class="comment">
                            <div class="comment-author-ava"><img class="lazy"
                                    data-src="{{ asset('storage/images/' . $review->user->photo) }}"
                                    alt="Comment author">
                            </div>
                            <div class="comment-body">
                                <div class="comment-header d-flex flex-wrap justify-content-between">
                                    <div>
                                        <h4 class="comment-title mb-1">{{ $review->subject }}</h4>
                                        <span>{{ $review->user->first_name }}</span>
                                        <span class="ml-3">{{ $review->created_at->format('M d, Y') }}</span>
                                    </div>
                                    <div class="mb-2">
                                        <div class="rating-stars">
                                            @php
                                                for ($i = 0; $i < $review->rating; $i++) {
                                                    echo "<i class = 'far fa-star filled'></i>";
                                                }
                                            @endphp
                                        </div>
                                    </div>
                                </div>
                                <p class="comment-text  mt-2">{{ $review->review }}</p>

                            </div>
                        </div>
                    </div>
                @empty
                    <div class="card p-5">
                        {{ __('No Review') }}
                    </div>
                @endforelse
                <div class="row mt-15">
                    <div class="col-lg-12 text-center">
                        {{ $reviews->links() }}
                    </div>
                </div>

            </div>
            <div class="col-md-4 mb-4">
                <div class="card">
                    <div class="card-body">
                        <div class="text-center">
                            <div class="d-inline align-baseline display-3 mr-1">
                                {{ round($item->reviews_avg_rating ?? 0, 2) }}</div>
                            <div class="d-inline align-baseline text-sm text-warning mr-1">
                                <div class="rating-stars">
                                    {!! Helper::renderStarRating($item->reviews_avg_rating) !!}
                                </div>
                            </div>
                        </div>
                        <div class="pt-3">
                            <label class="text-medium text-sm">5 {{ __('stars') }} <span class="text-muted">-
                                    {{ (int) ($review_breakdown[5] ?? 0) }}</span></label>
                            <div class="progress margin-bottom-1x">
                                <div class="progress-bar bg-warning" role="progressbar"
                                    style="width: {{ ((int) ($review_breakdown[5] ?? 0)) * 20 }}%; height: 2px;"
                                    aria-valuenow="100"
                                    aria-valuemin="{{ ((int) ($review_breakdown[5] ?? 0)) * 20 }}"
                                    aria-valuemax="100"></div>
                            </div>
                            <label class="text-medium text-sm">4 {{ __('stars') }} <span class="text-muted">-
                                    {{ (int) ($review_breakdown[4] ?? 0) }}</span></label>
                            <div class="progress margin-bottom-1x">
                                <div class="progress-bar bg-warning" role="progressbar"
                                    style="width: {{ ((int) ($review_breakdown[4] ?? 0)) * 20 }}%; height: 2px;"
                                    aria-valuenow="{{ ((int) ($review_breakdown[4] ?? 0)) * 20 }}"
                                    aria-valuemin="0" aria-valuemax="100"></div>
                            </div>
                            <label class="text-medium text-sm">3 {{ __('stars') }} <span class="text-muted">-
                                    {{ (int) ($review_breakdown[3] ?? 0) }}</span></label>
                            <div class="progress margin-bottom-1x">
                                <div class="progress-bar bg-warning" role="progressbar"
                                    style="width: {{ ((int) ($review_breakdown[3] ?? 0)) * 20 }}%; height: 2px;"
                                    aria-valuenow="{{ ((int) ($review_breakdown[3] ?? 0)) * 20 }}"
                                    aria-valuemin="0" aria-valuemax="100"></div>
                            </div>
                            <label class="text-medium text-sm">2 {{ __('stars') }} <span class="text-muted">-
                                    {{ (int) ($review_breakdown[2] ?? 0) }}</span></label>
                            <div class="progress margin-bottom-1x">
                                <div class="progress-bar bg-warning" role="progressbar"
                                    style="width: {{ ((int) ($review_breakdown[2] ?? 0)) * 20 }}%; height: 2px;"
                                    aria-valuenow="{{ ((int) ($review_breakdown[2] ?? 0)) * 20 }}"
                                    aria-valuemin="0" aria-valuemax="100"></div>
                            </div>
                            <label class="text-medium text-sm">1 {{ __('star') }} <span class="text-muted">-
                                    {{ (int) ($review_breakdown[1] ?? 0) }}</span></label>
                            <div class="progress mb-2">
                                <div class="progress-bar bg-warning" role="progressbar"
                                    style="width: {{ ((int) ($review_breakdown[1] ?? 0)) * 20 }}%; height: 2px;"
                                    aria-valuenow="0"
                                    aria-valuemin="{{ ((int) ($review_breakdown[1] ?? 0)) * 20 }}"
                                    aria-valuemax="100"></div>
                            </div>
                        </div>
                        @if (Auth::user())
                            <div class="pb-2"><a class="btn btn-primary btn-block" href="#"
                                    data-bs-toggle="modal"
                                    data-bs-target="#leaveReview"><span>{{ __('Leave a Review') }}</span></a></div>
                        @else
                            <div class="pb-2"><a class="btn btn-primary btn-block"
                                    href="{{ route('user.login') }}"><span>{{ __('Login') }}</span></a></div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if (count($related_items) > 0)
        <div class="relatedproduct-section container padding-bottom-3x mb-1 s-pt-30">
            <!-- Related Products Carousel-->
            <div class="row">
                <div class="col-lg-12">
                    <div class="section-title">
                        <h2 class="h3">{{ __('You May Also Like') }}</h2>
                    </div>
                </div>
            </div>
            <!-- Carousel-->
            <div class="row">
                <div class="col-lg-12">
                    <div class="relatedproductslider owl-carousel">
                        @foreach ($related_items as $related)
                            <div class="slider-item">
                                <div class="product-card">

                                    @if ($related->is_stock())
                                        @if ($related->is_type == 'new')
                                        @else
                                            <div
                                                class="product-badge
                                    @if ($related->is_type == 'feature') bg-warning

                                    @elseif($related->is_type == 'top')
                                    bg-info
                                    @elseif($related->is_type == 'best')
                                    bg-dark
                                    @elseif($related->is_type == 'flash_deal')
                                    bg-success @endif
                                    ">
                                                {{ $related->is_type != 'undefine' ? ucfirst(str_replace('_', ' ', $related->is_type)) : '' }}
                                            </div>
                                        @endif
                                    @else
                                        <div
                                            class="product-badge bg-secondary border-default text-body
                                    ">
                                            {{ __('out of stock') }}</div>
                                    @endif
                                    @if (PriceHelper::showPreviousPrice($related))
                                        <div class="product-badge product-badge2 bg-info">
                                            -{{ PriceHelper::DiscountPercentage($related) }}</div>
                                    @endif

                                    @if (PriceHelper::showPreviousPrice($related))
                                        <div class="product-badge product-badge2 bg-info">
                                            -{{ PriceHelper::DiscountPercentage($related) }}</div>
                                    @endif
                                    <div class="product-thumb">
                                        <img class="lazy"
                                            data-src="{{ $resolveProductImageUrl($related->thumbnail) }}"
                                            alt="{{ $related->name }}">
                                        <div class="product-button-group">
                                            <a class="product-button wishlist_store"
                                                href="{{ route('user.wishlist.store', $related->id) }}"
                                                title="{{ __('Wishlist') }}"><i class="icon-heart"></i></a>
                                            <a class="product-button product_compare" href="javascript:;"
                                                data-target="{{ route('fornt.compare.product', $related->id) }}"
                                                title="{{ __('Compare') }}"><i class="icon-repeat"></i></a>
                                            @include('includes.item_footer', ['sitem' => $related])
                                        </div>
                                    </div>
                                    <div class="product-card-body">
                                        <div class="product-category"><a
                                                href="{{ route('front.catalog') . '?category=' . $related->category->slug }}">{{ $related->category->name }}</a>
                                        </div>
                                        <h3 class="product-title"><a
                                                href="{{ route('front.product', $related->slug) }}">
                                                {{ Str::limit($related->name, 35) }}
                                            </a></h3>
                                        <h4 class="product-price">
                                            @if (PriceHelper::showPreviousPrice($related))
                                                <del>{{ PriceHelper::setPreviousPrice($related->previous_price) }}</del>
                                            @endif
                                            {{ PriceHelper::grandCurrencyPrice($related) }}
                                        </h4>
                                    </div>

                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    @endif

    <script>
        (function () {
            var STORAGE_KEY = 'selected_vehicle';
            var py = document.getElementById('product_ymm_year');
            var pm = document.getElementById('product_ymm_make');
            var pmodel = document.getElementById('product_ymm_model');
            var shopLink = document.getElementById('product_ymm_shop_link');
            var hint = document.getElementById('product_ymm_hint');
            var fitmentStatus = document.getElementById('product_fitment_status');
            var fitmentStatusTop = document.getElementById('product_fitment_status_top');
            if (!py || !pm || !pmodel || !shopLink) return;

            var yearsUrl = @json(route('vehicle.years'));
            var makesBase = @json(rtrim(url('/makes'), '/'));
            var modelsBase = @json(rtrim(url('/models'), '/'));
            var catalogBaseUrl = @json(route('front.catalog'));
            var currentCatalogUrl = '#';
            var currentFitMatched = null;

            function pf(sel, data, ph) {
                sel.innerHTML = '<option value="">' + ph + '</option>';
                data.forEach(function (item) {
                    sel.innerHTML += '<option value="' + item.id + '">' + (item.make || item.year || item.model) + '</option>';
                });
            }

            function loadMakes(yearId, selectedMakeId) {
                pm.disabled = true;
                pmodel.disabled = true;
                pf(pmodel, [], @json(__('Model')));
                return fetch(makesBase + '/' + encodeURIComponent(yearId))
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        pf(pm, data, @json(__('Make')));
                        pm.disabled = false;
                        if (selectedMakeId) pm.value = String(selectedMakeId);
                    });
            }

            function loadModels(makeId, selectedModelId) {
                pmodel.disabled = true;
                return fetch(modelsBase + '/' + encodeURIComponent(makeId))
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        pf(pmodel, data, @json(__('Model')));
                        pmodel.disabled = false;
                        if (selectedModelId) pmodel.value = String(selectedModelId);
                        updateShopLink();
                    });
            }

            function getOptText(sel) {
                var o = sel.options[sel.selectedIndex];
                return o ? o.text.trim() : '';
            }

            function findOptionValueByText(selectEl, text) {
                var target = normalizeText(text);
                if (!selectEl || !target) return '';
                var opts = Array.from(selectEl.options || []);
                var match = opts.find(function (opt) {
                    return normalizeText(opt.textContent || opt.text || '') === target;
                });
                return match ? String(match.value) : '';
            }

            function normalizeText(v) {
                return String(v || '').replace(/\s+/g, ' ').trim().toLowerCase();
            }

            function getFitmentRows() {
                var table = document.querySelector('.pa-fitment-table') || document.querySelector('#collapsePaFitting table');
                if (!table) return [];
                var rows = [];
                table.querySelectorAll('tbody tr, tr').forEach(function (tr) {
                    var cols = Array.from(tr.querySelectorAll('th,td')).map(function (td) {
                        return td.textContent.replace(/\s+/g, ' ').trim();
                    }).filter(Boolean);
                    if (cols.length >= 3) rows.push(cols);
                });
                if (rows.length > 1) {
                    var header = rows[0].map(normalizeText);
                    if (header[0] === 'year' && header[1] === 'make' && header[2] === 'model') {
                        rows.shift();
                    }
                }
                return rows;
            }

            var fitmentRows = getFitmentRows();

            function setFitmentStatus(isMatch, label, hasSelection) {
                if (!fitmentStatus) return;
                if (!hasSelection) {
                    currentFitMatched = null;
                    fitmentStatus.style.display = 'none';
                    fitmentStatus.textContent = '';
                    fitmentStatus.classList.remove('is-fit');
                    if (fitmentStatusTop) {
                        fitmentStatusTop.style.display = 'none';
                        fitmentStatusTop.textContent = '';
                        fitmentStatusTop.classList.remove('is-fit');
                    }
                    return;
                }
                currentFitMatched = !!isMatch;
                if (isMatch) {
                    var fitText = @json(__('This part fits :vehicle')).replace(':vehicle', label);
                    fitmentStatus.innerHTML = '<i class="fas fa-check-circle" aria-hidden="true"></i>' + fitText;
                    fitmentStatus.classList.add('is-fit');
                    if (fitmentStatusTop) {
                        fitmentStatusTop.innerHTML = '<i class="fas fa-check-circle" aria-hidden="true"></i>' + fitText;
                        fitmentStatusTop.classList.add('is-fit');
                        fitmentStatusTop.style.display = 'block';
                    }
                } else {
                    var notFitText = @json(__('This part does NOT fit :vehicle')).replace(':vehicle', label);
                    fitmentStatus.innerHTML = '<i class="fas fa-times-circle" aria-hidden="true"></i>' + notFitText;
                    fitmentStatus.classList.remove('is-fit');
                    if (fitmentStatusTop) {
                        fitmentStatusTop.innerHTML = '<i class="fas fa-times-circle" aria-hidden="true"></i>' + notFitText;
                        fitmentStatusTop.classList.remove('is-fit');
                        fitmentStatusTop.style.display = 'block';
                    }
                }
                fitmentStatus.style.display = 'block';
            }

            function evaluateFitment() {
                if (!py.value || !pm.value || !pmodel.value) {
                    setFitmentStatus(false, '', false);
                    return;
                }
                var yearText = getOptText(py);
                var makeText = getOptText(pm);
                var modelText = getOptText(pmodel);
                var selectedLabel = [yearText, makeText, modelText].filter(Boolean).join(' ');
                var selectedYear = normalizeText(yearText);
                var selectedMake = normalizeText(makeText);
                var selectedModel = normalizeText(modelText);
                var hasFitmentData = fitmentRows.length > 0;
                var matched = hasFitmentData && fitmentRows.some(function (row) {
                    return normalizeText(row[0]) === selectedYear &&
                        normalizeText(row[1]) === selectedMake &&
                        normalizeText(row[2]) === selectedModel;
                });
                setFitmentStatus(matched, selectedLabel, true);
            }

            function updateShopLink() {
                if (!py.value || !pm.value || !pmodel.value) {
                    shopLink.classList.add('disabled');
                    shopLink.setAttribute('tabindex', '-1');
                    shopLink.setAttribute('aria-disabled', 'true');
                    shopLink.href = '#';
                    if (hint) hint.textContent = '';
                    evaluateFitment();
                    return;
                }
                var y = getOptText(py);
                var mk = getOptText(pm);
                var md = getOptText(pmodel);
                var u = new URL(catalogBaseUrl, window.location.origin);
                u.searchParams.set('year', y);
                u.searchParams.set('make', mk);
                u.searchParams.set('model', md);
                currentCatalogUrl = u.toString();
                shopLink.href = currentCatalogUrl;
                shopLink.classList.remove('disabled');
                shopLink.removeAttribute('aria-disabled');
                shopLink.removeAttribute('tabindex');
                if (hint) hint.textContent = '';
                evaluateFitment();
                try {
                    localStorage.setItem(STORAGE_KEY, JSON.stringify({
                        year_id: py.value,
                        year: y,
                        make_id: pm.value,
                        make: mk,
                        model_id: pmodel.value,
                        model: md
                    }));
                } catch (e) {}
            }

            fetch(yearsUrl)
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    pf(py, data, @json(__('Year')));
                    try {
                        var stored = localStorage.getItem(STORAGE_KEY);
                        if (stored) {
                            var v = JSON.parse(stored);
                            var yearId = v.year_id ? String(v.year_id) : findOptionValueByText(py, v.year);
                            if (yearId) {
                                py.value = yearId;
                                return loadMakes(yearId, v.make_id).then(function () {
                                    var makeId = v.make_id ? String(v.make_id) : findOptionValueByText(pm, v.make);
                                    if (makeId) {
                                        pm.value = makeId;
                                        return loadModels(makeId, v.model_id).then(function () {
                                            var modelId = v.model_id ? String(v.model_id) : findOptionValueByText(pmodel, v.model);
                                            if (modelId) pmodel.value = modelId;
                                        });
                                    }
                                }).then(function () { updateShopLink(); });
                            }
                        }
                    } catch (e) {}
                });

            py.addEventListener('change', function () {
                if (!py.value) {
                    pm.disabled = true;
                    pmodel.disabled = true;
                    pf(pm, [], @json(__('Make')));
                    pf(pmodel, [], @json(__('Model')));
                    updateShopLink();
                    return;
                }
                loadMakes(py.value).then(function () { updateShopLink(); });
            });

            pm.addEventListener('change', function () {
                if (!pm.value) {
                    pmodel.disabled = true;
                    pf(pmodel, [], @json(__('Model')));
                    updateShopLink();
                    return;
                }
                loadModels(pm.value).then(updateShopLink);
            });

            pmodel.addEventListener('change', updateShopLink);

            shopLink.addEventListener('click', function (e) {
                if (shopLink.classList.contains('disabled') || !currentCatalogUrl || currentCatalogUrl === '#') {
                    e.preventDefault();
                    return;
                }
                e.preventDefault();
                window.location.assign(currentCatalogUrl);
            });
        })();
    </script>


    @auth
        <form class="modal fade ratingForm" action="{{ route('front.review.submit') }}" method="post" id="leaveReview"
            tabindex="-1">
            @csrf
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h4 class="modal-title">{{ __('Leave a Review') }}</h4>
                        <button class="close modal_close" type="button" data-bs-dismiss="modal" aria-label="Close"><span
                                aria-hidden="true">&times;</span></button>
                    </div>
                    <div class="modal-body">
                        @php
                            $user = Auth::user();
                        @endphp
                        <div class="row">
                            <div class="col-sm-6">
                                <div class="form-group">
                                    <label for="review-name">{{ __('Your Name') }}</label>
                                    <input class="form-control" type="text" id="review-name"
                                        value="{{ $user->first_name }}" required>
                                </div>
                            </div>
                            <input type="hidden" name="item_id" value="{{ $item->id }}">
                            <div class="col-sm-6">
                                <div class="form-group">
                                    <label for="review-email">{{ __('Your Email') }}</label>
                                    <input class="form-control" type="email" id="review-email"
                                        value="{{ $user->email }}" required>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-sm-6">
                                <div class="form-group">
                                    <label for="review-subject">{{ __('Subject') }}</label>
                                    <input class="form-control" type="text" name="subject" id="review-subject" required>
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <div class="form-group">
                                    <label for="review-rating">{{ __('Rating') }}</label>
                                    <select name="rating" class="form-control" id="review-rating">
                                        <option value="5">5 {{ __('Stars') }}</option>
                                        <option value="4">4 {{ __('Stars') }}</option>
                                        <option value="3">3 {{ __('Stars') }}</option>
                                        <option value="2">2 {{ __('Stars') }}</option>
                                        <option value="1">1 {{ __('Star') }}</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="review-message">{{ __('Review') }}</label>
                            <textarea class="form-control" name="review" id="review-message" rows="8" required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-primary" type="submit"><span>{{ __('Submit Review') }}</span></button>
                    </div>
                </div>
            </div>
        </form>
    @endauth

@endsection
