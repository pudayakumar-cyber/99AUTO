@php
    $resolveProductImageUrl = function (?string $rawPath): string {
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

    $extractItemFitmentRows = function ($item): array {
        if (empty($item->details)) return [];
        $rowsOut = [];
        preg_match_all('/<tr>(.*?)<\/tr>/si', $item->details, $rows);
        foreach ($rows[1] ?? [] as $rowHtml) {
            preg_match_all('/<td[^>]*>(.*?)<\/td>/si', $rowHtml, $cols);
            if (count($cols[1] ?? []) !== 3) {
                continue;
            }

            [$yearsCell, $makeCell, $modelCell] = array_map(
                fn ($v) => trim(strip_tags((string) $v)),
                $cols[1]
            );
            if ($yearsCell === '' || $makeCell === '' || $modelCell === '') continue;
            $rowsOut[] = [
                'years' => array_values(array_filter(array_map('trim', explode(',', $yearsCell)))),
                'make' => $makeCell,
                'model' => $modelCell,
            ];
        }
        return $rowsOut;
    };
@endphp
<style>
    #main_div .product-card .js-fitment-status {
        display: flex !important;
        align-items: flex-start;
        gap: 0.3rem;
        line-height: 1.25;
        min-height: 2.5rem;
        word-break: break-word;
    }
    #main_div .product-card .js-fitment-status i {
        flex: 0 0 auto;
        margin-top: 0.08rem;
    }
    @media (max-width: 575.98px) {
        #main_div .product-card .js-fitment-status {
            font-size: 0.88rem;
            min-height: 2.75rem;
        }
    }
</style>
<div class="row g-3" id="main_div">
    @if($items->count() > 0)
        @if ($checkType != 'list')
            @foreach ($items as $item)
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
                    <div class="product-badge bg-secondary border-default text-body
                    ">{{__('out of stock')}}</div>
                    @endif

                @if($item->previous_price && $item->previous_price !=0)
                <div class="product-badge product-badge2 bg-info"> -{{PriceHelper::DiscountPercentage($item)}}</div>
                @endif
                <div class="product-thumb">
                    <img class="lazy" src="{{ $resolveProductImageUrl($item->thumbnail) }}" data-src="{{ $resolveProductImageUrl($item->thumbnail) }}" alt="{{ $item->name }}" loading="lazy">
                    <div class="product-button-group">
                        <a class="product-button wishlist_store" href="{{route('user.wishlist.store',$item->id)}}" title="{{__('Wishlist')}}"><i class="icon-heart"></i></a>
                        <a class="product-button product_compare" href="javascript:;" data-target="{{route('fornt.compare.product',$item->id)}}" title="{{__('Compare')}}"><i class="icon-repeat"></i></a>
                        @include('includes.item_footer',['sitem' => $item])
                    </div>
                </div>
                <div class="product-card-body">
                    <div class="product-category">
                        <a href="{{route('front.catalog').'?category='.$item->category->slug}}">{{$item->category->name}}</a>
                    </div>
                    <h3 class="product-title"><a href="{{route('front.product',$item->slug)}}">
                        {{ Str::limit(collect([
                            optional($item->brand)->name ?: null,
                            $item->product_part_number ?: $item->prod_number  ?: null,
                            $item->name,
                        ])->filter(fn ($v) => trim((string) $v) !== '')->implode(' - '), 38) }}
                    </a></h3>
                    <div class="rating-stars">
                        {!! Helper::renderStarRating($item->reviews_avg_rating)!!}
                    </div>
                    <h4 class="product-price">
                        @if ($item->previous_price !=0)
                        <del>{{PriceHelper::setPreviousPrice($item->previous_price)}}</del>
                        @endif
                        {{PriceHelper::grandCurrencyPrice($item)}}
                    </h4>
                    <div class="small mt-1 js-fitment-status d-none" aria-live="polite"></div>
                </div>

                </div>
            </div>
            @endforeach
        @else
            @foreach ($items as $item)
                <div class="col-lg-12">
                    <div class="product-card product-list" data-fitment-rows='@json($extractItemFitmentRows($item))'>
                        <div class="product-thumb" >
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
                                ">{{ __($item->is_type != 'undefine' ?  ucfirst(str_replace('_',' ',$item->is_type)) : '') }}
                            </div>
                            @else
                            <div class="product-badge bg-secondary border-default text-body
                            ">{{__('out of stock')}}</div>
                            @endif
                            @if($item->previous_price && $item->previous_price !=0)
                            <div class="product-badge product-badge2 bg-info"> -{{PriceHelper::DiscountPercentage($item)}}</div>
                            @endif

                            <img class="lazy" src="{{ $resolveProductImageUrl($item->thumbnail) }}" data-src="{{ $resolveProductImageUrl($item->thumbnail) }}" alt="{{ $item->name }}" loading="lazy">
                            <div class="product-button-group">
                                <a class="product-button wishlist_store" href="{{route('user.wishlist.store',$item->id)}}" title="{{__('Wishlist')}}"><i class="icon-heart"></i></a>
                                <a data-target="{{route('fornt.compare.product',$item->id)}}" class="product-button product_compare" href="javascript:;" title="{{__('Compare')}}"><i class="icon-repeat"></i></a>
                                @include('includes.item_footer',['sitem' => $item])
                            </div>
                        </div>
                            <div class="product-card-inner">
                                <div class="product-card-body">
                                    <div class="product-category"><a href="{{route('front.catalog').'?category='.$item->category->slug}}">{{$item->category->name}}</a></div>
                                    <h3 class="product-title"><a href="{{route('front.product',$item->slug)}}">
                                        {{ Str::limit(collect([
                                            optional($item->brand)->name ?: null,
                                            $item->product_part_number ?: $item->prod_number ?: null,
                                            $item->name,
                                        ])->filter(fn ($v) => trim((string) $v) !== '')->implode(' - '), 52) }}
                                    </a></h3>
                                    <div class="rating-stars">
                                        {!! Helper::renderStarRating($item->reviews_avg_rating) !!}
                                    </div>
                                    <h4 class="product-price">
                                        @if ($item->previous_price !=0)
                                        <del>{{PriceHelper::setPreviousPrice($item->previous_price)}}</del>
                                        @endif
                                        {{PriceHelper::grandCurrencyPrice($item)}}
                                    </h4>
                                    <div class="small mt-1 js-fitment-status d-none" aria-live="polite"></div>
                                    <p class="text-sm sort_details_show  text-muted hidden-xs-down my-1">
                                    {{ Str::limit(strip_tags($item->sort_details), 100) }}
                                    </p>
                                </div>


                            </div>
                        </div>
                </div>
            @endforeach
        @endif
    @else
        <div class="col-lg-12">
            <div class="card">
                <div class="card-body text-center">
                    <h4 class="h4 mb-0">{{ __('No Product Found') }}</h4>
                </div>
            </div>
        </div>
    @endif
