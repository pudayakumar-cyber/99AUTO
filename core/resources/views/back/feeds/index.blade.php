@extends('master.back')

@section('content')

<div class="container-fluid">

    {{-- Page Heading --}}
    <div class="card mb-4">
        <div class="card-body">
            <div class="d-sm-flex align-items-center justify-content-between">
                <h3 class="mb-0 bc-title">
                    <b>{{ __('Product Feed Exports') }}</b>
                </h3>

                <div>
                    <button id="generateFeedBtn" class="btn btn-success btn-sm">
                        <i class="fas fa-file-csv"></i> {{ __('Generate Product Feed') }}
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Export History Table --}}
    <div class="card shadow mb-4">
        <div class="card-body">

            @include('alerts.alerts')

            <div class="table-responsive">
                <table class="table table-bordered table-striped" width="100%">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>{{ __('File Name') }}</th>
                            <th>{{ __('Total Records') }}</th>
                            <th>{{ __('Processed') }}</th>
                            <th>{{ __('Progress') }}</th>
                            <th>{{ __('Status') }}</th>
                            <th>{{ __('Created At') }}</th>
                            <th>{{ __('Action') }}</th>
                        </tr>
                    </thead>
                    <tbody id="exportTableBody">
                        @foreach($exports as $export)
                            <tr>
                                <td>{{ $export->id }}</td>
                                <td>{{ $export->file_name ?? '—' }}</td>
                                <td>{{ $export->total_records }}</td>
                                <td>{{ $export->processed_records }}</td>
                                <td>
                                    <div class="progress">
                                        <div class="progress-bar"
                                             style="width: {{ $export->progress }}%">
                                            {{ $export->progress }}%
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge badge-{{ 
                                        $export->status == 'completed' ? 'success' :
                                        ($export->status == 'processing' ? 'info' :
                                        ($export->status == 'failed' ? 'danger' : 'secondary')) }}">
                                        {{ ucfirst($export->status) }}
                                    </span>
                                </td>
                                <td>{{ $export->created_at }}</td>
                                <td>
                                    @if($export->status == 'completed')
                                        <a href="{{ route('back.feeds.download', $export->id) }}"
                                            class="btn btn-sm btn-primary">
                                                Download
                                        </a>
                                        <button 
                                            class="btn btn-sm btn-danger deleteExportBtn"
                                            data-id="{{ $export->id }}">
                                            Delete
                                        </button>
                                    @else
                                        —
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

        </div>
    </div>

</div>

@endsection

@section('scripts')

@section('scripts')

<script>
document.getElementById('generateFeedBtn').addEventListener('click', function () {

    fetch("{{ route('back.feeds.generate') }}", {
        method: "POST",
        headers: {
            "X-CSRF-TOKEN": "{{ csrf_token() }}",
            "Accept": "application/json"
        }
    })
    .then(res => res.json())
    .then(data => {

        // Immediately add new row
        addNewRow(data.export);

        // Start polling progress
        startPolling(data.export.id);

    });

});


function addNewRow(exportData) {

    let row = `
        <tr id="export-row-${exportData.id}">
            <td>${exportData.id}</td>
            <td>—</td>
            <td>0</td>
            <td>0</td>
            <td>
                <div class="progress">
                    <div class="progress-bar" style="width:0%">0%</div>
                </div>
            </td>
            <td><span class="badge badge-secondary">Pending</span></td>
            <td>${exportData.created_at}</td>
            <td>—</td>
        </tr>
    `;

    document.getElementById('exportTableBody')
        .insertAdjacentHTML('afterbegin', row);
}


function startPolling(exportId) {

    let interval = setInterval(() => {

        fetch("{{ route('back.feeds.progress', '') }}/" + exportId)
        .then(res => res.json())
        .then(data => {

            let row = document.getElementById(`export-row-${exportId}`);

            if (!row) return;

            row.children[2].innerText = data.total_records;
            row.children[3].innerText = data.processed_records;

            let progressBar = row.querySelector('.progress-bar');
            progressBar.style.width = data.progress + '%';
            progressBar.innerText = data.progress + '%';

            let badgeClass = {
                pending: 'secondary',
                processing: 'info',
                completed: 'success',
                failed: 'danger'
            }[data.status];

            row.children[5].innerHTML =
                `<span class="badge badge-${badgeClass}">
                    ${data.status.charAt(0).toUpperCase() + data.status.slice(1)}
                </span>`;

            if (data.status === 'completed') {

                row.children[7].innerHTML =
                    `<a href="/storage/exports/${data.file_name}"
                       class="btn btn-sm btn-primary">Download</a>`;

                clearInterval(interval);
            }

        });

    }, 3000);
}

document.addEventListener('click', function(e) {

    if (e.target.classList.contains('deleteExportBtn')) {

        let exportId = e.target.dataset.id;

        if (!confirm('Are you sure you want to delete this export?')) {
            return;
        }

        fetch("{{ route('back.feeds.delete', '') }}/" + exportId, {
            method: "DELETE",
            headers: {
                "X-CSRF-TOKEN": "{{ csrf_token() }}",
                "Accept": "application/json"
            }
        })
        .then(res => res.json())
        .then(data => {

            if (data.success) {
                location.reload();
            }

        });
    }

});
</script>

@endsection