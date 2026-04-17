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
        padding: 42px 0 64px;
    }

    .faq-shell {
        max-width: 1040px;
        margin: 0 auto;
    }

    .faq-hero {
        position: relative;
        background: linear-gradient(135deg, #ffffff 0%, #fff6f6 100%);
        border: 1px solid rgba(220, 38, 38, 0.08);
        border-radius: 20px;
        padding: 40px 44px;
        box-shadow: 0 14px 34px rgba(15, 23, 42, 0.07);
        margin-bottom: 34px;
        overflow: hidden;
    }

    .faq-hero::after {
        content: "";
        position: absolute;
        top: -56px;
        right: -56px;
        width: 180px;
        height: 180px;
        border-radius: 999px;
        background: radial-gradient(circle, rgba(220, 38, 38, 0.12), rgba(220, 38, 38, 0));
        pointer-events: none;
    }

    .faq-title {
        margin: 0 0 14px;
        color: #0f172a;
        font-size: 44px;
        font-weight: 700;
        line-height: 1.15;
        letter-spacing: -0.02em;
    }

    .faq-intro {
        margin: 0;
        max-width: 760px;
        color: #334155;
        font-size: 17px;
        line-height: 1.85;
    }

    .faq-section {
        margin-bottom: 34px;
    }

    .faq-section-title {
        display: inline-flex;
        align-items: center;
        gap: 10px;
        margin: 0 0 18px;
        color: #0f172a;
        font-size: 28px;
        font-weight: 700;
        line-height: 1.25;
    }

    .faq-section-title::before {
        content: "";
        width: 4px;
        height: 28px;
        border-radius: 999px;
        background: #dc2626;
    }

    .faq-item {
        border: 1px solid #e5e7eb;
        border-radius: 18px;
        overflow: hidden;
        box-shadow: 0 8px 24px rgba(15, 23, 42, 0.05);
        margin-bottom: 14px;
        background: #ffffff !important;
    }

    .faq-item .card-header {
        background: #ffffff !important;
        border: 0 !important;
        padding: 0;
    }

    .faq-item .accordion-button {
        margin: 0 !important;
        padding: 0;
        background: transparent !important;
        box-shadow: none !important;
    }

    .faq-item .card-header h3,
    .faq-item .card-header h6 {
        margin: 0 !important;
        background: #ffffff !important;
    }

    .faq-item .accordion-button::after,
    .faq-item .accordion-button::before,
    .faq-item .card-header::after,
    .faq-item .card-header::before,
    .faq-item .card-header a::before {
        display: none !important;
        content: none !important;
    }

    .faq-item .accordion-button a {
        position: relative;
        display: block;
        width: 100%;
        padding: 22px 60px 22px 24px;
        color: #0f172a !important;
        font-size: 18px;
        font-weight: 600;
        line-height: 1.45;
        text-decoration: none;
        transition: color 0.2s ease, background-color 0.2s ease;
        background: #ffffff !important;
        border: 0 !important;
        box-shadow: none !important;
    }

    .faq-item .accordion-button a::after {
        content: "+";
        position: absolute;
        top: 50%;
        right: 22px;
        transform: translateY(-50%);
        width: 28px;
        height: 28px;
        border-radius: 999px;
        background: #fee2e2;
        color: #dc2626;
        font-size: 18px;
        font-weight: 700;
        line-height: 28px;
        text-align: center;
    }

    .faq-item .card-body {
        padding: 0 24px 24px;
        color: #475569;
        font-size: 16px;
        line-height: 1.82;
        background: #ffffff !important;
        border-top: 1px solid #f1f5f9 !important;
    }

    .faq-item .accordion-collapse.show + .card-body {
        border-top-color: #fecaca;
    }

    .faq-item .accordion-collapse.show,
    .faq-item .accordion-collapse.collapsing {
        border-top: 1px solid #fecaca;
    }

    .faq-item .accordion-collapse.show ~ .card-body,
    .faq-item .accordion-collapse.collapsing ~ .card-body {
        background: #ffffff;
    }

    .faq-item .accordion-button a[aria-expanded="true"] {
        color: #b91c1c;
        background: #ffffff !important;
    }

    .faq-item .accordion-button a[aria-expanded="true"]::after {
        content: "-";
        background: #dc2626;
        color: #ffffff;
    }

    .faq-item .accordion-button a:hover {
        background: #ffffff !important;
        color: #b91c1c;
    }

    .scroll-to-top-btn {
        display: none !important;
    }

    @media (max-width: 767.98px) {
        .faq-page-wrap {
            padding: 28px 0 46px;
        }

        .faq-hero {
            padding: 28px 20px;
            margin-bottom: 28px;
        }

        .faq-title {
            font-size: 32px;
        }

        .faq-section-title {
            font-size: 24px;
        }

        .faq-item .card-body {
            padding-left: 18px;
            padding-right: 18px;
        }

        .faq-item .accordion-button a {
            padding: 18px 52px 18px 18px;
            font-size: 17px;
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
          <div class="faq-shell">
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
  </div>

@endsection

