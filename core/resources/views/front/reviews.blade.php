@extends('master.front')
@section('page_type', 'content')
@section('title', __('Customer Reviews'))
@section('meta')
<meta name="description" content="Read customer reviews of auto parts available at 99 Auto Parts.">
@endsection
@section('content')
<div class="container py-4">
    <h1 class="h4 mb-4">{{ __('Customer Reviews') }}</h1>
    @forelse ($reviews as $review)
        <article class="card mb-3">
            <div class="card-body">
                <h2 class="h5">{{ $review->subject ?: __('Product review') }}</h2>
                <p>{{ __('Rating') }}: {{ $review->rating }} / 5</p>
                <p>{{ $review->review }}</p>
                @if ($review->item->slug)
                    <a href="{{ \App\Support\ProductUrl::for($review->item) }}">{{ $review->item->name }}</a>
                @endif
            </div>
        </article>
    @empty
        <p>{{ __('No customer reviews are available yet.') }}</p>
    @endforelse
    {{ $reviews->links() }}
</div>
@endsection