</div>


<!-- Pagination-->
<div class="row mt-15" id="item_pagination">
    <div class="col-lg-12 text-center">
        {{$items->links()}}
    </div>
</div>

<script>
    (function () {
        var STORAGE_KEY = 'selected_vehicle';
        function normalize(v) {
            return String(v || '').replace(/\s+/g, ' ').trim().toLowerCase();
        }
        function hasVehicle(v) {
            return v && v.year && v.make && v.model;
        }
        function fitsVehicle(rows, vehicle) {
            var y = normalize(vehicle.year);
            var mk = normalize(vehicle.make);
            var md = normalize(vehicle.model);
            return (rows || []).some(function (row) {
                var years = Array.isArray(row.years) ? row.years : [];
                var yearMatch = years.some(function (yy) { return normalize(yy) === y; });
                return yearMatch && normalize(row.make) === mk && normalize(row.model) === md;
            });
        }

        var selectedVehicle = null;
        try { selectedVehicle = JSON.parse(localStorage.getItem(STORAGE_KEY) || 'null'); } catch (e) {}
        if (!hasVehicle(selectedVehicle)) return;

        var vehicleLabel = [selectedVehicle.year, selectedVehicle.make, selectedVehicle.model].join(' ');
        var fitsText = @json(__('Fits'));
        var notFitsText = @json(__('Does not fit'));

        document.querySelectorAll('.product-card[data-fitment-rows]').forEach(function (card) {
            var statusEl = card.querySelector('.js-fitment-status');
            if (!statusEl) return;
            var rows = [];
            try { rows = JSON.parse(card.getAttribute('data-fitment-rows') || '[]'); } catch (e) {}
            var matched = fitsVehicle(rows, selectedVehicle);
            statusEl.classList.remove('d-none', 'text-success', 'text-danger');
            statusEl.classList.add(matched ? 'text-success' : 'text-danger');
            statusEl.innerHTML = '<i class="fas ' + (matched ? 'fa-check-circle' : 'fa-times-circle') + '"></i> '
                + (matched ? fitsText : notFitsText) + ' ' + vehicleLabel;
        });
    })();
</script>

