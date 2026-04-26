@extends('master.front')

@section('title')
    {{ __('Compare') }}
@endsection

@section('meta')
    <meta name="keywords" content="{{ $setting->meta_keywords }}">
    <meta name="description" content="{{ $setting->meta_description }}">
@endsection

@php
    $selectedCompareCount = count($items);
    $canAddMoreCompareItems = $selectedCompareCount < 2;
    $resolveCompareImageUrl = function (?string $rawPath): string {
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
        if ($filename === '') {
            return url('/core/public/storage/images/placeholder.png');
        }

        return url('/core/public/storage/images/' . $filename);
    };
@endphp

@section('styleplugins')
    <style>
        .compare-page-actions {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 0.85rem;
            margin-bottom: 1.25rem;
        }
        .compare-page-actions .compare-page-copy {
            max-width: 42rem;
        }
        .compare-page-actions .compare-page-copy h2 {
            margin-bottom: 0.35rem;
            font-size: 1.4rem;
        }
        .compare-page-actions .compare-page-copy p {
            margin-bottom: 0;
            color: #666;
        }
        .compare-page-actions .compare-page-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
        }
        .compare-empty-state {
            padding: 2rem 1.25rem;
            border: 1px solid #ececec;
            border-radius: 0.75rem;
            text-align: center;
            background: #fff;
        }
        .compare-empty-state h3 {
            margin-bottom: 0.65rem;
            font-size: 1.75rem;
        }
        .compare-empty-state p {
            max-width: 32rem;
            margin: 0 auto 1rem;
            color: #666;
        }
        .compare-empty-state .compare-page-buttons {
            justify-content: center;
        }
        .compare-picker-result {
            display: flex;
            align-items: center;
            gap: 0.9rem;
            padding: 0.85rem 0;
            border-bottom: 1px solid #ececec;
        }
        .compare-picker-result:last-child {
            border-bottom: 0;
            padding-bottom: 0;
        }
        .compare-picker-result:first-child {
            padding-top: 0;
        }
        .compare-picker-thumb {
            width: 72px;
            height: 72px;
            border: 1px solid #ececec;
            border-radius: 0.5rem;
            object-fit: contain;
            background: #fff;
            flex: 0 0 72px;
        }
        .compare-picker-result-body {
            min-width: 0;
            flex: 1 1 auto;
        }
        .compare-picker-result-body a {
            display: inline-block;
            color: #232323;
            font-weight: 500;
            line-height: 1.35;
            text-decoration: none;
        }
        .compare-picker-meta {
            margin-top: 0.35rem;
            font-size: 0.92rem;
            color: #777;
        }
        .compare-picker-price {
            margin-top: 0.35rem;
            font-weight: 600;
            color: #dc2127;
        }
        .compare-picker-result-actions {
            flex: 0 0 auto;
        }
        .compare-picker-status {
            margin-top: 0.9rem;
            display: none;
        }
        .compare-picker-status.show {
            display: block;
        }
        .comparison-table .comparison-item-thumb img {
            object-fit: contain;
        }
        @media (max-width: 767.98px) {
            .compare-page-actions {
                align-items: stretch;
            }
            .compare-page-actions .compare-page-buttons {
                width: 100%;
            }
            .compare-page-actions .compare-page-buttons .btn {
                flex: 1 1 100%;
            }
            .compare-empty-state {
                padding: 1.5rem 1rem;
            }
            .compare-empty-state h3 {
                font-size: 1.5rem;
            }
            .compare-picker-result {
                align-items: flex-start;
                flex-wrap: wrap;
            }
            .compare-picker-result-actions {
                width: 100%;
            }
            .compare-picker-result-actions .btn {
                width: 100%;
            }
        }
    </style>
@endsection

