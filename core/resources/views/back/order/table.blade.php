@foreach ($datas as $data)
    <tr id="order-bulk-delete">
        <td><input type="checkbox" class="bulk-item" value="{{ $data->id }}"></td>

        <td>
            {{ $data->transaction_number }}
        </td>
        <td>
        
            {{ json_decode(@$data->billing_info, true)['bill_first_name'] }}
        </td>

        <td>
            @if ($setting->currency_direction == 1)
                {{ $data->currency_sign }}{{ PriceHelper::OrderTotal($data) }}
            @else
                {{ PriceHelper::OrderTotal($data) }}{{ $data->currency_sign }}
            @endif
        </td>

        <td>
            <div class="dropdown">
                <button
                    class="btn btn-{{ $data->payment_status == 'Paid' ? 'success' : 'danger' }} btn-sm dropdown-toggle"
                    type="button" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true"
                    aria-expanded="false">
                    {{ $data->payment_status == 'Paid' ? __('Paid') : __('Unpaid') }}
                </button>
                <div class="dropdown-menu animated--fade-in" aria-labelledby="dropdownMenuButton">
                    <a class="dropdown-item" data-toggle="modal" data-target="#statusModal" href="javascript:;"
                        data-href="{{ route('back.order.status', [$data->id, 'payment_status', 'Paid']) }}">{{ __('Paid') }}</a>
                    <a class="dropdown-item" data-toggle="modal" data-target="#statusModal" href="javascript:;"
                        data-href="{{ route('back.order.status', [$data->id, 'payment_status', 'Unpaid']) }}">{{ __('Unpaid') }}</a>
                </div>
            </div>
        </td>
        <td>
            <div class="dropdown">
                <button class="btn {{ $data->order_status }}  btn-sm dropdown-toggle" type="button"
                    id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                    {{ $data->order_status }}
                </button>
                <div class="dropdown-menu animated--fade-in" aria-labelledby="dropdownMenuButton">
                    <a class="dropdown-item" data-toggle="modal" data-target="#statusModal" href="javascript:;"
                        data-href="{{ route('back.order.status', [$data->id, 'order_status', 'Pending']) }}">{{ __('Pending') }}</a>
                    <a class="dropdown-item" data-toggle="modal" data-target="#statusModal" href="javascript:;"
                        data-href="{{ route('back.order.status', [$data->id, 'order_status', 'In Progress']) }}">{{ __('In Progress') }}</a>
                    <a class="dropdown-item" data-toggle="modal" data-target="#statusModal" href="javascript:;"
                        data-href="{{ route('back.order.status', [$data->id, 'order_status', 'Delivered']) }}">{{ __('Delivered') }}</a>
                    <a class="dropdown-item" data-toggle="modal" data-target="#statusModal" href="javascript:;"
                        data-href="{{ route('back.order.status', [$data->id, 'order_status', 'Canceled']) }}">{{ __('Canceled') }}</a>
                </div>
            </div>
        </td>
        <td>
            <form action="{{ route('back.order.tracking.update', $data->id) }}" method="POST">
                @csrf
                <input
                    type="text"
                    name="tracking_number"
                    class="form-control form-control-sm mr-2"
                    value="{{ $data->tracking_number }}"
                    placeholder="{{ __('Tracking Number') }}"
                    required
                >
                <details class="my-2">
                    <summary>{{ __('Shipping notification details') }}</summary>
                    <label class="d-block mt-2">{{ __('Carrier (optional)') }}
                        <input type="text" name="tracking_carrier" maxlength="150" class="form-control form-control-sm"
                            value="{{ data_get($data->tracking_details, 'tracking_carrier') }}">
                    </label>
                    <label class="d-block">{{ __('Carrier tracking link (HTTPS, optional)') }}
                        <input type="url" name="tracking_url" maxlength="2048" class="form-control form-control-sm"
                            value="{{ data_get($data->tracking_details, 'tracking_url') }}">
                    </label>
                    <label class="d-block">{{ __('Estimated delivery date (optional)') }}
                        <input type="date" name="estimated_delivery_date" class="form-control form-control-sm"
                            value="{{ data_get($data->tracking_details, 'estimated_delivery_date') }}">
                    </label>
                    <input type="hidden" name="out_for_delivery" value="0">
                    <label class="d-block">
                        <input type="checkbox" name="out_for_delivery" value="1">
                        {{ __('Carrier confirmed this shipment is out for delivery') }}
                    </label>
                    @if(data_get($data->tracking_details, 'out_for_delivery_at'))
                        <small class="d-block">{{ __('Previously confirmed:') }} {{ data_get($data->tracking_details, 'out_for_delivery_at') }}</small>
                    @endif
                    <small>{{ __('Enter only verified shipment details. Update these fields when replacing the tracking number.') }}</small>
                </details>
                <button type="submit" class="btn btn-primary btn-sm">{{ __('Save') }}</button>
            </form>
        </td>
        <td>
            <div class="action-list">
                <a class="btn btn-secondary btn-sm" href="{{ route('back.order.invoice', $data->id) }}">
                    <i class="fas fa-eye"></i>
                </a>
                <a class="btn btn-info btn-sm " href="{{ route('back.order.edit', $data->id) }}">
                    <i class="fas fa-pen"></i>
                </a>
                <a class="btn btn-danger btn-sm " data-toggle="modal" data-target="#confirm-delete" href="javascript:;"
                    data-href="{{ route('back.order.delete', $data->id) }}">
                    <i class="fas fa-trash-alt"></i>
                </a>

            </div>
        </td>
    </tr>
@endforeach
