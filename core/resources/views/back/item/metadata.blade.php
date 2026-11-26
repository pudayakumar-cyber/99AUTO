@extends('master.back')

@section('content')
@php
    $fallbackItem = clone $item;
    $fallbackItem->seo_title = null;
    $fallbackItem->seo_meta_description = null;
@endphp
<div class="container-fluid">
    <div class="card mb-4">
        <div class="card-body d-sm-flex align-items-center justify-content-between">
            <h3 class="mb-0 bc-title"><b>{{ __('Product SEO Metadata') }}</b></h3>
            @php
                $returnRoute = [
                    'normal' => 'back.item.edit',
                    'digital' => 'back.digital.item.edit',
                    'license' => 'back.license.item.edit',
                    'affiliate' => 'back.affiliate.edit',
                ][$item->item_type] ?? 'back.item.index';
            @endphp
            <a class="btn btn-primary btn-sm" href="{{ $returnRoute === 'back.item.index' ? route($returnRoute) : route($returnRoute, $item->id) }}">{{ __('Back to Product') }}</a>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <p>{{ __('Product') }}: {{ $item->name }} (ID {{ $item->id }})</p>
            <p class="text-muted">{{ __('Optional custom metadata. Leave a field blank to use the storefront fallback; saving a blank field clears its custom value.') }}</p>
            @if (session('success'))
                <div class="alert alert-success" role="status">{{ session('success') }}</div>
            @endif

            <form action="{{ route('back.item.metadata.update', ['item' => $item->id]) }}" method="POST">
                @csrf
                <div class="form-group">
                    <label for="seo_title">{{ __('Custom SEO Title') }}</label>
                    <input type="text" id="seo_title" name="seo_title" class="form-control" maxlength="255"
                        value="{{ old('seo_title', $item->seo_title) }}"
                        placeholder="{{ \App\Support\ProductMetaTitle::for($fallbackItem) }}">
                    <small class="form-text text-muted">{{ __('Fallback preview (not saved):') }} {{ \App\Support\ProductMetaTitle::for($fallbackItem) }}</small>
                    @if ($errors->has('seo_title'))
                        <div class="text-danger" role="alert">{{ $errors->first('seo_title') }}</div>
                    @endif
                </div>
                <div class="form-group">
                    <label for="seo_meta_description">{{ __('Custom SEO Meta Description') }}</label>
                    <textarea id="seo_meta_description" name="seo_meta_description" class="form-control" rows="5" maxlength="1000"
                        placeholder="{{ \App\Support\ProductMetaDescription::for($fallbackItem) }}">{{ old('seo_meta_description', $item->seo_meta_description) }}</textarea>
                    <small class="form-text text-muted">{{ __('Fallback preview (not saved):') }} {{ \App\Support\ProductMetaDescription::for($fallbackItem) }}</small>
                    @if ($errors->has('seo_meta_description'))
                        <div class="text-danger" role="alert">{{ $errors->first('seo_meta_description') }}</div>
                    @endif
                </div>
                <button type="submit" class="btn btn-primary">{{ __('Save SEO Metadata') }}</button>
            </form>
        </div>
    </div>
</div>
@endsection
