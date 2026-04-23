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
    #main_div.catalog-progressive {
        position: relative;
    }
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
    .catalog-skeleton {
        border-radius: 0.75rem;
        background: #fff;
        padding: 0.9rem;
        min-height: 22rem;
        box-shadow: 0 0.25rem 1rem rgba(0, 0, 0, 0.05);
    }
    .catalog-skeleton-line,
    .catalog-skeleton-thumb,
    .catalog-skeleton-price {
        background: linear-gradient(90deg, #f1f1f1 25%, #e4e4e4 37%, #f1f1f1 63%);
        background-size: 400% 100%;
        animation: catalogSkeletonPulse 1.35s ease infinite;
        border-radius: 0.5rem;
    }
    .catalog-skeleton-thumb {
        width: 100%;
        aspect-ratio: 1 / 1;
        margin-bottom: 0.9rem;
    }
    .catalog-skeleton-line {
        height: 0.9rem;
        margin-bottom: 0.65rem;
    }
    .catalog-skeleton-line.short {
        width: 55%;
    }
    .catalog-skeleton-line.medium {
        width: 78%;
    }
    .catalog-skeleton-price {
        height: 1.15rem;
        width: 45%;
        margin-top: 0.9rem;
    }
    .catalog-progressive-sentinel {
        width: 100%;
        height: 1px;
    }
    @keyframes catalogSkeletonPulse {
        0% { background-position: 100% 50%; }
        100% { background-position: 0 50%; }
    }
</style>
@php
    $chunkSize = min(4, max(1, $items->count()));
    $itemPartial = $checkType != 'list'
        ? 'front.catalog.partials.grid-item'
        : 'front.catalog.partials.list-item';
    $initialItems = $items->getCollection()->take($chunkSize);
    $remainingItemsCount = max(0, $items->count() - $initialItems->count());
    $catalogShowingFrom = method_exists($items, 'firstItem') ? ($items->firstItem() ?? 0) : ($items->count() ? 1 : 0);
    $catalogShowingTo = method_exists($items, 'lastItem') ? ($items->lastItem() ?? 0) : $items->count();
    $catalogShowingTotal = method_exists($items, 'total') ? $items->total() : $items->count();
    $catalogShowingText = $catalogShowingTotal > 0
        ? $catalogShowingFrom . ' - ' . $catalogShowingTo . ' ' . __('of') . ' ' . $catalogShowingTotal . ' ' . __('items')
        : '0 ' . __('items');
@endphp
<div id="catalog_count_meta" class="d-none" data-showing-text="{{ $catalogShowingText }}"></div>
<div class="row g-3 catalog-progressive" id="main_div" data-chunk-size="{{ $chunkSize }}" data-total-items="{{ $items->count() }}">
    @if($items->count() > 0)
        @foreach ($initialItems as $item)
            @include($itemPartial, ['item' => $item, 'resolveProductImageUrl' => $resolveProductImageUrl, 'extractItemFitmentRows' => $extractItemFitmentRows])
        @endforeach
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

@if ($remainingItemsCount > 0)
    <div id="catalog_chunk_loader" class="row g-3 d-none mt-0" aria-hidden="true">
        @for ($i = 0; $i < $chunkSize; $i++)
            <div class="{{ $checkType != 'list' ? 'col-xxl-3 col-md-4 col-6' : 'col-lg-12' }}">
                <div class="catalog-skeleton">
                    <div class="catalog-skeleton-thumb"></div>
                    <div class="catalog-skeleton-line short"></div>
                    <div class="catalog-skeleton-line"></div>
                    <div class="catalog-skeleton-line medium"></div>
                    <div class="catalog-skeleton-price"></div>
                </div>
            </div>
        @endfor
    </div>
    <div id="catalog_chunk_sentinel" class="catalog-progressive-sentinel"></div>
@endif


<!-- Pagination-->
<div class="row mt-15" id="item_pagination">
    <div class="col-lg-12 text-center">
        {{$items->links()}}
    </div>
</div>

