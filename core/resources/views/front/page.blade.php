@extends('master.front')

@section('title')
    {{__('Page')}}
@endsection

@section('content')
<style>
    .managed-page-wrap {
        padding: 48px 0 64px;
    }

    .managed-page-card {
        border: 0;
        border-radius: 18px;
        box-shadow: 0 10px 28px rgba(15, 23, 42, 0.06);
        overflow: hidden;
    }

    .managed-page-card .card-body {
        padding: 48px 56px;
    }

    .managed-page-content {
        color: #334155;
        font-family: inherit;
        line-height: 1.75;
        font-size: 16px;
    }

    .managed-page-title {
        margin: 0 0 24px;
        color: #0f172a;
        font-size: 42px;
        font-weight: 700;
        line-height: 1.15;
        letter-spacing: -0.02em;
        text-align: center;
    }

    .managed-page-featured {
        margin: 0 0 32px;
        border-radius: 18px;
        overflow: hidden;
        background: #f8fafc;
        box-shadow: 0 10px 24px rgba(15, 23, 42, 0.08);
    }

    .managed-page-featured img {
        display: block;
        width: 100%;
        max-height: 420px;
        object-fit: cover;
    }

    .managed-page-body {
        max-width: 100%;
    }

    .managed-page-body,
    .managed-page-body * {
        font-family: inherit !important;
    }

    .managed-page-body p,
    .managed-page-body li,
    .managed-page-body span,
    .managed-page-body div,
    .managed-page-body font {
        color: #334155 !important;
        font-size: 16px !important;
        line-height: 1.75 !important;
    }

    .managed-page-body h1,
    .managed-page-body h2,
    .managed-page-body h3,
    .managed-page-body h4,
    .managed-page-body h5,
    .managed-page-body h6 {
        margin: 32px 0 14px;
        color: #0f172a !important;
        font-weight: 700;
        line-height: 1.2;
        letter-spacing: -0.02em;
    }

    .managed-page-body h1,
    .managed-page-body h2 {
        font-size: 34px !important;
    }

    .managed-page-body h3 {
        font-size: 28px !important;
    }

    .managed-page-body h4 {
        font-size: 24px !important;
    }

    .managed-page-body h5 {
        font-size: 20px !important;
    }

    .managed-page-body h6 {
        font-size: 18px !important;
    }

    .managed-page-body > :first-child {
        margin-top: 0 !important;
    }

    .managed-page-body p {
        margin: 0 0 18px;
    }

    .managed-page-body ul,
    .managed-page-body ol {
        margin: 0 0 22px;
        padding-left: 24px;
    }

    .managed-page-body li {
        margin-bottom: 8px;
    }

    .managed-page-body strong,
    .managed-page-body b {
        color: #0f172a !important;
        font-weight: 700 !important;
    }

    .managed-page-body a {
        color: #dc2626;
        text-decoration: underline;
    }

    @media (max-width: 767.98px) {
        .managed-page-wrap {
            padding: 32px 0 48px;
        }

        .managed-page-card .card-body {
            padding: 28px 20px;
        }

        .managed-page-title {
            font-size: 32px;
            margin-bottom: 20px;
        }

        .managed-page-body h1,
        .managed-page-body h2 {
            font-size: 28px !important;
        }

        .managed-page-body h3 {
            font-size: 24px !important;
        }
    }
</style>
    <!-- Page Title-->
<div class="page-title">
  <div class="container">
    <div class="row">
        <div class="col-lg-12">
            <ul class="breadcrumbs">
                <li><a href="{{route('front.index')}}">{{__('Home')}}</a> </li>
                <li class="separator">&nbsp;</li>
                <li>{{$page->title}}</li>
              </ul>
        </div>
    </div>
  </div>
</div>
<!-- Page Content-->
<div class="managed-page-wrap">
    <div class="container other-page-data">
        <!-- Categories-->
        <div class="row">
            <div class="col-lg-12">
                <div class="card managed-page-card mb-0">
                    <div class="card-body">
                        <div class="managed-page-content">
                            <h1 class="managed-page-title">{{ $page->title }}</h1>
                            @if($page->photo)
                                <div class="managed-page-featured">
                                    <img src="{{ url('/core/public/storage/images/' . $page->photo) }}" alt="{{ $page->title }}">
                                </div>
                            @endif
                            <div class="managed-page-body">
                                {!! html_entity_decode($page->details) !!}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
      </div>
</div>

@endsection
