@foreach($datas as $data)
<tr>
    <td>
        <img src="{{ $data->photo ? url('/core/public/storage/images/'.$data->photo) : url('/core/public/storage/images/placeholder.png') }}" alt="Image Not Found">
    </td>
    <td>
        {{ $data->name }}
    </td>
    <td>
        @if($data->package_length && $data->package_width && $data->package_height && $data->package_weight)
            {{ number_format((float) $data->package_length, 2) }} x {{ number_format((float) $data->package_width, 2) }} x {{ number_format((float) $data->package_height, 2) }} in
            <br>
            {{ number_format((float) $data->package_weight, 2) }} lb
        @else
            <span class="text-warning">{{ __('Not set') }}</span>
        @endif
    </td>

    <td>

        <div class="dropdown">
            <button class="btn btn-{{  $data->status == 1 ? 'success' : 'danger'  }} btn-sm  dropdown-toggle" type="button" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
              {{  $data->status == 1 ? __('Enabled') : __('Disabled')  }}
            </button>
            <div class="dropdown-menu animated--fade-in" aria-labelledby="dropdownMenuButton">
              <a class="dropdown-item" href="{{ route('back.category.status',[$data->id,1]) }}">{{ __('Enable') }}</a>
              <a class="dropdown-item" href="{{ route('back.category.status',[$data->id,0]) }}">{{ __('Disable') }}</a>
            </div>
          </div>

        </div>

    </td>
    <td>
        <div class="action-list">
            <a class="btn btn-secondary btn-sm "
                href="{{ route('back.category.edit',$data->id) }}">
                <i class="fas fa-edit"></i>
            </a>
            <a class="btn btn-danger btn-sm " data-toggle="modal"
                data-target="#confirm-delete" href="javascript:;"
                data-href="{{ route('back.category.destroy',$data->id) }}">
                <i class="fas fa-trash-alt"></i>
            </a>
        </div>
    </td>
</tr>
@endforeach
