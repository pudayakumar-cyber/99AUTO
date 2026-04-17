@extends('master.back')

@section('content')

<div class="container-fluid">

	<!-- Page Heading -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="d-sm-flex align-items-center justify-content-between">
                <h3 class="mb-0 bc-title"><b>{{ __('Create Category') }}</b> </h3>
                <a class="btn btn-primary btn-sm" href="{{route('back.category.index')}}"><i class="fas fa-chevron-left"></i> {{ __('Back') }}</a>
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
								<form class="admin-form" action="{{ route('back.category.store') }}" method="POST"
									enctype="multipart/form-data">

                                    @csrf

									@include('alerts.alerts')

									<div class="form-group">
										<label for="name">{{ __('Set Image') }} *</label>
                                        <br>
										<img class="admin-img" src="{{  url('/core/public/storage/images/placeholder.png') }}"
												alt="No Image Found">
                                        <br>
										<span class="mt-1">{{ __('Image Size Should Be 60 x 60.') }}</span>
									</div>

									<div class="form-group position-relative">
										<label class="file">
											<input type="file"  accept="image/*"  class="upload-photo" name="photo" id="file"
												aria-label="File browser example" >
											<span class="file-custom text-left">{{ __('Upload Image...') }}</span>
										</label>
                                    </div>

									<div class="form-group">
										<label for="name">{{ __('Name') }} *</label>
										<input type="text" name="name" class="form-control item-name" id="name"
											placeholder="{{ __('Enter Name') }}" value="{{ old('name') }}" >
									</div>

									<div class="form-group">
										<label for="slug">{{ __('Slug') }} *</label>
										<input type="text" name="slug" class="form-control" id="slug"
											placeholder="{{ __('Enter Slug') }}" value="{{ old('slug') }}" >
									</div>

									<div class="form-group">
										<label for="meta_keywords">{{ __('Meta Keywords') }}
											</label>
										<input type="text" name="meta_keywords" class="tags"
											id="meta_keywords"
											placeholder="{{ __('Enter Meta Keywords') }}"
											value="">
									</div>

									<div class="form-group">
										<label
											for="meta_description">{{ __('Meta Description') }}
											</label>
										<textarea name="meta_descriptions" id="meta_description"
											class="form-control" rows="5"
											placeholder="{{ __('Enter Meta Description') }}"
										></textarea>
									</div>

									<div class="form-group">
										<label for="serial">{{ __('Serial') }} *</label>
										<input type="number" name="serial" class="form-control" id="serial"
											placeholder="{{ __('Enter Serial Number') }}" value="0">
									</div>

									<div class="card mb-4">
										<div class="card-body">
											<h5 class="mb-3">{{ __('Default Shipping Package') }}</h5>
											<p class="text-muted mb-3">{{ __('Set rough category-level estimates in inches and pounds for eShipper checkout rates.') }}</p>
											<div class="row">
												<div class="col-md-3">
													<div class="form-group">
														<label for="package_length">{{ __('Length (in)') }}</label>
														<input type="number" step="0.01" min="0" name="package_length" class="form-control" id="package_length"
															placeholder="{{ __('e.g. 6') }}" value="{{ old('package_length') }}">
													</div>
												</div>
												<div class="col-md-3">
													<div class="form-group">
														<label for="package_width">{{ __('Width (in)') }}</label>
														<input type="number" step="0.01" min="0" name="package_width" class="form-control" id="package_width"
															placeholder="{{ __('e.g. 4') }}" value="{{ old('package_width') }}">
													</div>
												</div>
												<div class="col-md-3">
													<div class="form-group">
														<label for="package_height">{{ __('Height (in)') }}</label>
														<input type="number" step="0.01" min="0" name="package_height" class="form-control" id="package_height"
															placeholder="{{ __('e.g. 4') }}" value="{{ old('package_height') }}">
													</div>
												</div>
												<div class="col-md-3">
													<div class="form-group">
														<label for="package_weight">{{ __('Weight (lb)') }}</label>
														<input type="number" step="0.01" min="0" name="package_weight" class="form-control" id="package_weight"
															placeholder="{{ __('e.g. 3') }}" value="{{ old('package_weight') }}">
													</div>
												</div>
											</div>
										</div>
									</div>

									<div class="form-group">
										<button type="submit"
											class="btn btn-secondary ">{{ __('Submit') }}</button>
									</div>

									<div>
								</form>
						</div>
					</div>
				</div>
			</div>

		</div>

	</div>

</div>

@endsection
