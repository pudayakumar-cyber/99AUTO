@extends('master.back')

@section('content')

<div class="container-fluid">

	<!-- Page Heading -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="d-sm-flex align-items-center justify-content-between">
                <h3 class="mb-0 bc-title"><b>{{ __('Update Page SEO') }}</b> </h3>
                <a class="btn btn-primary btn-sm" href="{{ route('back.page_seo.index') }}"><i class="fas fa-chevron-left"></i> {{ __('Back') }}</a>
                </div>
        </div>
    </div>

	<!-- Form -->
	<div class="row">

		<div class="col-xl-12 col-lg-12 col-md-12">

			<div class="card o-hidden border-0 shadow-lg">
				<div class="card-body ">
					<!-- Nested Row within Card Body -->
					<div class="row justify-content-center">
						<div class="col-lg-12">
								<form class="admin-form" action="{{ route('back.page_seo.update', $page->id) }}"
									method="POST">

                                    @csrf

									@include('alerts.alerts')

									<div class="form-group">
										<label for="display_name">{{ __('Page / Section') }}</label>
										<input type="text" class="form-control" id="display_name" value="{{ __($page->display_name) }}" readonly disabled>
									</div>

									<div class="form-group">
										<label for="title">{{ __('Meta Title') }} *</label>
										<input type="text" name="title" class="form-control" id="title"
											placeholder="{{ __('Enter Meta Title') }}" value="{{ $page->title }}" required>
									</div>

									<div class="form-group">
										<label for="meta_keywords">{{ __('Meta Keywords') }}</label>
										<input type="text" name="meta_keywords" class="tags"
											id="meta_keywords"
											placeholder="{{ __('Enter Meta Keywords') }}"
											value="{{ $page->meta_keywords }}">
									</div>

									<div class="form-group">
										<label for="meta_description">{{ __('Meta Description') }} *</label>
										<textarea name="meta_description" id="meta_description"
											class="form-control" rows="5"
											placeholder="{{ __('Enter Meta Description') }}" required
										>{{ $page->meta_description }}</textarea>
									</div>

									<div class="form-group">
										<button type="submit"
											class="btn btn-secondary ">{{ __('Submit') }}</button>
									</div>
								</form>
						</div>
					</div>
				</div>
			</div>

		</div>

	</div>

</div>

@endsection
