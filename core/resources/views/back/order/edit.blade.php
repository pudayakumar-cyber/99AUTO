@extends('master.back')

@section('content')
    <div class="container-fluid">

        <!-- Page Heading -->
        <div class="card mb-4">
            <div class="card-body">
                <div class="d-sm-flex align-items-center justify-content-between">
                    <h3 class="mb-0 bc-title"><b>{{ __('Edit Order ID') }}</b></h3>
                    <a class="btn btn-primary  btn-sm" href="{{ route('back.order.index') }}"><i
                            class="fas fa-chevron-left"></i> {{ __('Back') }}</a>
                </div>
            </div>
        </div>

        <!-- Form -->
        <div class="row">

            <div class="col-xl-12 col-lg-12 col-md-12">

                <div class="card o-hidden border-0 shadow-lg">
                    <div class="card-body p-0">
                        <!-- Nested Row within Card Body -->
                        <div class="row justify-content-center">
                            <div class="col-lg-8">
                                <div class="p-5">
                                    <form class="admin-form" action="{{ route('back.order.update', $order->id) }}"
                                        method="POST" enctype="multipart/form-data">

                                        @csrf
                                        @include('alerts.alerts')

                                        <div class="form-group">
                                            <label for="transaction_number">{{ __('Order ID') }} *</label>
                                            <input type="text" name="transaction_number" class="form-control item-name"
                                                id="transaction_number" placeholder="{{ __('Enter Order ID') }}"
                                                value="{{ $order->transaction_number }}" required>
                                        </div>

                                        <div class="form-group">
                                            <label for="shipping_method_name">{{ __('Shipping Method') }}</label>
                                            <input type="text" name="shipping_method_name" class="form-control"
                                                id="shipping_method_name" placeholder="{{ __('Shipping Method') }}"
                                                value="{{ $order->shipping_method_name }}">
                                        </div>

                                        <div class="form-group">
                                            <label for="shipping_carrier">{{ __('Carrier') }}</label>
                                            <input type="text" name="shipping_carrier" class="form-control"
                                                id="shipping_carrier" placeholder="{{ __('Carrier') }}"
                                                value="{{ $order->shipping_carrier }}">
                                        </div>

                                        <div class="form-group">
                                            <label for="tracking_number">{{ __('Tracking Number') }}</label>
                                            <input type="text" name="tracking_number" class="form-control"
                                                id="tracking_number" placeholder="{{ __('Tracking Number') }}"
                                                value="{{ $order->tracking_number }}">
                                        </div>

                                        <div class="form-group text-center">
                                            <button type="submit" class="btn btn-secondary ">{{ __('Submit') }}</button>
                                        </div>
                                        <div>
                                    </form>

                                    <hr class="my-5">

                                    <div>
                                        <h5 class="mb-3">{{ __('Shipment Preview') }}</h5>

                                        @if (!empty($shipmentPreview['ready']))
                                            <div class="alert alert-success">
                                                {{ __('This order has enough address and package data for shipment preparation.') }}
                                            </div>
                                        @else
                                            <div class="alert alert-warning">
                                                {{ __('This order is not ready for live shipment creation yet. Review the issues below first.') }}
                                            </div>
                                        @endif

                                        @if (!empty($shipmentPreview['warnings']))
                                            <div class="mb-4">
                                                @foreach ($shipmentPreview['warnings'] as $warning)
                                                    <div class="alert alert-warning mb-2">{{ $warning }}</div>
                                                @endforeach
                                            </div>
                                        @endif

                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="card mb-4">
                                                    <div class="card-body">
                                                        <h6 class="mb-3">{{ __('Selected Shipping') }}</h6>
                                                        <p class="mb-1"><strong>{{ __('Method') }}:</strong>
                                                            {{ $shipmentPreview['shipping_method']['title'] ?? __('Not available') }}</p>
                                                        <p class="mb-1"><strong>{{ __('Carrier') }}:</strong>
                                                            {{ $shipmentPreview['shipping_method']['carrier'] ?? __('Not available') }}</p>
                                                        <p class="mb-1"><strong>{{ __('Price') }}:</strong>
                                                            {{ $shipmentPreview['shipping_method']['price'] ?? __('Not available') }}</p>
                                                        <p class="mb-1"><strong>{{ __('Source') }}:</strong>
                                                            {{ $shipmentPreview['shipping_method']['source'] ?? __('Not available') }}</p>
                                                        <p class="mb-1"><strong>{{ __('Rate ID') }}:</strong>
                                                            <span class="text-break">{{ $shipmentPreview['shipping_method']['rate_id'] ?? __('Not available') }}</span></p>
                                                        <p class="mb-0"><strong>{{ __('Quote UUID') }}:</strong>
                                                            <span class="text-break">{{ $shipmentPreview['shipping_method']['quote_uuid'] ?? __('Not available') }}</span></p>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="card mb-4">
                                                    <div class="card-body">
                                                        <h6 class="mb-3">{{ __('Origin Address') }}</h6>
                                                        <p class="mb-1">{{ $shipmentPreview['origin']['company'] ?? '' }}</p>
                                                        <p class="mb-1">{{ $shipmentPreview['origin']['contact'] ?? '' }}</p>
                                                        <p class="mb-1">{{ $shipmentPreview['origin']['address1'] ?? '' }}
                                                            @if (!empty($shipmentPreview['origin']['address2']))
                                                                , {{ $shipmentPreview['origin']['address2'] }}
                                                            @endif
                                                        </p>
                                                        <p class="mb-1">
                                                            {{ $shipmentPreview['origin']['city'] ?? '' }},
                                                            {{ $shipmentPreview['origin']['province'] ?? '' }}
                                                            {{ $shipmentPreview['origin']['zip'] ?? '' }}
                                                        </p>
                                                        <p class="mb-1">{{ $shipmentPreview['origin']['country'] ?? '' }}</p>
                                                        <p class="mb-1">{{ $shipmentPreview['origin']['email'] ?? '' }}</p>
                                                        <p class="mb-0">{{ $shipmentPreview['origin']['phone'] ?? '' }}</p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="card mb-4">
                                            <div class="card-body">
                                                <h6 class="mb-3">{{ __('Destination Address') }}</h6>
                                                <p class="mb-1">{{ $shipmentPreview['destination']['attention'] ?? '' }}</p>
                                                @if (!empty($shipmentPreview['destination']['company']))
                                                    <p class="mb-1">{{ $shipmentPreview['destination']['company'] }}</p>
                                                @endif
                                                <p class="mb-1">{{ $shipmentPreview['destination']['address1'] ?? '' }}
                                                    @if (!empty($shipmentPreview['destination']['address2']))
                                                        , {{ $shipmentPreview['destination']['address2'] }}
                                                    @endif
                                                </p>
                                                <p class="mb-1">
                                                    {{ $shipmentPreview['destination']['city'] ?? '' }},
                                                    {{ $shipmentPreview['destination']['province'] ?? '' }}
                                                    {{ $shipmentPreview['destination']['zip'] ?? '' }}
                                                </p>
                                                <p class="mb-1">{{ $shipmentPreview['destination']['country'] ?? '' }}</p>
                                                <p class="mb-1">{{ $shipmentPreview['destination']['email'] ?? '' }}</p>
                                                <p class="mb-0">{{ $shipmentPreview['destination']['phone'] ?? '' }}</p>
                                            </div>
                                        </div>

                                        <div class="card">
                                            <div class="card-body">
                                                <h6 class="mb-3">{{ __('Package Summary') }}</h6>

                                                @if (count($shipmentPreview['packages']))
                                                    <div class="table-responsive">
                                                        <table class="table table-bordered mb-0">
                                                            <thead>
                                                                <tr>
                                                                    <th>{{ __('Description') }}</th>
                                                                    <th>{{ __('Length') }}</th>
                                                                    <th>{{ __('Width') }}</th>
                                                                    <th>{{ __('Height') }}</th>
                                                                    <th>{{ __('Weight') }}</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                @foreach ($shipmentPreview['packages'] as $package)
                                                                    <tr>
                                                                        <td>{{ $package['description'] }}</td>
                                                                        <td>{{ $package['length'] }}</td>
                                                                        <td>{{ $package['width'] }}</td>
                                                                        <td>{{ $package['height'] }}</td>
                                                                        <td>{{ $package['weight'] }}</td>
                                                                    </tr>
                                                                @endforeach
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                @else
                                                    <p class="mb-0 text-muted">
                                                        {{ __('No package data is available yet for this order.') }}
                                                    </p>
                                                @endif
                                            </div>
                                        </div>

                                        <div class="card mt-4">
                                            <div class="card-body">
                                                <h6 class="mb-3">{{ __('Shipment Status') }}</h6>
                                                <p class="mb-1"><strong>{{ __('Provider') }}:</strong>
                                                    {{ $shipmentPreview['shipment']['provider'] ?? __('Not available') }}</p>
                                                <p class="mb-1"><strong>{{ __('Shipment ID') }}:</strong>
                                                    <span class="text-break">{{ $shipmentPreview['shipment']['shipment_id'] ?? __('Not available') }}</span></p>
                                                <p class="mb-1"><strong>{{ __('Tracking Number') }}:</strong>
                                                    <span class="text-break">{{ $shipmentPreview['shipment']['tracking_number'] ?? __('Not available') }}</span></p>
                                                <p class="mb-1"><strong>{{ __('Created At') }}:</strong>
                                                    {{ $shipmentPreview['shipment']['created_at'] ?? __('Not available') }}</p>
                                                @if (!empty($shipmentPreview['shipment']['label_url']))
                                                    <p class="mb-1"><strong>{{ __('Remote Label URL') }}:</strong>
                                                        <a href="{{ $shipmentPreview['shipment']['label_url'] }}" target="_blank" rel="noopener">
                                                            {{ __('Open Label URL') }}
                                                        </a>
                                                    </p>
                                                @endif

                                                @if (!empty($shipmentPreview['shipment']['has_label_route']))
                                                    <a href="{{ route('back.order.shipment.label', $order->id) }}" target="_blank"
                                                        class="btn btn-outline-primary btn-sm mt-2">
                                                        {{ __('Open Saved Label') }}
                                                    </a>
                                                @endif

                                                @if (!empty($shipmentPreview['shipment']['meta']))
                                                    <details class="mt-3">
                                                        <summary>{{ __('Shipment Metadata') }}</summary>
                                                        <pre class="small bg-light border rounded p-3 mt-2 mb-0" style="white-space: pre-wrap;">{{ json_encode($shipmentPreview['shipment']['meta'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                                                    </details>
                                                @endif
                                            </div>
                                        </div>

                                        <p class="text-muted small mt-3 mb-0">
                                            {{ __('This preview is read-only. Live shipment creation and label purchase are intentionally not triggered from here yet.') }}
                                        </p>

                                        <form action="{{ route('back.order.shipment.create', $order->id) }}" method="POST" class="mt-3">
                                            @csrf
                                            <button type="submit" class="btn btn-primary"
                                                @if (empty($shipmentPreview['ready']) || !config('services.eshipper.allow_live_shipments')) disabled @endif>
                                                {{ __('Create Shipment') }}
                                            </button>
                                            @if (!config('services.eshipper.allow_live_shipments'))
                                                <p class="text-muted small mt-2 mb-0">
                                                    {{ __('Shipment creation is disabled in config. Enable ESHIPPER_ALLOW_LIVE_SHIPMENTS only when you are ready to create live billable shipments.') }}
                                                </p>
                                            @endif
                                        </form>
                                    </div>

                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

        </div>

    </div>
@endsection
