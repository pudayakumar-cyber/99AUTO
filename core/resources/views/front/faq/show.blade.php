@extends('master.front')
@section('meta')
<meta name="keywords" content="{{$category->meta_keywords}}">
<meta name="description" content="{{$category->meta_descriptions}}">
@endsection
@section('title')
    {{__('FAQ')}}
@endsection

@section('content')
<style>
    .faq-page-wrap {
        padding: 36px 0 56px;
    }

    .faq-hero {
        background: #fff;
        border-radius: 18px;
        padding: 36px 40px;
        box-shadow: 0 10px 28px rgba(15, 23, 42, 0.06);
        margin-bottom: 28px;
    }

    .faq-title {
        margin: 0 0 12px;
        color: #0f172a;
        font-size: 40px;
        font-weight: 700;
        line-height: 1.15;
        letter-spacing: -0.02em;
    }

    .faq-intro {
        margin: 0;
        color: #475569;
        font-size: 16px;
        line-height: 1.8;
    }

    .faq-section {
        margin-bottom: 28px;
    }

    .faq-section-title {
        margin: 0 0 16px;
        color: #0f172a;
        font-size: 24px;
        font-weight: 700;
        line-height: 1.25;
    }

    .faq-item {
        border: 0;
        border-radius: 16px;
        overflow: hidden;
        box-shadow: 0 8px 24px rgba(15, 23, 42, 0.06);
        margin-bottom: 16px;
    }

    .faq-item .card-header {
        background: #fff;
        border: 0;
        padding: 0;
    }

    .faq-item .accordion-button {
        margin: 0;
        padding: 0;
    }

    .faq-item .accordion-button a {
        display: block;
        width: 100%;
        padding: 20px 24px;
        color: #0f172a;
        font-size: 18px;
        font-weight: 600;
        line-height: 1.45;
        text-decoration: none;
    }

    .faq-item .card-body {
        padding: 0 24px 22px;
        color: #475569;
        font-size: 16px;
        line-height: 1.8;
    }

    @media (max-width: 767.98px) {
        .faq-page-wrap {
            padding: 28px 0 44px;
        }

        .faq-hero {
            padding: 28px 20px;
        }

        .faq-title {
            font-size: 32px;
        }

        .faq-item .accordion-button a,
        .faq-item .card-body {
            padding-left: 18px;
            padding-right: 18px;
        }
    }
</style>
    <!-- Page Title-->
<div class="page-title">
    <div class="container">
        <div class="row">
            <div class="col-lg-12">
                <ul class="breadcrumbs">
                    <li><a href="{{route('front.index')}}">{{__('Home')}}</a>
                    </li>
                    <li class="separator">&nbsp;</li>
                    <li><a href="{{route('front.faq')}}">{{__('FAQ')}}</a>
                    </li>
                    <li class="separator">&nbsp;</li>
                    <li>{{$category->name}}</li>
                  </ul>
            </div>
        </div>
    </div>
  </div>
  <!-- Page Content-->
  <div class="faq-page-wrap">
      <div class="container">
          <div class="faq-hero">
              <h1 class="faq-title">{{ $category->name }}</h1>
              @if($category->text)
                  <p class="faq-intro">{{ $category->text }}</p>
              @endif
          </div>

          @php
              $groupedFaqs = $category->faqs->groupBy(function ($faq) {
                  if (\Illuminate\Support\Str::contains($faq->title, ['fit my vehicle', 'kind of auto parts', 'physical catalog', 'product image'])) {
                      return 'Finding and Ordering Parts';
                  }
                  if (\Illuminate\Support\Str::contains($faq->title, ['free shipping', 'shipping carriers', 'take for my order to arrive', "hasn't arrived", 'internationally'])) {
                      return 'Shipping and Delivery';
                  }
                  if (\Illuminate\Support\Str::contains($faq->title, ['return policy', 'damaged or incorrect part', 'return shipping', 'process a refund', 'cancel an order'])) {
                      return 'Returns and Refunds';
                  }

                  return 'Payment and Account';
              });
          @endphp

          @foreach ($groupedFaqs as $section => $faqs)
              <div class="faq-section">
                  <h2 class="faq-section-title">{{ $section }}</h2>
                  <div class="accordion" id="accordion-{{ \Illuminate\Support\Str::slug($section) }}">
                      @foreach ($faqs as $key => $faq)
                          <div class="card faq-item">
                              <div class="card-header" id="heading-{{ \Illuminate\Support\Str::slug($section) }}-{{ $key }}">
                                  <h3 class="accordion-button">
                                      <a href="#collapse-{{ \Illuminate\Support\Str::slug($section) }}-{{ $key }}" data-bs-toggle="collapse" data-bs-target="#collapse-{{ \Illuminate\Support\Str::slug($section) }}-{{ $key }}" aria-expanded="false" aria-controls="collapse-{{ \Illuminate\Support\Str::slug($section) }}-{{ $key }}">
                                          {{ $faq->title }}
                                      </a>
                                  </h3>
                              </div>
                              <div id="collapse-{{ \Illuminate\Support\Str::slug($section) }}-{{ $key }}" class="accordion-collapse collapse" aria-labelledby="heading-{{ \Illuminate\Support\Str::slug($section) }}-{{ $key }}" data-bs-parent="#accordion-{{ \Illuminate\Support\Str::slug($section) }}">
                                  <div class="card-body">{{ $faq->details }}</div>
                              </div>
                          </div>
                      @endforeach
                  </div>
              </div>
          @endforeach
      </div>
  </div>

@endsection
