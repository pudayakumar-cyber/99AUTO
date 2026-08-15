@extends('master.back')

@section('content')
@php
    $isCreate = $mode === \App\Services\ItemCsvImporter::MODE_CREATE;
    $submitRoute = $isCreate ? 'back.uploads.new.generate' : 'back.uploads.update.generate';
@endphp

<style>
    .import-mode-card { border-left: 4px solid; }
    .import-mode-create { border-left-color: #198754; }
    .import-mode-update { border-left-color: #0d6efd; }
    .upload-box { border: 2px dashed #cbd5e1; border-radius: 10px; background: #f8fafc; }
    .rules-table th { width: 12rem; }
</style>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-3">
        <h3 class="mb-0 bc-title"><b>{{ __('Bulk product import') }}</b></h3>
        <a class="btn btn-primary btn-sm" href="{{ route('back.item.index') }}">
            <i class="fas fa-chevron-left"></i> {{ __('All products') }}
        </a>
    </div>

    @include('alerts.alerts')

    <div class="nav nav-pills mb-4">
        <a class="nav-link {{ $isCreate ? 'active' : '' }}" href="{{ route('back.uploads.new') }}">
            <i class="fas fa-plus-circle mr-1"></i>{{ __('Upload new products') }}
        </a>
        <a class="nav-link {{ !$isCreate ? 'active' : '' }}" href="{{ route('back.uploads.update') }}">
            <i class="fas fa-edit mr-1"></i>{{ __('Update existing products') }}
        </a>
    </div>

    <div class="row">
        <div class="col-xl-7 mb-4">
            <div class="card shadow-sm import-mode-card {{ $isCreate ? 'import-mode-create' : 'import-mode-update' }}">
                <div class="card-header bg-white">
                    <h4 class="mb-1">
                        {{ $isCreate ? __('New products only') : __('Existing product updates only') }}
                    </h4>
                    <p class="text-muted mb-0">
                        {{ $isCreate
                            ? __('This uploader can create products but cannot modify an existing product.')
                            : __('This uploader can modify products by Item ID but can never create a product.') }}
                    </p>
                </div>
                <div class="card-body">
                    <div class="alert {{ $isCreate ? 'alert-success' : 'alert-primary' }}">
                        @if($isCreate)
                            <strong>{{ __('Main identity column: Product Part Number') }}</strong><br>
                            {{ __('Every row requires a unique Product Part Number. Existing Product Part Number, Internal SKU, PROD NUMBER, or Transit SKU matches are skipped and are not updated.') }}
                        @else
                            <strong>{{ __('Only matching column: Item ID') }}</strong><br>
                            {{ __('Item ID must be copied from the website database/admin export. The product export column named id is also accepted. Product Part Number and product name are not used to locate the product. Unknown IDs are skipped.') }}
                        @endif
                    </div>

                    <a href="{{ route('back.uploads.template', ['mode' => $mode]) }}" class="btn btn-outline-dark mb-3">
                        <i class="fas fa-download mr-1"></i>{{ __('Download :mode example CSV', ['mode' => $isCreate ? 'new-product' : 'update']) }}
                    </a>

                    <form action="{{ route($submitRoute) }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        <div class="upload-box text-center p-4">
                            <i class="fas fa-file-csv fa-3x {{ $isCreate ? 'text-success' : 'text-primary' }} mb-3"></i>
                            <h5>{{ $isCreate ? __('Select new-product CSV/XLSX') : __('Select product-update CSV/XLSX') }}</h5>
                            <p class="text-muted">{{ __('Maximum file size: 100 MB. The first worksheet and first row must contain the documented headers.') }}</p>
                            <input type="file" name="file" class="form-control" accept=".csv,text/csv,.xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required>
                        </div>

                        <div class="custom-control custom-checkbox mt-3">
                            <input type="checkbox" class="custom-control-input" id="confirm_rules" name="confirm_rules" value="1" required>
                            <label class="custom-control-label" for="confirm_rules">
                                {{ $isCreate
                                    ? __('I confirm every row is intended to be a new product and follows the required template.')
                                    : __('I confirm Item ID values came from the website and blank cells should keep existing values.') }}
                            </label>
                        </div>

                        <button type="submit" class="btn {{ $isCreate ? 'btn-success' : 'btn-primary' }} mt-3 px-4">
                            <i class="fas fa-upload mr-1"></i>{{ $isCreate ? __('Queue new-product import') : __('Queue product updates') }}
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-xl-5 mb-4">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white"><h5 class="mb-0">{{ __('Strict file rules') }}</h5></div>
                <div class="card-body">
                    @if($isCreate)
                        <table class="table table-sm rules-table">
                            <tbody>
                                <tr><th>Title</th><td>{{ __('Required on every row') }}</td></tr>
                                <tr><th>Product Part Number</th><td>{{ __('Required, primary unique identity') }}</td></tr>
                                <tr><th>Brand</th><td>{{ __('Required') }}</td></tr>
                                <tr><th>Product Category</th><td>{{ __('Required') }}</td></tr>
                                <tr><th>ADJUSTED PRICE</th><td>{{ __('Required, zero or greater') }}</td></tr>
                                <tr><th>Stock</th><td>{{ __('Required, zero or greater') }}</td></tr>
                            </tbody>
                        </table>
                        <ul class="pl-3 mb-0">
                            <li>{{ __('One product per row. Do not upload update data here.') }}</li>
                            <li>{{ __('Delete the example row from the downloaded template before adding and uploading real products.') }}</li>
                            <li>{{ __('Internal SKU/PROD NUMBER and Transit SKU are additional duplicate guards.') }}</li>
                            <li>{{ __('Duplicate identifier rows are skipped; existing products remain unchanged.') }}</li>
                            <li>{{ __('Images should be public image URLs separated by a pipe (|).') }}</li>
                            <li>{{ __('Fitment should use Fitment Table/YMM or Year, Make, Model columns.') }}</li>
                        </ul>
                    @else
                        <table class="table table-sm rules-table">
                            <tbody>
                                <tr><th>Item ID / id</th><td>{{ __('Required on every row; exact numeric website database ID') }}</td></tr>
                                <tr><th>Other columns</th><td>{{ __('Only include fields that may be updated') }}</td></tr>
                                <tr><th>Blank cells</th><td>{{ __('Keep the existing database value') }}</td></tr>
                                <tr><th>Unknown Item ID</th><td>{{ __('Skip row; never insert') }}</td></tr>
                            </tbody>
                        </table>
                        <ul class="pl-3 mb-0">
                            <li>{{ __('Use one row per Item ID and remove the example row before uploading.') }}</li>
                            <li>{{ __('Product Part Number is update data, not a matching key on this page.') }}</li>
                            <li>{{ __('Changing SKU or Product Part Number to a value owned by another product is rejected.') }}</li>
                            <li>{{ __('Images only fill a missing main image; existing product images are preserved.') }}</li>
                            <li>{{ __('The website product export uses id; the example template uses Item ID. Both mean the same database value.') }}</li>
                            <li>{{ __('Do not use external supplier IDs, SKU, or Product Part Number as Item ID.') }}</li>
                            <li>{{ __('For a price-only update, include Item ID and ADJUSTED PRICE only.') }}</li>
                        </ul>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-header bg-white"><h5 class="mb-0">{{ __('Recent :mode uploads', ['mode' => $isCreate ? 'new-product' : 'update']) }}</h5></div>
        <div class="table-responsive">
            <table class="table table-sm table-striped mb-0">
                <thead><tr>
                    <th>#</th><th>{{ __('File') }}</th><th>{{ __('Mode') }}</th><th>{{ __('Status') }}</th>
                    <th>{{ __('Rows') }}</th><th>{{ __('Succeeded / skipped') }}</th><th>{{ __('When') }}</th>
                </tr></thead>
                <tbody>
                @forelse($uploads as $upload)
                    <tr>
                        <td>{{ $upload->id }}</td>
                        <td class="text-truncate" style="max-width:14rem">{{ basename($upload->file_path) }}</td>
                        <td><span class="badge badge-{{ $isCreate ? 'success' : 'primary' }}">{{ $upload->import_mode }}</span></td>
                        <td><span class="badge badge-{{ $upload->status === 'completed' ? 'success' : ($upload->status === 'failed' ? 'danger' : 'secondary') }}">{{ $upload->status }}</span></td>
                        <td>{{ $upload->processed_rows ?? 0 }} / {{ $upload->total_rows ?? '-' }}</td>
                        <td>{{ $upload->imported_count ?? 0 }} / {{ $upload->skipped_count ?? 0 }}</td>
                        <td class="small">{{ $upload->created_at }}</td>
                    </tr>
                    @if($upload->error_message)
                        <tr><td colspan="7" class="text-danger small">{{ \Illuminate\Support\Str::limit($upload->error_message, 200) }}</td></tr>
                    @endif
                @empty
                    <tr><td colspan="7" class="text-center text-muted py-3">{{ __('No uploads in this mode yet.') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-3 border-top">{{ $uploads->links() }}</div>
    </div>
</div>
@endsection