@section('content')
    <div class="page-title">
        <div class="container">
            <div class="row">
                <div class="col-lg-12">
                    <ul class="breadcrumbs">
                        <li><a href="{{ route('front.index') }}">{{ __('Home') }}</a></li>
                        <li class="separator"></li>
                        <li>{{ __('Compare Products') }}</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <div class="container padding-bottom-2x mb-2">
        <div class="compare-page-actions">
            <div class="compare-page-copy">
                <h2>{{ __('Compare Products') }}</h2>
                <p>
                    @if ($selectedCompareCount > 0)
                        {{ __('Review the products you selected, then add one more item or go back to your account without losing your place.') }}
                    @else
                        {{ __('Start with one or two products, then compare pricing and details side by side.') }}
                    @endif
                </p>
            </div>
            <div class="compare-page-buttons">
                @if ($canAddMoreCompareItems)
                    <button class="btn btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#comparePickerModal">
                        <i class="icon-plus pr-2"></i>{{ __('Select Product') }}
                    </button>
                @endif
                <a class="btn btn-outline-primary" href="{{ route('front.catalog') }}">
                    <i class="icon-grid pr-2"></i>{{ __('Browse Products') }}
                </a>
                @auth
                    <a class="btn btn-outline-primary" href="{{ route('user.dashboard') }}">
                        <i class="icon-user pr-2"></i>{{ __('Dashboard') }}
                    </a>
                @endauth
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                @if ($selectedCompareCount > 0)
                    <div class="comparison-table">
                        <table class="table table-bordered">
                            <tbody>
                                <tr class="bg-secondary">
                                    <th class="text-uppercase">{{ __('Summary') }}</th>
                                    @foreach ($items as $item)
                                        <td><span class="text-medium">{{ $item->name }}</span></td>
                                    @endforeach
                                </tr>

                                @if ($selectedCompareCount != 1)
                                    <tr>
                                        <td>
                                            <h6>{{ $items[0]->name }}</h6>
                                            <p><b>{{ __('Brand') }}</b> : {{ $items[0]->brand->name }}, <b>{{ __('Price') }}</b> : {{ PriceHelper::grandCurrencyPrice($items[0]) }}</p>
                                            <hr>
                                            <h6 class="mt-2">{{ $items[1]->name }}</h6>
                                            <p><b>{{ __('Brand') }}</b> : {{ $items[1]->brand->name }}, <b>{{ __('Price') }}</b> : {{ PriceHelper::grandCurrencyPrice($items[1]) }}</p>
                                        </td>
                                        @foreach ($items as $item)
                                            <td>
                                                <div class="comparison-item">
                                                    <span class="remove-item compare_remove" data-href="{{ route('front.compare.remove', $item->id) }}"><i class="icon-x"></i></span>
                                                    <a class="comparison-item-thumb" href="{{ route('front.product', $item->slug) }}">
                                                        <img src="{{ $resolveCompareImageUrl($item->thumbnail) }}" alt="{{ $item->name }}">
                                                    </a>
                                                    <a class="comparison-item-title" href="{{ route('front.product', $item->slug) }}">{{ $item->name }}</a>
                                                    @if ($item->item_type != 'affiliate')
                                                        <a class="btn btn-outline-primary btn-sm add_to_single_cart" href="javascript:;" data-target="{{ $item->id }}">{{ __('Add to Cart') }}</a>
                                                    @endif
                                                </div>
                                            </td>
                                        @endforeach
                                    </tr>
                                    @foreach ($sname as $key => $name)
                                        <tr>
                                            <th>{{ $name }}</th>
                                            <td>
                                                @if ($items[0]->specification_name)
                                                    @if (in_array($name, json_decode($items[0]->specification_name, true)))
                                                        @if (isset($sdesc[0][$key]))
                                                            {{ $sdesc[0][$key] }}
                                                        @endif
                                                    @endif
                                                @endif
                                            </td>
                                            <td>
                                                @if ($items[1]->specification_name)
                                                    @if (in_array($name, json_decode($items[1]->specification_name, true)))
                                                        @if (isset($sdesc[1][$key]))
                                                            {{ $sdesc[1][$key] }}
                                                        @endif
                                                    @endif
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                @else
                                    <tr>
                                        <td>
                                            <h4>{{ $items[0]->name }}</h4>
                                            <p><b>{{ __('Brand') }}</b> : {{ $items[0]->brand->name }}, <b>{{ __('Price') }}</b> : {{ PriceHelper::grandCurrencyPrice($items[0]) }}</p>
                                        </td>
                                        @foreach ($items as $item)
                                            <td>
                                                <div class="comparison-item">
                                                    <span class="remove-item compare_remove" data-href="{{ route('front.compare.remove', $item->id) }}"><i class="icon-x"></i></span>
                                                    <a class="comparison-item-thumb" href="{{ route('front.product', $item->slug) }}">
                                                        <img src="{{ $resolveCompareImageUrl($item->thumbnail) }}" alt="{{ $item->name }}">
                                                    </a>
                                                    <a class="comparison-item-title" href="{{ route('front.product', $item->slug) }}">{{ $item->name }}</a>
                                                    @if ($item->item_type != 'affiliate')
                                                        <a class="btn btn-outline-primary btn-sm add_to_single_cart" href="javascript:;" data-target="{{ $item->id }}">{{ __('Add to Cart') }}</a>
                                                    @endif
                                                </div>
                                            </td>
                                        @endforeach
                                    </tr>
                                    @foreach ($sname as $key => $name)
                                        @if ($items[0]->specification_name)
                                            <tr>
                                                <th>{{ $name }}</th>
                                                <td>
                                                    @if (in_array($name, json_decode($items[0]->specification_name, true)))
                                                        @if (isset($sdesc[0][$key]))
                                                            {{ $sdesc[0][$key] }}
                                                        @endif
                                                    @endif
                                                </td>
                                            </tr>
                                        @endif
                                    @endforeach
                                @endif

                                <tr>
                                    <th></th>
                                    @foreach ($items as $item)
                                        @if ($item->item_type != 'affiliate')
                                            <td>
                                                <a class="btn btn-outline-primary btn-sm btn-block add_to_single_cart" href="javascript:;" data-target="{{ $item->id }}">{{ __('Add to Cart') }}</a>
                                            </td>
                                        @endif
                                    @endforeach
                                </tr>
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="compare-empty-state">
                        <h3>{{ __('No products selected yet') }}</h3>
                        <p>{{ __('Choose products to compare side by side, or head back to your account while you continue browsing.') }}</p>
                        <div class="compare-page-buttons">
                            <button class="btn btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#comparePickerModal">
                                <i class="icon-plus pr-2"></i>{{ __('Select Product') }}
                            </button>
                            <a class="btn btn-outline-primary" href="{{ route('front.catalog') }}">
                                <i class="icon-grid pr-2"></i>{{ __('Browse Products') }}
                            </a>
                            @auth
                                <a class="btn btn-outline-primary" href="{{ route('user.dashboard') }}">
                                    <i class="icon-user pr-2"></i>{{ __('Dashboard') }}
                                </a>
                            @endauth
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <div class="modal fade" id="comparePickerModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">{{ __('Select Product to Compare') }}</h5>
                    <button class="close" type="button" data-bs-dismiss="modal" aria-label="{{ __('Close') }}">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p class="text-muted mb-3">{{ __('Search by product name, SKU, part number, or brand to add an item directly into compare.') }}</p>
                    <form id="comparePickerForm" class="input-group">
                        <input type="text" class="form-control" id="comparePickerQuery" placeholder="{{ __('Search products to compare') }}" autocomplete="off">
                        <button class="btn btn-primary" type="submit">{{ __('Search') }}</button>
                    </form>
                    <div id="comparePickerStatus" class="alert compare-picker-status mb-0 mt-3" role="alert"></div>
                    <div id="comparePickerResults" class="mt-3"></div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-outline-primary" type="button" data-bs-dismiss="modal">{{ __('Close') }}</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const compareSearchUrl = @json(route('front.compare.search'));
            const compareResults = document.getElementById('comparePickerResults');
            const compareStatus = document.getElementById('comparePickerStatus');
            const compareQueryInput = document.getElementById('comparePickerQuery');
            const compareForm = document.getElementById('comparePickerForm');

            function setCompareStatus(message, level) {
                compareStatus.textContent = message;
                compareStatus.className = 'alert compare-picker-status show';
                compareStatus.classList.add(level === 'error' ? 'alert-danger' : 'alert-success');
            }

            function clearCompareStatus() {
                compareStatus.textContent = '';
                compareStatus.className = 'alert compare-picker-status';
            }

            function renderCompareResults(items) {
                if (!items.length) {
                    compareResults.innerHTML = '<p class="text-muted mb-0">{{ __('No products found for that search.') }}</p>';
                    return;
                }

                compareResults.innerHTML = items.map(function (item) {
                    return `
                        <div class="compare-picker-result">
                            <img class="compare-picker-thumb" src="${item.image_url}" alt="${item.name}">
                            <div class="compare-picker-result-body">
                                <a href="${item.product_url}" target="_blank" rel="noopener">${item.name}</a>
                                <div class="compare-picker-meta">${item.brand ? item.brand : ''}</div>
                                <div class="compare-picker-price">${item.price}</div>
                            </div>
                            <div class="compare-picker-result-actions">
                                <button class="btn btn-primary btn-sm compare-picker-add" type="button" data-target="${item.compare_url}">
                                    {{ __('Select Product') }}
                                </button>
                            </div>
                        </div>
                    `;
                }).join('');
            }

            async function performCompareSearch() {
                const query = compareQueryInput.value.trim();
                clearCompareStatus();

                if (!query) {
                    compareResults.innerHTML = '<p class="text-muted mb-0">{{ __('Enter a search term to find products.') }}</p>';
                    return;
                }

                compareResults.innerHTML = '<p class="text-muted mb-0">{{ __('Searching products...') }}</p>';

                try {
                    const response = await fetch(compareSearchUrl + '?q=' + encodeURIComponent(query), {
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                        }
                    });
                    const data = await response.json();
                    renderCompareResults(data.items || []);
                } catch (error) {
                    compareResults.innerHTML = '<p class="text-danger mb-0">{{ __('Unable to load compare suggestions right now.') }}</p>';
                }
            }

            compareForm.addEventListener('submit', function (event) {
                event.preventDefault();
                performCompareSearch();
            });

            let compareSearchTimer = null;
            compareQueryInput.addEventListener('input', function () {
                clearTimeout(compareSearchTimer);
                compareSearchTimer = setTimeout(performCompareSearch, 250);
            });

            document.addEventListener('click', function (event) {
                const trigger = event.target.closest('.compare-picker-add');
                if (!trigger) {
                    return;
                }

                event.preventDefault();
                clearCompareStatus();

                fetch(trigger.getAttribute('data-target'), {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                    }
                })
                .then(function (response) {
                    return response.json();
                })
                .then(function (data) {
                    const countLabels = document.querySelectorAll('.compare_count');
                    countLabels.forEach(function (node) {
                        node.textContent = data.compare_count;
                    });

                    if (data.status == 1) {
                        setCompareStatus(data.message, 'success');
                        window.location.reload();
                    } else {
                        setCompareStatus(data.message, 'error');
                    }
                })
                .catch(function () {
                    setCompareStatus('{{ __('Unable to add this product to compare right now.') }}', 'error');
                });
            });
        });
    </script>
@endsection
