@extends('master.back')

@section('content')
    <style>

        .upload-box{
            border:2px dashed #d1d5db;
            border-radius:10px;
            background:#fafafa;
            transition:all 0.3s ease;
        }

        .upload-box:hover{
            border-color:#0d6efd;
            background:#f4f8ff;
        }

    </style>
    <div class="container-fluid">

        <div class="d-sm-flex align-items-center justify-content-between mb-3">
            <h3 class="mb-0 bc-title"><b>{{ __('Bulk product import') }}</b></h3>
            <a class="btn btn-primary btn-sm" href="{{ route('back.item.index') }}"><i class="fas fa-chevron-left"></i> {{ __('All products') }}</a>
        </div>

        @include('alerts.alerts')

        <div class="row justify-content-center">
            <div class="col-md-11 col-xl-10">

                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-white border-bottom">
                        <h4 class="mb-0">
                            <i class="fas fa-cloud-upload-alt text-primary"></i>
                            {{ __('Queue-based CSV import') }}
                        </h4>
                    </div>
                    <div class="card-body">
                        <p class="text-muted small mb-3">
                            {{ __('Large files are processed in the background (queue). Ensure a worker is running:') }}
                            <code>php artisan queue:work</code>
                        </p>
                        <p class="small mb-3">
                            <strong>{{ __('Required / common columns') }}:</strong>
                            Title, PROD NUMBER, MOOG, Brand, Category, Images, ADJUSTED PRICE,
                            <strong>Description</strong>, <strong>Product Features</strong>, <strong>Fitment Table</strong> (or <strong>YMM</strong> / <strong>YMM Rows</strong>)
                            — for vehicle search, use an HTML table (Year | Make | Model columns) or one row per line:
                            <code>2015,2016|Honda|Civic</code> (pipe), tab-separated, or 3-field CSV. Tables get class <code>pa-fitment-table</code> automatically.
                            {{ __('Legacy aliases') }}: Product Highlights, Product Overview, Specifications, Fitting Vehicles.
                        </p>
                        <form action="{{ route('back.uploads.generate') }}" method="POST" enctype="multipart/form-data">
                            @csrf
                            <div class="upload-box text-center p-4">
                                <i class="fas fa-file-csv fa-3x text-primary mb-3"></i>
                                <h5 class="mb-2">{{ __('Upload product CSV') }}</h5>
                                <p class="text-muted mb-4">{{ __('First row must be headers matching the column names above.') }}</p>
                                <input type="file" name="file" class="form-control" accept=".csv,text/csv" required>
                                <small class="text-muted d-block mt-2">CSV</small>
                            </div>
                            <div class="text-center mt-4">
                                <button type="submit" class="btn btn-primary px-4 py-2">
                                    <i class="fas fa-upload mr-2"></i>{{ __('Queue import') }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card shadow-sm border-0">
                    <div class="card-header bg-white border-bottom">
                        <h5 class="mb-0">{{ __('Recent uploads') }}</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-sm table-striped mb-0">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>{{ __('File') }}</th>
                                        <th>{{ __('Status') }}</th>
                                        <th>{{ __('Rows') }}</th>
                                        <th>{{ __('Imported / skipped') }}</th>
                                        <th>{{ __('When') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($uploads as $u)
                                        <tr>
                                            <td>{{ $u->id }}</td>
                                            <td class="text-truncate" style="max-width:12rem">{{ basename($u->file_path) }}</td>
                                            <td><span class="badge badge-{{ $u->status === 'completed' ? 'success' : ($u->status === 'failed' ? 'danger' : 'secondary') }}">{{ $u->status }}</span></td>
                                            <td>{{ $u->processed_rows ?? 0 }} / {{ $u->total_rows ?? '—' }}</td>
                                            <td>{{ $u->imported_count ?? 0 }} / {{ $u->skipped_count ?? 0 }}</td>
                                            <td class="small">{{ $u->created_at }}</td>
                                        </tr>
                                        @if($u->error_message)
                                            <tr><td colspan="6" class="text-danger small">{{ \Illuminate\Support\Str::limit($u->error_message, 200) }}</td></tr>
                                        @endif
                                    @empty
                                        <tr><td colspan="6" class="text-center text-muted py-3">{{ __('No uploads yet.') }}</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                        <div class="p-3 border-top">
                            {{ $uploads->links() }}
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm border-0 mt-4">
                    <div class="card-header bg-white border-bottom">
                        <h5 class="mb-0">{{ __('Chunk jobs (queue)') }}</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-sm table-striped mb-0">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>{{ __('Queue') }}</th>
                                        <th>{{ __('Job') }}</th>
                                        <th>{{ __('Attempts') }}</th>
                                        <th>{{ __('State') }}</th>
                                        <th>{{ __('Created') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($chunkJobs as $j)
                                        @php
                                            $payload = json_decode($j->payload, true) ?: [];
                                            $displayName = $payload['displayName'] ?? '—';
                                            $isRunning = !is_null($j->reserved_at);
                                        @endphp
                                        <tr>
                                            <td>{{ $j->id }}</td>
                                            <td>{{ $j->queue }}</td>
                                            <td class="text-break">{{ class_basename($displayName) }}</td>
                                            <td>{{ $j->attempts }}</td>
                                            <td>
                                                <span class="badge badge-{{ $isRunning ? 'warning' : 'secondary' }}">
                                                    {{ $isRunning ? __('running') : __('pending') }}
                                                </span>
                                            </td>
                                            <td class="small">{{ \Carbon\Carbon::createFromTimestamp($j->created_at)->toDateTimeString() }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="6" class="text-center text-muted py-3">{{ __('No chunk jobs in queue.') }}</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                        <div class="p-3 border-top">
                            {{ $chunkJobs->links() }}
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm border-0 mt-4">
                    <div class="card-header bg-white border-bottom">
                        <h5 class="mb-0">{{ __('Batch runs') }}</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-sm table-striped mb-0">
                                <thead>
                                    <tr>
                                        <th>{{ __('Batch ID') }}</th>
                                        <th>{{ __('Name') }}</th>
                                        <th>{{ __('Jobs') }}</th>
                                        <th>{{ __('Pending') }}</th>
                                        <th>{{ __('Failed') }}</th>
                                        <th>{{ __('Status') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($batchRuns as $b)
                                        <tr>
                                            <td class="small text-monospace">{{ \Illuminate\Support\Str::limit($b->id, 12) }}</td>
                                            <td>{{ $b->name }}</td>
                                            <td>{{ $b->total_jobs }}</td>
                                            <td>{{ $b->pending_jobs }}</td>
                                            <td>{{ $b->failed_jobs }}</td>
                                            <td>
                                                <span class="badge badge-{{ $b->finished_at ? 'success' : 'warning' }}">
                                                    {{ $b->finished_at ? __('finished') : __('running') }}
                                                </span>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="6" class="text-center text-muted py-3">{{ __('No product upload batches yet.') }}</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                        <div class="p-3 border-top">
                            {{ $batchRuns->links() }}
                        </div>
                    </div>
                </div>

            </div>
        </div>

    </div>

@endsection