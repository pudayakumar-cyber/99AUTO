@extends('master.back')

@section('content')

<!-- Start of Main Content -->
<div class="container-fluid">

	<!-- Page Heading -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="d-sm-flex align-items-center justify-content-between">
                <h3 class="mb-0 bc-title"><b>{{ __('Page SEO Settings') }}</b></h3>
            </div>
        </div>
    </div>

	<!-- DataTales -->
	<div class="card shadow mb-4">
		<div class="card-body">
			@include('alerts.alerts')
			<div class="gd-responsive-table">
				<table class="table table-bordered table-striped" id="admin-table" width="100%" cellspacing="0">

					<thead>
						<tr>
                            <th>{{ __('Page Name') }}</th>
                            <th>{{ __('Meta Title') }}</th>
                            <th>{{ __('Meta Description') }}</th>
							<th>{{ __('Actions') }}</th>
						</tr>
					</thead>

					<tbody>
                        @foreach($pages as $page)
                        <tr>
                            <td>
                                <b>{{ __($page->display_name) }}</b>
                            </td>
                            <td>
                                {{ $page->title }}
                            </td>
                            <td>
                                {{ Str::limit($page->meta_description, 100) }}
                            </td>
                            <td>
                                <div class="action-list">
                                    <a class="btn btn-secondary btn-sm" href="{{ route('back.page_seo.edit', $page->id) }}">
                                        <i class="fas fa-edit"></i> {{ __('Edit') }}
                                    </a>
                                </div>
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
