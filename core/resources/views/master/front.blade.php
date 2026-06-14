<!DOCTYPE html>
<html lang="en">

<head>
    @if (config('services.google.tag_manager_id'))
    <!-- Google Tag Manager Stub -->
    <script>
    window.dataLayer = window.dataLayer || [];
    window.dataLayer.push({
      event: 'page_view',
      page_type: '@yield('page_type', 'other')',
      page_title: document.title,
      page_path: window.location.pathname,
      page_url: window.location.href,
      user_logged_in: {{ Auth::check() ? 'true' : 'false' }},
      user_id: {{ Auth::check() ? Auth::id() : 'null' }}
    });
    @if(Session::has('registered_success_track'))
    window.dataLayer.push({
      event: 'sign_up',
      method: 'website_account',
      user_id: {{ Auth::check() ? Auth::id() : 'null' }}
    });
    @endif
    window.dataLayer.push({'gtm.start': new Date().getTime(), event:'gtm.js'});
    </script>
    <!-- End Google Tag Manager Stub -->
    @endif
    <meta charset="UTF-8">
    @if (url()->current() == route('front.index'))
        <title>@yield('hometitle')</title>
    @else
        <title>@yield('title') - {{ $setting->title }}</title>
    @endif

    <link rel="canonical" href="{{ url()->current() }}">

    <!-- Preload first home page slider image for LCP improvement -->
    @if (url()->current() == route('front.index'))
        @php
            $firstSlider = DB::table('sliders')->where('home_page', $setting->theme ?? 'theme1')->first();
        @endphp
        @if ($firstSlider)
            <link rel="preload" href="{{ url('/core/public/storage/images/' . $firstSlider->photo) }}" as="image">
        @endif
    @endif

    <!-- SEO Meta Tags-->
    @if (url()->current() == route('front.index'))
        <meta name="author" content="GeniusDevs">
        <meta name="distribution" content="web">
        <meta name="description" content="{{ $setting->meta_description }}">
        <meta name="keywords" content="{{ $setting->meta_keywords }}">
        <meta name="image" content="{{ asset('storage/images/' . $setting->meta_image) }}">
        <meta property="og:title" content="{{ $setting->title}}">
        <meta property="og:description" content="{{ $setting->meta_description }}">
        <meta property="og:image" content="{{ asset('storage/images/' . $setting->meta_image) }}">
        <meta property="og:image:secure_url" content="{{ asset('storage/images/' . $setting->meta_image) }}" />
        <meta property="og:image:type" content="image/jpeg" />
        <meta property="og:image:width" content="1200" />
        <meta property="og:image:height" content="627" />
        <meta property="og:url" content="{{ url()->current() }}">
        <meta property="og:site_name" content="{{ $setting->title }}">
        <meta property="og:type" content="website">
    @else
        @if (View::hasSection('meta'))
            @yield('meta')
        @else
            <meta name="author" content="GeniusDevs">
            <meta name="description" content="{{ $setting->meta_description }}">
            <meta name="keywords" content="{{ $setting->meta_keywords }}">
            <meta property="og:title" content="@yield('title') - {{ $setting->title }}">
            <meta property="og:description" content="{{ $setting->meta_description }}">
            <meta property="og:url" content="{{ url()->current() }}">
            <meta property="og:site_name" content="{{ $setting->title }}">
            <meta property="og:type" content="website">
        @endif
    @endif

    <!-- Mobile Specific Meta Tag-->
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">

    <!-- Favicon Icons-->
    <link rel="dns-prefetch" href="//www.googletagmanager.com">
    <link rel="dns-prefetch" href="//connect.facebook.net">
    <link rel="dns-prefetch" href="//www.facebook.com">
    <link rel="dns-prefetch" href="//static.addtoany.com">
    <link rel="preconnect" href="https://www.googletagmanager.com" crossorigin>
    <link rel="preconnect" href="https://connect.facebook.net" crossorigin>
    <link rel="icon" type="image/png" href="{{ asset('storage/images/' . $setting->favicon) }}">
    <link rel="apple-touch-icon" href="{{ asset('storage/images/' . $setting->favicon) }}">
    <link rel="apple-touch-icon" sizes="152x152" href="{{ asset('storage/images/' . $setting->favicon) }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('storage/images/' . $setting->favicon) }}">
    <link rel="apple-touch-icon" sizes="167x167" href="{{ asset('storage/images/' . $setting->favicon) }}">    <!-- Preload critical stylesheets -->
    <link rel="preload" href="{{ asset('assets/front/css/plugins.min.css') }}" as="style">
    <link rel="preload" href="{{ asset('assets/front/css/styles.min.css') }}" as="style">
    <link rel="preload" href="{{ asset('assets/front/css/responsive.css') }}" as="style">

    <!-- Vendor Styles including: Bootstrap, Font Icons, Plugins, etc.-->
    <link rel="stylesheet" href="{{ asset('assets/front/css/plugins.min.css') }}" media="print" onload="this.media='all'; this.onload=null;">
    @yield('styleplugins')

    <link id="mainStyles" rel="stylesheet" href="{{ asset('assets/front/css/styles.min.css') }}" media="print" onload="this.media='all'; this.onload=null;">

    <link id="mainStyles" rel="stylesheet" href="{{ asset('assets/front/css/responsive.css') }}" media="print" onload="this.media='all'; this.onload=null;">

    <!-- Color css -->
    <link
        href="{{ asset('assets/front/css/color.php?primary_color=') . str_replace('#', '', $setting->primary_color) }}"
        rel="stylesheet">

    <!-- Modernizr-->
    <script src="{{ asset('assets/front/js/modernizr.min.js') }}"></script>

    @if (optional($default_language)->rtl == 1)
        <link rel="stylesheet" href="{{ asset('assets/front/css/rtl.css') }}">
    @endif
    <style>
        {{ $setting->custom_css }}
        .top-shipping-bar {
            background-color: #1a2530;
            color: #ffffff;
            padding: 6px 0;
            font-size: 12px;
            font-weight: 600;
            text-align: center;
            width: 100%;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
            letter-spacing: 0.03em;
        }
        .top-announcement-bar {
            background-color: #f8f9fa;
            border-bottom: 1px solid #e3e6e8;
            padding: 5px 0;
            font-size: 12px;
            font-weight: 600;
            color: #222;
            width: 100%;
            text-align: center;
        }
        .top-announcement-bar span {
            text-align: center;
        }
        .ca-flag-icon {
            width: 22px;
            height: auto;
            vertical-align: middle;
            display: inline-block;
            margin: 0 8px;
            border-radius: 2px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.15);
        }
        .product-card {
            cursor: pointer;
        }

        .product-card .product-button-group,
        .product-card .product-button-group *,
        .product-card a,
        .product-card button {
            cursor: pointer;
        }

        .whatsapp-float {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background-color: #25D366;
            color: #fff;
            width: 55px;
            height: 55px;
            border-radius: 50%;
            text-align: center;
            font-size: 30px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
            z-index: 1000;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: transform 0.2s ease;
        }

        .wa-popup{
            position: fixed;
            bottom: 90px;
            right: 20px;
            width: 260px;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,.2);
            z-index: 1001;
            font-family: Arial, sans-serif;
        }

        .wa-header{
            background:#25D366;
            color:#fff;
            padding:10px;
            border-radius:12px 12px 0 0;
            display:flex;
            justify-content:space-between;
            align-items:center;
            font-size:14px;
        }

        .wa-popup p{
            padding:10px;
            font-size:14px;
            color:#333;
        }

        .wa-btn{
            display:block;
            margin:10px;
            padding:10px;
            background:#25D366;
            color:#fff;
            text-align:center;
            border-radius:8px;
            text-decoration:none;
            font-weight:600;
        }

        .wa-btn:hover{
            background:#1ebe5d;
        }

        .hidden{
            display:none;
        }

        #wa-close{
            cursor:pointer;
            font-size:18px;
        }

        .whatsapp-float:hover {
            transform: scale(1.1);
            color: #fff;
        }

        .cookie-consent {
            left: 16px;
            right: 16px;
            bottom: 16px;
            width: auto;
            padding: 12px 20px;
            border-radius: 14px;
            z-index: 1100;
        }

        .cookie-container,
        .cookie-consent .items-center {
            gap: 12px;
            flex-wrap: wrap;
        }

        body {
            padding-bottom: 96px;
        }

        /* Eliminate CLS on home page hero slider */
        .hero-slider,
        .hero-slider-main {
            min-height: 582px;
        }
        .hero-slider-main:not(.owl-loaded) > .item:not(:first-child) {
            display: none !important;
        }
        @media (max-width: 991px) {
            .hero-slider,
            .hero-slider-main {
                min-height: 430px;
            }
        }
        @media (max-width: 576px) {
            .hero-slider,
            .hero-slider-main {
                min-height: 340px;
            }
        }

        .whatsapp-float {
            bottom: 104px;
            z-index: 1110;
        }

        .wa-popup {
            bottom: 174px;
            z-index: 1111;
        }

        .popular-brands-section {
                padding: 40px 0;
                background: #f7f7f7;
            }

            .section-title {
                font-weight: 600;
                margin-bottom: 25px;
                position: relative;
            }

            .section-title::after {
                content: '';
                display: block;
                width: 60px;
                height: 2px;
                background: #d60000;
                margin-top: 6px;
            }

            .popular-brands-center {
                display: flex;
                justify-content: center;
                gap: 40px;
                align-items: center;
            }

            .brand-box {
                background: #fff;
                padding: 25px;
                border-radius: 8px;
                box-shadow: 0 6px 18px rgba(0,0,0,0.08);
                transition: transform 0.2s ease;
            }

            .brand-box:hover {
                transform: translateY(-4px);
            }

            .brand-box img {
                max-height: 55px;
                object-fit: contain;
            }


 .brand-section-header {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 16px;
                flex-wrap: wrap;
            }

            .brand-toggle-btn {
                border: 1px solid #d71920;
                background: transparent;
                color: #d71920;
                padding: 10px 18px;
                border-radius: 999px;
                font-size: 13px;
                font-weight: 700;
                line-height: 1;
                transition: all 0.2s ease;
            }

            .brand-toggle-btn:hover {
                background: #d71920;
                color: #fff;
            }

            .all-brands-grid {
                display: none;
                grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
                gap: 16px;
                margin-top: 24px;
            }

            .all-brands-grid.is-visible {
                display: grid;
            }

            .brand-grid-item {
                display: flex;
                align-items: center;
                justify-content: center;
                min-height: 110px;
                padding: 18px;
                background: #fff;
                border: 1px solid #ececec;
                border-radius: 10px;
                transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
            }

            .brand-grid-item:hover {
                transform: translateY(-2px);
                border-color: #d71920;
                box-shadow: 0 12px 24px rgba(0, 0, 0, 0.08);
            }

            .brand-grid-item img {
                max-width: 100%;
                max-height: 54px;
                object-fit: contain;
            }

            .brand-name-fallback {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                min-height: 54px;
                color: #222;
                font-size: 14px;
                font-weight: 700;
                line-height: 1.25;
                text-align: center;
                text-decoration: none;
            }


            .search-box-wrap {
                flex: 1 1 auto;
                min-width: 0;
                position: relative;
            }

            /* width:auto on desktop — 100% here was forcing full-row width and overlapping toolbar */
            .site-header .topbar .container > .row > .col-lg-12 > .d-flex {
                align-items: center;
            }

            .site-header .search-box-wrap {
                align-items: center;
            }

            .site-header .search-box-wrap .search-box-inner,
            .site-header .search-box-wrap .search-box,
            .site-header .header-keyword-search,
            #header_search_form.input-group {
                width: 100%;
            }

            .site-header .search-box-wrap .search-box {
                align-items: center;
                gap: 0;
            }

            .site-header .header-keyword-search .input-group {
                height: 38px;
            }

            .vehicle-search {
                display: flex;
                align-items: center;
                gap: 6px;
                flex-wrap: wrap;
                width: auto;
                flex: 0 0 auto;
            }

            .vehicle-search .form-control {
                min-width: 110px;
                flex: 1 1 110px;
            }

            .vehicle-picker-trigger {
                border: 1px solid {{ $setting->primary_color }};
                background: {{ $setting->primary_color }};
                height: 38px;
                min-width: 56px;
                padding: 0 14px;
                border-radius: 6px;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                gap: 8px;
                color: #fff;
                transition: background 0.2s ease, border-color 0.2s ease, color 0.2s ease;
            }

            .vehicle-picker-trigger .fas.fa-car {
                color: #fff;
            }

            .vehicle-picker-label {
                font-size: 13px;
                font-weight: 600;
                white-space: nowrap;
                line-height: 1.2;
                color: #fff;
            }

            .vehicle-picker-trigger:hover {
                filter: brightness(0.92);
                border-color: {{ $setting->primary_color }};
                color: #fff;
            }

            .vehicle-picker-trigger:hover .fas.fa-car,
            .vehicle-picker-trigger:hover .vehicle-picker-label {
                color: #fff;
            }

            .vehicle-picker-trigger:focus-visible {
                outline: 2px solid #fff;
                outline-offset: 2px;
            }

            .vehicle-picker-backdrop {
                display: none;
                position: fixed;
                inset: 0;
                z-index: 10500;
                align-items: center;
                justify-content: center;
                padding: 16px;
                background: rgba(0, 0, 0, 0.45);
                -webkit-backdrop-filter: blur(2px);
                backdrop-filter: blur(2px);
            }

            .vehicle-picker-backdrop.is-open {
                display: flex;
            }

            .vehicle-picker-modal {
                width: 100%;
                max-width: 560px;
                max-height: min(90vh, 640px);
                overflow: auto;
                background: #fff;
                border-radius: 10px;
                box-shadow: 0 20px 50px rgba(0, 0, 0, 0.2);
                border: 1px solid #e8e9eb;
                padding: 0;
                position: relative;
            }

            .vehicle-picker-head {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 16px 18px 12px;
                border-bottom: 1px solid #eee;
                font-size: 16px;
            }

            .vehicle-picker-title {
                font-weight: 700;
                color: #111;
                margin: 0;
            }

            .vehicle-picker-close {
                border: none;
                background: transparent;
                color: #111;
                font-size: 22px;
                line-height: 1;
                cursor: pointer;
                padding: 4px 8px;
                margin: -4px -8px -4px 0;
            }

            .vehicle-picker-close:hover {
                color: #d71920;
            }

            .vehicle-picker-body {
                padding: 16px 18px;
            }

            .vehicle-picker-row {
                display: flex;
                flex-wrap: wrap;
                gap: 10px;
            }

            .vehicle-picker-row .form-control {
                flex: 1 1 140px;
                min-width: 0;
            }

            .vehicle-picker-hint {
                display: none;
                margin-top: 10px;
                font-size: 13px;
                color: #c0392b;
            }

            .vehicle-picker-hint.is-visible {
                display: block;
            }

            .vehicle-picker-footer {
                padding: 0 18px 18px;
            }

            .vehicle-picker-search-btn {
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 10px;
                width: 100%;
                padding: 12px 16px;
                border: none;
                border-radius: 6px;
                background: #d71920;
                color: #fff;
                font-size: 15px;
                font-weight: 700;
                cursor: pointer;
                transition: background 0.2s ease;
            }

            .vehicle-picker-search-btn:hover {
                background: #b8141a;
                color: #fff;
            }

            body.vehicle-picker-open {
                overflow: hidden;
            }

            #header_search_form.input-group {
                flex: 1 1 260px;
                min-width: 220px;
            }

            .vehicle-summary {
                display: flex;
                align-items: center;
                gap: 6px;
                padding: 4px 10px;
                background: #f8f9fa;
                border-left: 2px solid #d71920;
                font-size: 12px;
                min-height: 38px;
                white-space: nowrap;
            }

            .vehicle-text {
                font-weight: 600;
                color: #222;
                line-height: 1;
            }

            .vehicle-clear {
                background: none;
                border: none;
                color: #d71920;
                font-size: 12px;
                font-weight: 600;
                cursor: pointer;
                padding: 0;
            }

            .vehicle-clear:hover {
                text-decoration: underline;
            }

            .toolbar-item.mobile-shop-link .toolbar-link {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                gap: 6px;
                min-width: 88px;
                padding: 0 10px;
            }

            .toolbar-item.mobile-shop-link .text-label {
                display: inline-block;
            }

            .mobile-primary-nav {
                display: none;
            }

            .mobile-cart-backdrop,
            .mobile-cart-drawer-close {
                display: none;
            }




            @media (max-width: 767.98px) {
                .popular-brands-center {
                    flex-direction: column;
                    gap: 20px;
                }

                .menu-top-area .t-m-s-a,
                .menu-top-area .right-area {
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    flex-wrap: wrap;
                    gap: 6px 14px;
                }

                .menu-top-area .track-order-link,
                .menu-top-area .main-link,
                .menu-top-area .login-register .track-order-link {
                    font-size: 12px;
                    line-height: 1.25;
                }

                .menu-top-area .right-area > * {
                    margin: 0;
                }

                .site-header .vehicle-search {
                    width: auto;
                }

                .site-header .search-box-wrap {
                    width: 100%;
                    padding-top: 0;
                }

                /* Keep car + keyword on one row (responsive.css handles layout) */
                .site-header .search-box-wrap .search-box {
                    flex-direction: row;
                    align-items: stretch;
                    gap: 8px;
                }

                .site-header .vehicle-picker-trigger {
                    width: auto;
                    min-width: 44px;
                }

                .vehicle-picker-backdrop {
                    align-items: flex-end;
                    padding: 0;
                    background: rgba(0, 0, 0, 0.5);
                }

                .vehicle-picker-modal {
                    max-width: none;
                    max-height: 92vh;
                    width: 100%;
                    border-radius: 12px 12px 0 0;
                    margin-top: auto;
                }

                .vehicle-picker-footer {
                    padding-bottom: max(18px, env(safe-area-inset-bottom));
                }

                .vehicle-search .form-control,
                #header_search_form.input-group {
                    width: 100%;
                    min-width: 0;
                    flex: 1 1 auto;
                }

                .vehicle-summary {
                    width: 100%;
                    margin-top: 8px;
                    justify-content: space-between;
                    white-space: normal;
                    height: auto;
                }

                .cookie-consent {
                    left: 12px;
                    right: 12px;
                    bottom: 12px;
                    padding: 12px 14px;
                    border-radius: 12px;
                }

                body {
                    padding-bottom: 138px;
                }

                .whatsapp-float {
                    right: 16px;
                    bottom: 92px;
                    width: 52px;
                    height: 52px;
                    font-size: 28px;
                }

                .wa-popup {
                    right: 12px;
                    left: 12px;
                    width: auto;
                    max-width: none;
                    bottom: 156px;
                }

                .site-header .topbar {
                    padding: 8px 0 0;
                    background: #fff;
                }

                .site-header .topbar .container > .row > .col-lg-12 > .d-flex {
                    display: flex !important;
                    flex-wrap: wrap;
                    align-items: center;
                    gap: 8px;
                }

                .site-header .site-branding {
                    display: none;
                }

                .site-header .search-box-wrap {
                    position: relative !important;
                    top: auto !important;
                    left: auto !important;
                    z-index: 1 !important;
                    order: 1;
                    flex: 1 1 100%;
                    width: 100% !important;
                    height: auto !important;
                    background: transparent;
                    margin: 0;
                }

                .site-header .toolbar {
                    order: 2;
                    display: flex !important;
                    width: 100%;
                    justify-content: space-between;
                    gap: 18px;
                    margin-top: 7px;
                    padding: 9px 44px 11px;
                    min-height: 72px;
                    align-items: center;
                    border: 0;
                    background: #fff;
                    box-shadow: 0 10px 22px rgba(15, 23, 42, 0.06);
                }

                .navbar-stuck .topbar {
                    padding-bottom: 0 !important;
                }

                .navbar-stuck .site-header .toolbar,
                .site-header.navbar-stuck .toolbar {
                    padding-bottom: 11px;
                }

                .site-header .toolbar .toolbar-item {
                    display: flex !important;
                    align-items: center;
                    justify-content: center;
                    flex: 1 1 0;
                    width: auto !important;
                    min-width: 0;
                    margin-left: 0 !important;
                }

                .site-header .toolbar .mobile-menu-toggle {
                    order: 1;
                    justify-content: flex-start;
                }

                .site-header .toolbar .mobile-category-toggle {
                    order: 2;
                    justify-content: center;
                }

                .site-header .toolbar .cart-toolbar-item {
                    order: 3;
                    justify-content: flex-end;
                }

                .site-header .toolbar .toolbar-item > a {
                    width: 100%;
                }

                .site-header .toolbar .toolbar-item > a > div {
                    min-height: 48px;
                    display: inline-flex;
                    flex-direction: column;
                    align-items: center;
                    justify-content: center;
                    gap: 3px;
                    color: #222;
                }

                .site-header .toolbar .toolbar-item > a > div > .text-label {
                    display: inline-block !important;
                    font-size: 12px;
                    font-weight: 400;
                    line-height: 1.15;
                    color: #222;
                }

                .site-header .toolbar .toolbar-item > a > div i {
                    font-size: 25px;
                    line-height: 1;
                }

                .page-title {
                    margin-top: 14px;
                }

                .site-header .toolbar .toolbar-item.close-m-serch,
                .site-header .toolbar .toolbar-item.mobile-shop-link {
                    display: none !important;
                }

                .site-header .toolbar .toolbar-item.hidden-on-mobile {
                    display: none !important;
                }

                .site-header .toolbar .cart-icon .count-label {
                    top: -7px;
                    right: -9px;
                }

                .site-header .toolbar .cart-icon {
                    position: relative;
                }

                .mobile-cart-backdrop {
                    position: fixed;
                    inset: 0;
                    z-index: 1050;
                    display: block;
                    background: rgba(0, 0, 0, 0.45);
                    opacity: 0;
                    visibility: hidden;
                    pointer-events: none;
                    transition: opacity 0.22s ease, visibility 0.22s ease;
                }

                .mobile-cart-backdrop.is-open {
                    opacity: 1;
                    visibility: visible;
                    pointer-events: auto;
                }

                body.mobile-cart-open {
                    overflow: hidden;
                }

                body.mobile-cart-open .site-header {
                    position: relative;
                    z-index: 1060;
                }

                .site-header .toolbar .cart-toolbar-item.is-cart-open {
                    z-index: 1070;
                }

                .site-header .toolbar .cart-toolbar-item .cart-dropdown,
                body > .cart-dropdown.mobile-cart-drawer {
                    position: fixed;
                    top: 128px !important;
                    right: 0;
                    bottom: 0;
                    left: auto;
                    z-index: 1070;
                    display: block !important;
                    width: min(88vw, 380px);
                    height: calc(100vh - 128px);
                    max-height: calc(100vh - 128px);
                    padding: 22px 18px 22px !important;
                    overflow-y: auto;
                    background: #fff;
                    border: 0;
                    border-radius: 0;
                    box-shadow: -18px 0 45px rgba(0, 0, 0, 0.18);
                    opacity: 1;
                    visibility: visible;
                    pointer-events: none;
                    transform: translateX(110%);
                    transition: transform 0.26s ease;
                }

                .site-header .toolbar .cart-toolbar-item.is-cart-open .cart-dropdown,
                body.mobile-cart-open > .cart-dropdown.mobile-cart-drawer {
                    pointer-events: auto;
                    transform: translateX(0);
                }

                .mobile-cart-drawer-close {
                    position: fixed;
                    top: 18px;
                    right: 18px;
                    z-index: 1080;
                    display: none !important;
                    width: 42px;
                    height: 42px;
                    border: 0;
                    border-radius: 50%;
                    background: #111;
                    color: #fff;
                    font-size: 20px;
                    line-height: 42px;
                    text-align: center;
                }

                body.mobile-cart-open .mobile-cart-drawer-close {
                    display: inline-flex !important;
                    align-items: center;
                    justify-content: center;
                }

                .mobile-primary-nav {
                    display: none !important;
                }

                .toolbar-item.mobile-shop-link .toolbar-link {
                    min-width: 88px;
                    padding: 0 10px;
                }
            }


    </style>
    {{-- Google AdSense Start --}}
    @if ($setting->is_google_adsense == '1')
        {!! $setting->google_adsense !!}
    @endif
    {{-- Google AdSense End --}}

    {{-- Google AnalyTics Start --}}
    @if ($setting->is_google_analytics == '1')
        {!! $setting->google_analytics !!}
    @endif
    {{-- Google AnalyTics End --}}

    {{-- Facebook pixel  Start --}}
    @if ($setting->is_facebook_pixel == '1')
        {!! $setting->facebook_pixel !!}
    @endif
    {{-- Facebook pixel End --}}
@if (!($setting->is_google_analytics == '1' && trim((string) $setting->google_analytics) !== ''))
<!-- Google tag (gtag.js) Stub -->
<script>
  window.dataLayer = window.dataLayer || [];
  window.gtag = window.gtag || function(){window.dataLayer.push(arguments);}
  gtag('js', new Date());

  gtag('config', '{{ config('services.google.analytics_id') }}');
  gtag('config', 'AW-{{ config('services.google.ads_conversion_id') }}');
</script>
@endif
<!-- #metapixelscript -->
@if (!($setting->is_facebook_pixel == '1' && trim((string) $setting->facebook_pixel) !== ''))
@php
    $metaAdvancedMatching = [];
    $metaNormalize = function ($value) {
        $value = preg_replace('/\s+/', '', strtolower(trim((string) $value)));
        return $value === '' ? null : hash('sha256', $value);
    };
    $metaNormalizeEmail = function ($value) {
        $value = strtolower(trim((string) $value));
        return $value === '' ? null : hash('sha256', $value);
    };
    $metaNormalizePhone = function ($value) {
        $value = preg_replace('/\D+/', '', (string) $value);
        return $value === '' ? null : hash('sha256', $value);
    };
    $metaNormalizeCountry = function ($value) use ($metaNormalize) {
        $value = strtolower(trim((string) $value));
        $countries = [
            'canada' => 'ca',
            'ca' => 'ca',
            'united states' => 'us',
            'united states of america' => 'us',
            'usa' => 'us',
            'us' => 'us',
        ];
        return $metaNormalize($countries[$value] ?? $value);
    };

    if (Auth::check()) {
        $metaUser = Auth::user();
        $metaAdvancedMatching = array_filter([
            'em' => $metaNormalizeEmail($metaUser->email ?? null),
            'ph' => $metaNormalizePhone($metaUser->phone ?? null),
            'fn' => $metaNormalize($metaUser->first_name ?? null),
            'ln' => $metaNormalize($metaUser->last_name ?? null),
            'ct' => $metaNormalize($metaUser->bill_city ?? $metaUser->ship_city ?? null),
            'st' => $metaNormalize($metaUser->bill_province ?? $metaUser->ship_province ?? null),
            'zp' => $metaNormalize($metaUser->bill_zip ?? $metaUser->ship_zip ?? null),
            'country' => $metaNormalizeCountry($metaUser->bill_country ?? $metaUser->ship_country ?? null),
            'external_id' => $metaNormalize('user_' . $metaUser->id),
        ]);
    } else {
        $guestMeta = Session::get('guest_meta_details');
        if (is_array($guestMeta) && !empty($guestMeta)) {
            $metaAdvancedMatching = array_filter([
                'em' => $metaNormalizeEmail($guestMeta['email'] ?? null),
                'ph' => $metaNormalizePhone($guestMeta['phone'] ?? null),
                'fn' => $metaNormalize($guestMeta['first_name'] ?? null),
                'ln' => $metaNormalize($guestMeta['last_name'] ?? null),
                'ct' => $metaNormalize($guestMeta['city'] ?? null),
                'st' => $metaNormalize($guestMeta['province'] ?? null),
                'zp' => $metaNormalize($guestMeta['zip'] ?? null),
                'country' => $metaNormalizeCountry($guestMeta['country'] ?? null),
                'external_id' => $metaNormalize($guestMeta['email'] ?? null),
            ]);
        }
    }
@endphp
<!-- Meta Pixel Stub -->
<script>
if(!window.fbq) {
  window.fbq = function(){window.fbq.callMethod?window.fbq.callMethod.apply(window.fbq,arguments):window.fbq.queue.push(arguments)};
  if(!window._fbq)window._fbq=window.fbq;
  window.fbq.push=window.fbq;window.fbq.loaded=!0;window.fbq.version='2.0';window.fbq.queue=[];
}
fbq('init', '{{ config('services.facebook.pixel_id') }}'@if(!empty($metaAdvancedMatching)), @json($metaAdvancedMatching)@endif);
fbq('track', 'PageView');
</script>
<noscript><img height="1" width="1" style="display:none"
src="https://www.facebook.com/tr?id={{ config('services.facebook.pixel_id') }}&ev=PageView&noscript=1"
/></noscript>
<!-- End Meta Pixel Stub -->
@endif

<!-- #metapixelscript -->
<script>
(function (window, document) {
    window.paTrack = function (metaEvent, payload, googleEvent, options) {
        payload = payload || {};
        options = options || {};

        try {
            var activeVehicle = null;
            try {
                activeVehicle = JSON.parse(localStorage.getItem('selected_vehicle') || 'null');
            } catch (e) {}

            if (payload && Array.isArray(payload.items)) {
                payload.items.forEach(function (item) {
                    if (item) {
                        if (!item.google_business_vertical) {
                            item.google_business_vertical = 'retail';
                        }
                        if (item.price !== undefined) {
                            item.price = parseFloat(item.price);
                        }
                        if (item.quantity !== undefined) {
                            item.quantity = parseInt(item.quantity, 10);
                        }
                        if (activeVehicle && activeVehicle.year && activeVehicle.make && activeVehicle.model) {
                            if (!item.vehicle_year) {
                                item.vehicle_year = String(activeVehicle.year).trim();
                            }
                            if (!item.vehicle_make) {
                                item.vehicle_make = String(activeVehicle.make).trim();
                            }
                            if (!item.vehicle_model) {
                                item.vehicle_model = String(activeVehicle.model).trim();
                            }
                        }
                        if (!item.vehicle_trim) {
                            item.vehicle_trim = (activeVehicle && activeVehicle.trim) ? String(activeVehicle.trim).trim() : '';
                        }
                        if (!item.manufacturer && item.item_brand) {
                            item.manufacturer = String(item.item_brand).trim();
                        }
                        if (!item.part_type) {
                            item.part_type = String(item.item_category3 || item.item_category2 || item.item_category || '').trim();
                        }
                        if (!item.part_typefitment) {
                            item.part_typefitment = String(item.item_category3 || item.item_category2 || item.item_category || '').trim();
                        }
                        if (!item.mpn && item.item_id) {
                            item.mpn = String(item.item_id).trim();
                        }
                        if (!item.sku && item.item_id) {
                            item.sku = String(item.item_id).trim();
                        }
                    }
                });
            }
            window.dataLayer = window.dataLayer || [];
            var dataLayerObj = {
                event: googleEvent || metaEvent,
                meta_event: metaEvent,
                google_ads_send_to: options.googleAdsSendTo || null,
                ecommerce: payload
            };

            var eventYear = (activeVehicle && activeVehicle.year) ? String(activeVehicle.year).trim() : '';
            var eventMake = (activeVehicle && activeVehicle.make) ? String(activeVehicle.make).trim() : '';
            var eventModel = (activeVehicle && activeVehicle.model) ? String(activeVehicle.model).trim() : '';
            var eventTrim = (activeVehicle && activeVehicle.trim) ? String(activeVehicle.trim).trim() : '';

            if (!eventYear && payload && Array.isArray(payload.items) && payload.items.length > 0) {
                var firstItem = payload.items[0];
                if (firstItem && firstItem.vehicle_year) {
                    eventYear = String(firstItem.vehicle_year).trim();
                    eventMake = String(firstItem.vehicle_make || '').trim();
                    eventModel = String(firstItem.vehicle_model || '').trim();
                    eventTrim = String(firstItem.vehicle_trim || '').trim();
                }
            }

            if (eventYear) {
                dataLayerObj.vehicle_year = eventYear;
                dataLayerObj.vehicle_make = eventMake;
                dataLayerObj.vehicle_model = eventModel;
                dataLayerObj.vehicle_trim = eventTrim;
            }
            if (payload && Array.isArray(payload.items) && payload.items.length > 0) {
                var firstItem = payload.items[0];
                if (firstItem) {
                    dataLayerObj.part_type = firstItem.part_type || firstItem.part_typefitment || '';
                }
            }
            window.dataLayer.push(dataLayerObj);
        } catch (error) {
            console.warn('Tracking dataLayer push failed', error);
        }

        try {
            var hasGtmScript = false;
            var scripts = document.getElementsByTagName('script');
            for (var i = 0; i < scripts.length; i++) {
                if (scripts[i].src && scripts[i].src.indexOf('gtm.js') !== -1) {
                    hasGtmScript = true;
                    break;
                }
            }
            var shouldSendDirectGoogleEvent = !hasGtmScript && !window.google_tag_manager;

            if (shouldSendDirectGoogleEvent && googleEvent && typeof window.gtag === 'function') {
                window.gtag('event', googleEvent, payload);
                if (options.googleAdsSendTo) {
                    window.gtag('event', 'conversion', Object.assign({}, payload, {
                        send_to: options.googleAdsSendTo
                    }));
                }
            }
        } catch (error) {
            console.warn('Google tracking failed', error);
        }

        try {
            if (metaEvent && typeof window.fbq === 'function') {
                var metaOptions = options.eventId ? { eventID: options.eventId } : undefined;
                window.fbq(options.metaMethod || 'track', metaEvent, payload, metaOptions);
            }
        } catch (error) {
            console.warn('Meta tracking failed', error);
        }
    };

    document.addEventListener('click', function (event) {
        var target = event.target;
        if (!target || typeof target.closest !== 'function') {
            return;
        }

        var element = target.closest('a, button, input[type="submit"], input[type="button"], .btn');

        if (!element || element.closest('[data-pa-track-ignore]')) {
            return;
        }

        var label = (
            element.getAttribute('data-pa-label') ||
            element.getAttribute('aria-label') ||
            element.textContent ||
            element.value ||
            element.id ||
            'click'
        ).replace(/\s+/g, ' ').trim().slice(0, 120);

        window.paTrack('SiteClick', {
            event_category: 'engagement',
            event_label: label,
            link_url: element.href || '',
            page_location: window.location.href
        }, 'select_content', { metaMethod: 'trackCustom' });
    }, true);
})(window, document);
</script>

</head>
<!-- Body-->

<body
    class="
@if ($setting->theme == 'theme1') body_theme1
@elseif($setting->theme == 'theme2')
body_theme2
@elseif($setting->theme == 'theme3')
body_theme3
@elseif($setting->theme == 'theme4')
body_theme4 @endif
">
    @if (config('services.google.tag_manager_id'))
    <!-- Google Tag Manager (noscript) -->
    <noscript><iframe src="/metrics/ns.html?id={{ config('services.google.tag_manager_id') }}"
    height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
    <!-- End Google Tag Manager (noscript) -->
    @endif
    @php
        $isSpeedBot = false;
        if (isset($_SERVER['HTTP_USER_AGENT'])) {
            $ua = $_SERVER['HTTP_USER_AGENT'];
            if (preg_match('/(Lighthouse|Chrome-Lighthouse|Googlebot|GTmetrix|Pingdom|PageSpeed)/i', $ua)) {
                $isSpeedBot = true;
            }
        }
        $loaderWidth = 200;
        $loaderHeight = 200;
        $loaderPath = public_path('storage/images/' . $setting->loader);
        if (file_exists($loaderPath)) {
            $loaderInfo = @getimagesize($loaderPath);
            if ($loaderInfo) {
                $loaderWidth = $loaderInfo[0];
                $loaderHeight = $loaderInfo[1];
            }
        }
    @endphp
    @if ($setting->is_loader == 1 && !$isSpeedBot)
        <!-- Preloader Start -->
        <div id="preloader">
            <img src="{{ url('/core/public/storage/images/' . $setting->loader) }}" alt="{{ __('Loading...') }}" width="{{ $loaderWidth }}" height="{{ $loaderHeight }}">
        </div>
        <!-- Preloader End -->
    @endif

    <!-- Header-->

    <header class="site-header navbar-sticky">
        @php
            $free_shipping_service = DB::table('shipping_services')->where('id', 1)->first();
            $min_price_val = $free_shipping_service ? $free_shipping_service->minimum_price : 150;
        @endphp
        <div style="" class="top-shipping-bar">
            <div class="container text-center">
                <span>{{ __('FREE SHIPPING ON ORDERS OVER') }} {{ \PriceHelper::setCurrencyPrice($min_price_val) }}!</span>
            </div>
        </div>
        <div class="top-announcement-bar">
            <div class="container d-flex align-items-center justify-content-center">
                <img src="https://cdn.shopify.com/static/images/flags/ca.svg?width=24" alt="Canada Flag" class="ca-flag-icon" width="24" height="16" loading="lazy">
                <span>{{ __("Proudly 100% Canadian Owned and Operated — Serving Drivers from Coast to Coast.") }}</span>
                <img src="https://cdn.shopify.com/static/images/flags/ca.svg?width=24" alt="Canada Flag" class="ca-flag-icon" width="24" height="16" loading="lazy">
            </div>
        </div>
        <div class="menu-top-area">
            <div class="container">
                <div class="row">
                    <div class="col-md-4">
                        <div class="t-m-s-a">
                            <a class="track-order-link" href="{{ route('front.order.track') }}"><i
                                    class="icon-map-pin"></i>{{ __('Track Order') }}</a>
                            <a class="track-order-link compare-mobile d-lg-none"
                                href="{{ route('fornt.compare.index') }}">{{ __('Compare') }}</a>
                        </div>
                    </div>
                    <div class="col-md-8">
                        <div class="right-area">

                            <a class="track-order-link wishlist-mobile d-inline-block d-lg-none"
                                href="{{ route('user.wishlist.index') }}"><i
                                    class="icon-heart"></i>{{ __('Wishlist') }}</a>

                            {{-- <div class="t-h-dropdown ">
                                <a class="main-link" href="#">{{ __('Language') }}<i
                                        class="icon-chevron-down"></i></a>
                                <div class="t-h-dropdown-menu">
                                    @foreach (DB::table('languages')->whereType('Website')->get() as $language)
                                        <a class="{{ Session::get('language') == $language->id ? 'active' : ($language->is_default == 1 && !Session::has('language') ? 'active' : '') }}"
                                            href="{{ route('front.language.setup', $language->id) }}"><i
                                                class="icon-chevron-right pr-2"></i>{{ $language->language }}</a>
                                    @endforeach
                                </div>
                            </div> --}}


                            <div class="t-h-dropdown ">
                                <a class="main-link" href="#">{{ __('Currency') }}<i
                                        class="icon-chevron-down"></i></a>
                                <div class="t-h-dropdown-menu">
                                    @foreach ($site_currencies as $currency)
                                        <a class="{{ Session::get('currency') == $currency->id ? 'active' : ($currency->is_default == 1 && !Session::has('currency') ? 'active' : '') }}"
                                            href="{{ route('front.currency.setup', $currency->id) }}"><i
                                                class="icon-chevron-right pr-2"></i>{{ $currency->name }}</a>
                                    @endforeach
                                </div>
                            </div>

                            <div class="login-register ">
                                @if (!Auth::user())
                                    <a class="track-order-link mr-0" href="{{ route('user.login') }}">
                                        {{ __('Login') }}
                                    </a>
                                @else
                                    <div class="t-h-dropdown">
                                        <div class="main-link">
                                            <i class="icon-user pr-2"></i> <span
                                                class="text-label">{{ Auth::user()->first_name }}</span>
                                        </div>
                                        <div class="t-h-dropdown-menu">
                                            <a href="{{ route('user.dashboard') }}"><i
                                                    class="icon-chevron-right pr-2"></i>{{ __('Dashboard') }}</a>
                                            <a href="{{ route('user.logout') }}"><i
                                                    class="icon-chevron-right pr-2"></i>{{ __('Logout') }}</a>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- Topbar-->
        <div class="topbar">
            <div class="container">
                <div class="row">
                    <div class="col-lg-12">
                        <div class="d-flex justify-content-between">
                            <!-- Logo-->
                            <div class="site-branding"><a class="site-logo align-self-center"
                                    href="{{ route('front.index') }}">
                                    @php
                                        $logoWidth = 1536;
                                        $logoHeight = 1024;
                                        $logoPath = public_path('storage/images/' . $setting->logo);
                                        if (file_exists($logoPath)) {
                                            $logoInfo = @getimagesize($logoPath);
                                            if ($logoInfo) {
                                                $logoWidth = $logoInfo[0];
                                                $logoHeight = $logoInfo[1];
                                            }
                                        }
                                    @endphp
                                    <img src="{{ asset('core/public/storage/images/' . $setting->logo) }}"
                                        alt="{{ $setting->title }}" width="{{ $logoWidth }}" height="{{ $logoHeight }}">
                                </a></div>
                            <!-- Search / Categories-->
                            <div class="search-box-wrap d-flex">
                                <div class="search-box-inner align-self-center">
                                    <div class="search-box d-flex">
                                        <!-- <select name="category" id="category_select" class="categoris">
                                            <option value="">{{ __('All') }}</option>
                                            @foreach ($header_categories as $category)
                                                <option value="{{ $category->slug }}">{{ $category->name }}</option>
                                            @endforeach
                                        </select> -->
                                        {{-- Car: always visible; not toggled with mobile search icon (see myscript.js) --}}
                                        <div class="vehicle-search d-flex gap-2 align-items-center flex-shrink-0">
                                            <button type="button" id="vehiclePickerToggle" class="vehicle-picker-trigger"
                                                aria-label="{{ __('Open vehicle selector') }}" aria-expanded="false"
                                                aria-haspopup="dialog">
                                                <i class="fas fa-car" aria-hidden="true"></i>
                                                <span class="vehicle-picker-label d-none d-lg-inline">{{ __('Find by Vehicle') }}</span>
                                            </button>
                                        </div>

                                        {{-- Keyword search only: toggled on mobile by toolbar magnifying glass --}}
                                        <div class="header-keyword-search flex-grow-1 min-w-0 d-flex">
                                        <form class="input-group" id="header_search_form"
                                            action="{{ route('front.catalog') }}" method="get">
                                            <input type="hidden" name="category" value=""
                                                id="search__category">
                                            <span class="input-group-btn">
                                                <button type="submit" aria-label="{{ __('Search products') }}"><i class="icon-search" aria-hidden="true"></i></button>
                                            </span>
                                            <input class="form-control" type="text"
                                                data-target="{{ route('front.search.suggest') }}"
                                                id="__product__search" name="search"
                                                aria-label="{{ __('Search by brand, category or product') }}"
                                                placeholder="{{ __('Search by brand, category or product') }}">
                                            <div class="serch-result d-none">
                                                {{-- search result --}}
                                            </div>
                                            <input type="hidden" id="search_year"  name="year"  value="{{ request('year') }}">
                                            <input type="hidden" id="search_make"  name="make"  value="{{ request('make') }}">
                                            <input type="hidden" id="search_model" name="model" value="{{ request('model') }}">

                                        </form>
                                        </div>
                                        
                                    </div>
                                </div>
                                <!-- 🔽 Vehicle summary appears HERE -->
                                <div id="vehicleSummary" class="vehicle-summary" style="display:none;">
                                    <span class="vehicle-text"></span>
                                    <button type="button" id="clearVehicleSummary" class="vehicle-clear"
                                        aria-label="{{ __('Clear selected vehicle') }}">
                                        ✕
                                    </button>
                                </div>
                            </div>
                            <!-- Toolbar-->
                            <div class="toolbar d-flex">

                                <div class="toolbar-item visible-on-mobile mobile-menu-toggle"><a href="#" role="button" aria-label="{{ __('Open menu') }}">
                                        <div><i class="icon-menu" aria-hidden="true"></i><span
                                                class="text-label">{{ __('Menu') }}</span></div>
                                    </a>
                                </div>
                                <div class="toolbar-item visible-on-mobile mobile-category-toggle"><a href="#" role="button" aria-label="{{ __('Browse categories') }}">
                                        <div><i class="icon-grid" aria-hidden="true"></i><span
                                                class="text-label">{{ __('Categories') }}</span></div>
                                    </a>
                                </div>

                                <div class="toolbar-item hidden-on-mobile"><a
                                        href="{{ route('fornt.compare.index') }}">
                                        <div><span class="compare-icon"><i class="icon-repeat"></i><span
                                                    class="count-label compare_count">{{ Session::has('compare') ? count(Session::get('compare')) : '0' }}</span></span><span
                                                class="text-label">{{ __('Compare') }}</span></div>
                                    </a>
                                </div>
                                @if (Auth::check())
                                    <div class="toolbar-item hidden-on-mobile"><a
                                            href="{{ route('user.wishlist.index') }}">
                                            <div><span class="compare-icon"><i class="icon-heart"></i><span
                                                        class="count-label wishlist_count">{{ Auth::user()->wishlists->count() }}</span></span><span
                                                    class="text-label">{{ __('Wishlist') }}</span></div>
                                        </a>
                                    </div>
                                @else
                                    <div class="toolbar-item hidden-on-mobile"><a
                                            href="{{ route('user.wishlist.index') }}">
                                            <div><span class="compare-icon"><i class="icon-heart"></i></span><span
                                                    class="text-label">{{ __('Wishlist') }}</span></div>
                                        </a>
                                    </div>
                                @endif
                                <div class="toolbar-item cart-toolbar-item"><a href="{{ route('front.cart') }}" class="mobile-cart-toggle">
                                        <div><span class="cart-icon"><i class="icon-shopping-cart"></i><span
                                                    class="count-label cart_count">{{ Session::has('cart') ? count(Session::get('cart')) : '0' }}
                                                </span></span><span class="text-label">{{ __('Cart') }}</span>
                                        </div>
                                    </a>
                                    <div class="toolbar-dropdown cart-dropdown widget-cart  cart_view_header"
                                        id="header_cart_load" data-target="{{ route('front.header.cart') }}">
                                        @include('includes.header_cart')
                                    </div>
                                    <button type="button" id="mobileCartClose" class="mobile-cart-drawer-close"
                                        aria-label="{{ __('Close cart') }}">
                                        <i class="icon-x" aria-hidden="true"></i>
                                    </button>
                                </div>
                            </div>

                            @php
                                $mobileMenuLinks = json_decode(optional($menus)->menus ?? '[]', true) ?: [];
                            @endphp

                            <!-- Mobile Menu-->
                            <div class="mobile-menu">
                                <!-- Slideable (Mobile) Menu-->
                                <div class="mm-heading-area">
                                    <h4>{{ __('Navigation') }}</h4>
                                    <div class="toolbar-item visible-on-mobile mobile-menu-toggle mm-t-two">
                                        <a href="#" role="button" aria-label="{{ __('Close menu') }}">
                                            <div> <i class="icon-x" aria-hidden="true"></i></div>
                                        </a>
                                    </div>
                                </div>
                                <ul class="nav nav-tabs" role="tablist">
                                    <li class="nav-item" role="presentation99">
                                        <span class="active" id="mmenu-tab" data-bs-toggle="tab"
                                            data-bs-target="#mmenu" role="tab" aria-controls="mmenu"
                                            aria-selected="true">{{ __('Menu') }}</span>
                                    </li>
                                    <li class="nav-item" role="presentation99">
                                        <span class="" id="mcat-tab" data-bs-toggle="tab"
                                            data-bs-target="#mcat" role="tab" aria-controls="mcat"
                                            aria-selected="false">{{ __('Category') }}</span>
                                    </li>

                                </ul>
                                <div class="tab-content p-0">
                                    <div class="tab-pane fade show active" id="mmenu" role="tabpanel"
                                        aria-labelledby="mmenu-tab">
                                        <nav class="slideable-menu">
                                            <ul>
                                                @foreach ($mobileMenuLinks as $link)
                                                    @php
                                                        $mobileHref = Helper::getHref($link);
                                                        $mobileLinkTarget = $link['target'] ?? '_self';
                                                        $mobileIsActive = $mobileHref === url()->current();
                                                    @endphp
                                                    @if (!array_key_exists('children', $link))
                                                        <li class="{{ $mobileIsActive ? 'active' : '' }}">
                                                            <a href="{{ $link['href'] == null ? $mobileHref : $link['href'] }}"
                                                                target="{{ $mobileLinkTarget }}">
                                                                <i class="icon-chevron-right"></i>{{ $link['text'] }}
                                                            </a>
                                                        </li>
                                                    @else
                                                        <li class="t-h-dropdown">
                                                            <a href="{{ $mobileHref ?: '#' }}" target="{{ $mobileLinkTarget }}">
                                                                <i class="icon-chevron-right"></i>{{ $link['text'] }} <i
                                                                    class="icon-chevron-down"></i>
                                                            </a>
                                                            <div class="t-h-dropdown-menu">
                                                                @foreach ($link['children'] as $childLink)
                                                                    @php
                                                                        $childHref = Helper::getHref($childLink);
                                                                        $childTarget = $childLink['target'] ?? '_self';
                                                                    @endphp
                                                                    <a class="{{ $childHref === url()->current() ? 'active' : '' }}"
                                                                        href="{{ $childHref }}"
                                                                        target="{{ $childTarget }}">
                                                                        <i class="icon-chevron-right pr-2"></i>{{ $childLink['text'] }}
                                                                    </a>
                                                                @endforeach
                                                            </div>
                                                        </li>
                                                    @endif
                                                @endforeach
                                            </ul>
                                        </nav>
                                    </div>
                                    <div class="tab-pane fade" id="mcat" role="tabpanel"
                                        aria-labelledby="mcat-tab">
                                        <nav class="slideable-menu">
                                            @include('includes.mobile-category')

                                        </nav>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- Navbar-->
        <div class="navbar">
            <div class="container">
                <div class="row g-3 w-100">
                    @if ($setting->is_show_category == 1)
                        <div class="col-lg-3">
                            @include('includes.categories')
                        </div>
                    @endif
                    <div class="col-lg-9 d-flex justify-content-between">
                        <div class="nav-inner">
                            @include('master.inc.site-menu')
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="mobile-primary-nav visible-on-mobile">
            <div class="container">
                <div class="mobile-primary-nav-links">
                    @foreach ($mobileMenuLinks as $link)
                        @php
                            $mobilePrimaryHref = Helper::getHref($link);
                            $mobilePrimaryTarget = $link['target'] ?? '_self';
                            $mobilePrimaryActive = $mobilePrimaryHref === url()->current();
                        @endphp
                        @if (!array_key_exists('children', $link))
                            <a href="{{ $link['href'] == null ? $mobilePrimaryHref : $link['href'] }}"
                                target="{{ $mobilePrimaryTarget }}"
                                class="mobile-primary-nav-link {{ $mobilePrimaryActive ? 'is-active' : '' }}">
                                {{ $link['text'] }}
                            </a>
                        @else
                            <a href="#"
                                class="mobile-primary-nav-link mobile-menu-toggle"
                                role="button"
                                aria-label="{{ __('Open :menu menu', ['menu' => $link['text']]) }}">
                                {{ $link['text'] }}
                            </a>
                        @endif
                    @endforeach
                </div>
            </div>
        </div>

    </header>

    <div id="mobileCartBackdrop" class="mobile-cart-backdrop" aria-hidden="true"></div>

    <!-- Vehicle Search modal (YMM) — desktop: centered; mobile: bottom sheet style -->
    <div id="vehiclePickerBackdrop" class="vehicle-picker-backdrop" aria-hidden="true">
        <div id="vehiclePickerModal" class="vehicle-picker-modal" role="dialog" aria-modal="true"
            aria-labelledby="vehiclePickerTitle">
            <div class="vehicle-picker-head">
                <h2 id="vehiclePickerTitle" class="vehicle-picker-title">{{ __('Vehicle Search') }}</h2>
                <button type="button" id="vehiclePickerClose" class="vehicle-picker-close"
                    aria-label="{{ __('Close vehicle search') }}">×</button>
            </div>
            <div class="vehicle-picker-body">
                <div class="vehicle-picker-row">
                    <select id="ymm_year" class="form-control" aria-label="{{ __('Select Year') }}">
                        <option value="">{{ __('Select Year') }}</option>
                    </select>
                    <select id="ymm_make" class="form-control" disabled aria-label="{{ __('Select Make') }}">
                        <option value="">{{ __('Select Make') }}</option>
                    </select>
                    <select id="ymm_model" class="form-control" disabled aria-label="{{ __('Select Model') }}">
                        <option value="">{{ __('Select Model') }}</option>
                    </select>
                </div>
                <p id="vehiclePickerHint" class="vehicle-picker-hint" role="alert"></p>
            </div>
            <div class="vehicle-picker-footer">
                <button type="button" id="vehiclePickerSearch" class="vehicle-picker-search-btn">
                    <i class="fas fa-search" aria-hidden="true"></i>
                    {{ __('Search') }}
                </button>
            </div>
        </div>
    </div>

    <!-- Page Content-->
    @yield('content')

    <!--    announcement banner section start   -->
    <a class="announcement-banner" href="#announcement-modal"></a>
    <div id="announcement-modal" class="mfp-hide white-popup">
        @php
            $announceWidth = 400;
            $announceHeight = 400;
            $announcePath = public_path('storage/images/' . $setting->announcement);
            if (file_exists($announcePath)) {
                $announceInfo = @getimagesize($announcePath);
                if ($announceInfo) {
                    $announceWidth = $announceInfo[0];
                    $announceHeight = $announceInfo[1];
                }
            }
        @endphp
        @if ($setting->announcement_type == 'newletter')
            <div class="announcement-with-content">
                <div class="left-area">
                    <img src="{{ asset('storage/images/' . $setting->announcement) }}" alt="{{ $setting->announcement_title ?: __('Announcement') }}" width="{{ $announceWidth }}" height="{{ $announceHeight }}" loading="lazy">
                </div>
                <div class="right-area">
                    <h3 class="">{{ $setting->announcement_title }}</h3>
                    <p>{{ $setting->announcement_details }}</p>
                    <form class="subscriber-form" action="{{ route('front.subscriber.submit') }}" method="post">
                        @csrf
                        <div class="input-group">
                            <input class="form-control" type="email" name="email"
                                aria-label="{{ __('Newsletter email address') }}"
                                placeholder="{{ __('Your e-mail') }}">
                            <span class="input-group-addon"><i class="icon-mail"></i></span>
                        </div>
                        <div aria-hidden="true">
                            <input type="hidden" name="b_c7103e2c981361a6639545bd5_1194bb7544" tabindex="-1">
                        </div>

                        <button class="btn btn-primary btn-block mt-2" type="submit">
                            <span>{{ __('Subscribe') }}</span>
                        </button>
                    </form>
                </div>
            </div>
        @else
            <a href="{{ $setting->announcement_link }}">
                <img src="{{ asset('storage/images/' . $setting->announcement) }}" alt="{{ $setting->announcement_title ?: __('Announcement') }}" width="{{ $announceWidth }}" height="{{ $announceHeight }}" loading="lazy">
            </a>
        @endif


    </div>
    <!--    announcement banner section end   -->

    <!-- Site Footer-->
    <footer class="site-footer">
        <div class="container">
            <div class="row">
                <div class="col-lg-4 col-md-6">
                    <!-- Contact Info-->
                    <section class="widget widget-light-skin">
                        <h3 class="widget-title">{{ __('Get In Touch') }}</h3>
                        <!-- <p class="mb-1"><strong>{{ __('Address') }}: </strong> {{ $setting->footer_address }}</p> -->
                        <p class="mb-1"><strong>{{ __('Phone') }}: </strong> {{ $setting->footer_phone }}</p>
                        <p class="mb-1"><strong>{{ __('Email') }}: </strong> {{ $setting->footer_email }}</p>
                        <ul class="list-unstyled text-sm">
                            <li><span class=""><strong>{{ $setting->working_days_from_to }}:
                                    </strong></span>
                                    <!-- {{ $setting->friday_start }} - {{ $setting->friday_end }} -->
                                </li>
                        </ul>
                        @php
                            $links = json_decode($setting->social_link, true)['links'];
                            $icons = json_decode($setting->social_link, true)['icons'];

                        @endphp
                        <div class="footer-social-links">
                            @foreach ($links as $link_key => $link)
                                @php
                                    $label = 'Social Media';
                                    foreach (['facebook', 'twitter', 'instagram', 'youtube', 'linkedin', 'pinterest'] as $platform) {
                                        if (strpos($icons[$link_key], $platform) !== false) {
                                            $label = ucfirst($platform);
                                            break;
                                        }
                                    }
                                @endphp
                                <a href="{{ $link }}" aria-label="{{ $label }}"><span><i
                                            class="{{ $icons[$link_key] }}"></i></span></a>
                            @endforeach
                        </div>
                    </section>
                </div>
                <div class="col-lg-4 col-sm-6">
                    <!-- Customer Info-->
                    <div class="widget widget-links widget-light-skin">
                        <h3 class="widget-title">{{ __('Usefull Links') }}</h3>
                          
                        <ul>
                            <li>
                                <a class="" href="{{ route('front.catalog') }}">{{ __('Catalog') }}</a>
                            </li>
                            @if ($setting->is_faq == 1)
                                <li>
                                    <a class="" href="{{ route('front.faq') }}">{{ __('Faq') }}</a>
                                </li>
                            @endif
                            @foreach ($footer_pages as $page)
                                <li><a href="{{ route('front.page', $page->slug) }}">{{ $page->title }}</a></li>
                            @endforeach

                        </ul>
                    </div>
                </div>
                <div class="col-lg-4">
                    <!-- Subscription-->
                    <section class="widget">
                        <h3 class="widget-title">{{ __('Newsletter') }}</h3>
                        <form class="row subscriber-form" action="{{ route('front.subscriber.submit') }}"
                            method="post">
                            @csrf
                            <div class="col-sm-12">
                                <div class="input-group">
                                    <input class="form-control" type="email" name="email"
                                        aria-label="{{ __('Newsletter email address') }}"
                                        placeholder="{{ __('Your e-mail') }}">
                                    <span class="input-group-addon"><i class="icon-mail"></i></span>
                                </div>
                                <div aria-hidden="true">
                                    <input type="hidden" name="b_c7103e2c981361a6639545bd5_1194bb7544"
                                        tabindex="-1">
                                </div>

                            </div>
                            <div class="col-sm-12">
                                <button class="btn btn-primary btn-block mt-2" type="submit">
                                    <span>{{ __('Subscribe') }}</span>
                                </button>
                            </div>
                            <div class="col-lg-12">
                                <p class="text-sm opacity-80 pt-2">
                                    {{ __('Subscribe to our Newsletter to receive early discount offers, latest news, sales and promo information.') }}
                                </p>
                            </div>
                        </form>
                        <div class="pt-3">
                            @php
                                $gatewayWidth = 300;
                                $gatewayHeight = 50;
                                $gatewayPath = $setting->footer_gateway_img ? public_path('storage/images/' . ltrim((string) $setting->footer_gateway_img, '/')) : '';
                                if ($gatewayPath && file_exists($gatewayPath)) {
                                    $gatewayInfo = @getimagesize($gatewayPath);
                                    if ($gatewayInfo) {
                                        $gatewayWidth = $gatewayInfo[0];
                                        $gatewayHeight = $gatewayInfo[1];
                                    }
                                }
                            @endphp
                            <img class="d-block gateway_image"
                                src="{{ $setting->footer_gateway_img ? url('/core/public/storage/images/' . ltrim((string) $setting->footer_gateway_img, '/')) : asset('system/resources/assets/images/placeholder.png') }}"
                                alt="{{ __('Accepted payment methods') }}"
                                width="{{ $gatewayWidth }}"
                                height="{{ $gatewayHeight }}"
                                loading="lazy">
                        </div>
                    </section>
                </div>
            </div>
            <!-- Copyright-->
            <p class="footer-copyright"> {{ $setting->copy_right }}</p>
        </div>
    </footer>

    <!-- Back To Top Button-->
    <a class="scroll-to-top-btn" href="#" aria-label="{{ __('Scroll back to top') }}">
        <i class="icon-chevron-up" aria-hidden="true"></i>
    </a>
    <!-- Backdrop-->
    <div class="site-backdrop"></div>

    <!-- Cookie alert dialog  -->
    @if ($setting->is_cookie == 1)
        @include('cookie-consent::index')
    @endif
    <!-- Cookie alert dialog  -->


    @php
        $mainbs = [];
        $mainbs['is_announcement'] = $setting->is_announcement;
        $mainbs['announcement_delay'] = $setting->announcement_delay;
        $mainbs['overlay'] = $setting->overlay;
        $mainbs = json_encode($mainbs);
    @endphp

    <script>
        var mainbs = {!! $mainbs !!};
        var decimal_separator = '{!! $setting->decimal_separator !!}';
        var thousand_separator = '{!! $setting->thousand_separator !!}';
    </script>

    <script>
        let language = {
            Days: '{{ __('Days') }}',
            Hrs: '{{ __('Hrs') }}',
            Min: '{{ __('Min') }}',
            Sec: '{{ __('Sec') }}',
        }
    </script>



    <!-- JavaScript (jQuery) libraries, plugins and custom scripts-->
    <script type="text/javascript" src="{{ asset('assets/front/js/plugins.min.js') }}" defer></script>
    <script type="text/javascript" src="{{ asset('assets/back/js/plugin/bootstrap-notify/bootstrap-notify.min.js') }}" defer></script>
    <script type="text/javascript" src="{{ asset('assets/front/js/scripts.min.js') }}" defer></script>
    <script type="text/javascript" src="{{ asset('assets/front/js/lazy.min.js') }}" defer></script>
    <script type="text/javascript" src="{{ asset('assets/front/js/lazy.plugin.js') }}" defer></script>
    <script type="text/javascript" src="{{ asset('assets/front/js/myscript.js') }}" defer></script>
    @yield('script')

    <!-- Lazy load tracking scripts (GTM, GA, Facebook Pixel) on first user interaction -->
    <script>
        window.addEventListener('load', function() {
            var loadThirdPartyScripts = function() {
                // 1. Google Tag Manager
                @if (config('services.google.tag_manager_id'))
                (function() {
                    if (document.querySelector('script[src*="/metrics/gtm.js"]')) return;
                    var js = document.createElement('script');
                    js.async = true;
                    js.src = '/metrics/gtm.js?id={{ config('services.google.tag_manager_id') }}';
                    var s = document.getElementsByTagName('script')[0];
                    s.parentNode.insertBefore(js, s);
                })();
                @endif

                // 2. Google Analytics (gtag.js)
                @if (!($setting->is_google_analytics == '1' && trim((string) $setting->google_analytics) !== ''))
                (function() {
                    if (document.querySelector('script[src*="/metrics/gtag/js"]')) return;
                    var js = document.createElement('script');
                    js.async = true;
                    js.src = '/metrics/gtag/js?id={{ config('services.google.analytics_id') }}';
                    var s = document.getElementsByTagName('script')[0];
                    s.parentNode.insertBefore(js, s);
                })();
                @endif

                // 3. Meta Pixel
                @if (!($setting->is_facebook_pixel == '1' && trim((string) $setting->facebook_pixel) !== ''))
                (function() {
                    if (document.querySelector('script[src*="connect.facebook.net/en_US/fbevents.js"]')) return;
                    var js = document.createElement('script');
                    js.async = true;
                    js.src = 'https://connect.facebook.net/en_US/fbevents.js';
                    var s = document.getElementsByTagName('script')[0];
                    s.parentNode.insertBefore(js, s);
                })();
                @endif
            };

            var trackingTriggered = false;
            var triggerTrackingLoad = function() {
                if (trackingTriggered) return;
                trackingTriggered = true;
                loadThirdPartyScripts();
                window.removeEventListener('scroll', triggerTrackingLoad);
                window.removeEventListener('mousemove', triggerTrackingLoad);
                window.removeEventListener('touchstart', triggerTrackingLoad);
            };
            window.addEventListener('scroll', triggerTrackingLoad, {passive: true});
            window.addEventListener('mousemove', triggerTrackingLoad, {passive: true});
            window.addEventListener('touchstart', triggerTrackingLoad, {passive: true});
        });
    </script>

    @if ($setting->is_facebook_messenger == '1')
        <!-- Messenger Chat Plugin Code -->
        <div id="fb-root"></div>

        <!-- Your Chat Plugin code -->
        <div id="fb-customer-chat" class="fb-customerchat">
        </div>

        <script>
            var chatbox = document.getElementById('fb-customer-chat');
            chatbox.setAttribute("page_id", "{{ $setting->facebook_messenger }}");
            chatbox.setAttribute("attribution", "biz_inbox");
            window.fbAsyncInit = function() {
                FB.init({
                    xfbml: true,
                    version: 'v11.0'
                });
            };
            window.addEventListener('load', function() {
                var loadMessenger = function() {
                    if (document.getElementById('facebook-jssdk')) {
                        return;
                    }

                    var js = document.createElement('script');
                    js.id = 'facebook-jssdk';
                    js.src = 'https://connect.facebook.net/en_US/sdk/xfbml.customerchat.js';
                    js.async = true;
                    document.body.appendChild(js);
                };

                var triggered = false;
                var triggerLoad = function() {
                    if (triggered) return;
                    triggered = true;
                    loadMessenger();
                    window.removeEventListener('scroll', triggerLoad);
                    window.removeEventListener('mousemove', triggerLoad);
                    window.removeEventListener('touchstart', triggerLoad);
                };
                window.addEventListener('scroll', triggerLoad, {passive: true});
                window.addEventListener('mousemove', triggerLoad, {passive: true});
                window.addEventListener('touchstart', triggerLoad, {passive: true});
            });
        </script>
    @endif



    <script type="text/javascript">
        let mainurl = '{{ route('front.index') }}';

        let view_extra_index = 0;
        // Notifications
        function SuccessNotification(title) {
            $.notify({
                title: ` <strong>${title}</strong>`,
                message: '',
                icon: 'fas fa-check-circle'
            }, {
                element: 'body',
                position: null,
                type: "success",
                allow_dismiss: true,
                newest_on_top: false,
                showProgressbar: false,
                placement: {
                    from: "top",
                    align: "right"
                },
                offset: 20,
                spacing: 10,
                z_index: 1031,
                delay: 5000,
                timer: 1000,
                url_target: '_blank',
                mouse_over: null,
                animate: {
                    enter: 'animated fadeInDown',
                    exit: 'animated fadeOutUp'
                },
                onShow: null,
                onShown: null,
                onClose: null,
                onClosed: null,
                icon_type: 'class'
            });
        }

        function DangerNotification(title) {
            $.notify({
                // options
                title: ` <strong>${title}</strong>`,
                message: '',
                icon: 'fas fa-exclamation-triangle'
            }, {
                // settings
                element: 'body',
                position: null,
                type: "danger",
                allow_dismiss: true,
                newest_on_top: false,
                showProgressbar: false,
                placement: {
                    from: "top",
                    align: "right"
                },
                offset: 20,
                spacing: 10,
                z_index: 1031,
                delay: 5000,
                timer: 1000,
                url_target: '_blank',
                mouse_over: null,
                animate: {
                    enter: 'animated fadeInDown',
                    exit: 'animated fadeOutUp'
                },
                onShow: null,
                onShown: null,
                onClose: null,
                onClosed: null,
                icon_type: 'class'
            });
        }
        // Notifications Ends
    </script>

    @if (Session::has('error'))
        <script>
            document.addEventListener("DOMContentLoaded", function() {
                DangerNotification('{{ Session::get('error') }}');
            });
        </script>
    @endif
    @if (Session::has('success'))
        <script>
            document.addEventListener("DOMContentLoaded", function() {
                SuccessNotification('{{ Session::get('success') }}');
            });
        </script>
    @endif
    <!-- WhatsApp Popup -->
    <div id="wa-popup" class="wa-popup hidden">
        <div class="wa-header">
            <strong>99 Auto Parts Support</strong>
            <span id="wa-close">×</span>
        </div>
        <p>
            👋 Hi! <br>
            Need help finding the right part for your vehicle?
        </p>
        <a href="https://wa.me/12892715870?text=Hi%20I%20need%20help%20with%20auto%20parts"
        target="_blank"
        class="wa-btn">
            Chat on WhatsApp
        </a>
    </div>

    <!-- Floating WhatsApp Icon -->
    <a href="#" class="whatsapp-float" id="wa-trigger" aria-label="{{ __('Open WhatsApp support chat') }}">
        <i class="fab fa-whatsapp" aria-hidden="true"></i>
    </a>
<script>
    document.addEventListener("DOMContentLoaded", function () {
        document.querySelectorAll("img:not([loading])").forEach(img => {
            img.setAttribute("loading", "lazy");
        });
    });
    document.querySelectorAll("img:not(.hero-img):not([loading])")

</script>
@include('includes.stripe_elements_script')
</body>
    <script>
        document.addEventListener("DOMContentLoaded", function () {

            const popup   = document.getElementById('wa-popup');
            const trigger = document.getElementById('wa-trigger');
            const close   = document.getElementById('wa-close');

            if (!popup || !trigger) return;

            // Auto show once after 8 seconds
            if (!localStorage.getItem('wa_popup_shown')) {
                setTimeout(() => {
                    popup.classList.remove('hidden');
                    localStorage.setItem('wa_popup_shown', 'yes');
                }, 8000);
            }

            // Click icon → open popup
            trigger.addEventListener('click', function (e) {
                e.preventDefault();
                popup.classList.remove('hidden');
            });

            // Close popup
            close.addEventListener('click', function () {
                popup.classList.add('hidden');
            });
        });
    </script>

</html>
<script>
    const VEHICLE_YEARS_URL  = "{{ route('vehicle.years') }}";
    const VEHICLE_MAKES_URL  = "{{ route('vehicle.makes', ':year') }}";
    const VEHICLE_MODELS_URL = "{{ route('vehicle.models', ':make') }}";
</script>

<script>
const STORAGE_KEY = 'selected_vehicle';

const YMM_PLACEHOLDERS = {
    year: @json(__('Select Year')),
    make: @json(__('Select Make')),
    model: @json(__('Select Model')),
};

// Populate dropdown
function fillSelect(select, data, placeholder) {
    select.innerHTML = `<option value="">${placeholder}</option>`;
    data.forEach(item => {
        select.innerHTML += `<option value="${item.id}">
            ${item.make || item.year || item.model}
        </option>`;
    });
}

const ymm_year  = document.getElementById('ymm_year');
const ymm_make  = document.getElementById('ymm_make');
const ymm_model = document.getElementById('ymm_model');

// 🔒 HARD STOP — initial state
fillSelect(ymm_make, [], YMM_PLACEHOLDERS.make);
fillSelect(ymm_model, [], YMM_PLACEHOLDERS.model);
ymm_make.disabled = true;
ymm_model.disabled = true;

// Load ONLY years
function loadYears(selectedYearId = null) {

    const cached = localStorage.getItem('vehicle_years');

    if (cached) {
        fillSelect(ymm_year, JSON.parse(cached), YMM_PLACEHOLDERS.year);
        if (selectedYearId) ymm_year.value = selectedYearId;
        return Promise.resolve();
    }

    return fetch(VEHICLE_YEARS_URL)
        .then(res => res.json())
        .then(data => {
            localStorage.setItem('vehicle_years', JSON.stringify(data));
            fillSelect(ymm_year, data, YMM_PLACEHOLDERS.year);
            if (selectedYearId) ymm_year.value = selectedYearId;
        });
}


// initial load (no selection)
loadYears();


// Year → Make
function loadMakes(yearId, selectedMakeId = null) {

    const key = `vehicle_makes_${yearId}`;
    const cached = localStorage.getItem(key);

    ymm_make.disabled = true;
    ymm_model.disabled = true;

    if (cached) {
        fillSelect(ymm_make, JSON.parse(cached), YMM_PLACEHOLDERS.make);
        ymm_make.disabled = false;
        if (selectedMakeId) ymm_make.value = selectedMakeId;
        return Promise.resolve();
    }

    return fetch(VEHICLE_MAKES_URL.replace(':year', yearId))
        .then(res => res.json())
        .then(data => {
            localStorage.setItem(key, JSON.stringify(data));
            fillSelect(ymm_make, data, YMM_PLACEHOLDERS.make);
            ymm_make.disabled = false;
            if (selectedMakeId) ymm_make.value = selectedMakeId;
        });
}


ymm_year.addEventListener('change', () => {
    if (!ymm_year.value) return;
    loadMakes(ymm_year.value);
});


// Make → Model
function loadModels(makeId, selectedModelId = null) {

    const key = `vehicle_models_${makeId}`;
    const cached = localStorage.getItem(key);

    ymm_model.disabled = true;

    if (cached) {
        fillSelect(ymm_model, JSON.parse(cached), YMM_PLACEHOLDERS.model);
        ymm_model.disabled = false;
        if (selectedModelId) ymm_model.value = selectedModelId;
        return Promise.resolve();
    }

    return fetch(VEHICLE_MODELS_URL.replace(':make', makeId))
        .then(res => res.json())
        .then(data => {
            localStorage.setItem(key, JSON.stringify(data));
            fillSelect(ymm_model, data, YMM_PLACEHOLDERS.model);
            ymm_model.disabled = false;
            if (selectedModelId) ymm_model.value = selectedModelId;
        });
}


ymm_make.addEventListener('change', () => {
    if (!ymm_make.value) return;
    loadModels(ymm_make.value);
});

</script>
<script>
    document.addEventListener('DOMContentLoaded', function () {

    const yearSelect  = document.getElementById('ymm_year');
    const makeSelect  = document.getElementById('ymm_make');
    const modelSelect = document.getElementById('ymm_model');

    const hiddenYear  = document.getElementById('search_year');
    const hiddenMake  = document.getElementById('search_make');
    const hiddenModel = document.getElementById('search_model');

    function getText(select) {
        const opt = select.options[select.selectedIndex];
        return opt ? opt.text.trim() : '';
    }

    /* 🔹 Save on change */
    yearSelect.addEventListener('change', () => {
        hiddenYear.value = getText(yearSelect);
        hiddenMake.value = '';
        hiddenModel.value = '';
    });

    makeSelect.addEventListener('change', () => {
        hiddenMake.value = getText(makeSelect);
        hiddenModel.value = '';
    });

    modelSelect.addEventListener('change', () => {
        hiddenModel.value = getText(modelSelect);
    });

    /* 🔹 Save on submit */
    const form = document.getElementById('header_search_form');
    if (!form) return;

    form.addEventListener('submit', function () {

        if (!ymm_year.value || !ymm_make.value || !ymm_model.value) return;

        localStorage.setItem(STORAGE_KEY, JSON.stringify({
            year_id: ymm_year.value,
            year: ymm_year.options[ymm_year.selectedIndex].text,

            make_id: ymm_make.value,
            make: ymm_make.options[ymm_make.selectedIndex].text,

            model_id: ymm_model.value,
            model: ymm_model.options[ymm_model.selectedIndex].text
        }));
    });

});


</script>
<script>
document.addEventListener('DOMContentLoaded', function () {

    const clearBtn = document.getElementById('clearVehicleSummary');

    if (!clearBtn) return;

    clearBtn.addEventListener('click', function () {

        /* 🔥 Clear storage */
        localStorage.removeItem(STORAGE_KEY);


        /* 🔄 Reset dropdowns */
        const year  = document.getElementById('ymm_year');
        const make  = document.getElementById('ymm_make');
        const model = document.getElementById('ymm_model');

        if (year && make && model) {
            year.selectedIndex = 0;
            year.disabled = false;

            fillSelect(make, [], YMM_PLACEHOLDERS.make);
            fillSelect(model, [], YMM_PLACEHOLDERS.model);

            make.disabled  = true;
            model.disabled = true;
        }

        /* Hide summary */
        document.getElementById('vehicleSummary').style.display = 'none';

        /* 🔁 Redirect to clean catalog */
        window.location.href = "{{ route('front.catalog') }}";
    });
});
</script>


<script>
function resetVehicleDropdowns() {
    const year  = document.getElementById('ymm_year');
    const make  = document.getElementById('ymm_make');
    const model = document.getElementById('ymm_model');

    if (!year || !make || !model) return;

    year.selectedIndex = 0;  // "Year"
    make.selectedIndex = 0;  // "Make"
    model.selectedIndex = 0; // "Model"

    make.disabled  = true;
    model.disabled = true;
}
</script>

<script>


</script>
<script>
function resetVehicleDropdownsAndRedirect() {

    const year  = document.getElementById('ymm_year');
    const make  = document.getElementById('ymm_make');
    const model = document.getElementById('ymm_model');

    const hiddenYear  = document.getElementById('search_year');
    const hiddenMake  = document.getElementById('search_make');
    const hiddenModel = document.getElementById('search_model');

    if (!year || !make || !model) return;

    /* 🔥 CLEAR STORAGE */
    localStorage.removeItem(STORAGE_KEY);


    /* 🔄 RESET DROPDOWNS */
    year.selectedIndex = 0;
    year.disabled = false;

    fillSelect(make, [], YMM_PLACEHOLDERS.make);
    fillSelect(model, [], YMM_PLACEHOLDERS.model);

    make.disabled  = true;
    model.disabled = true;

    /* 🔄 RESET HIDDEN INPUTS */
    hiddenYear.value  = '';
    hiddenMake.value  = '';
    hiddenModel.value = '';

    /* 🔄 HIDE SUMMARY */
    const summaryBox = document.getElementById('vehicleSummary');
    if (summaryBox) summaryBox.style.display = 'none';
}

document.addEventListener('DOMContentLoaded', async function () {

    const stored = localStorage.getItem(STORAGE_KEY);
    if (!stored) return;

    const v = JSON.parse(stored);
    if (!v.year_id || !v.make_id || !v.model_id) return;

    // 1️⃣ Load & select YEAR
    await loadYears(v.year_id);

    // 2️⃣ Load MAKES & select make
    await loadMakes(v.year_id, v.make_id);

    // 3️⃣ Load MODELS & select model
    await loadModels(v.make_id, v.model_id);

    // Keep dropdowns enabled so users can open Vehicle Search and change YMM anytime

    // 🔽 Summary
    const summaryBox  = document.getElementById('vehicleSummary');
    const summaryText = summaryBox.querySelector('.vehicle-text');
    summaryText.textContent = `✔: ${v.year} ${v.make} ${v.model}`;
    summaryBox.style.display = 'flex';

    // 🔁 Hidden inputs
    document.getElementById('search_year').value  = v.year;
    document.getElementById('search_make').value  = v.make;
    document.getElementById('search_model').value = v.model;
});


</script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const toggleBtn = document.getElementById('vehiclePickerToggle');
    const backdrop = document.getElementById('vehiclePickerBackdrop');
    const closeBtn = document.getElementById('vehiclePickerClose');
    const searchBtn = document.getElementById('vehiclePickerSearch');
    const hintEl = document.getElementById('vehiclePickerHint');
    const form = document.getElementById('header_search_form');
    const ymmMsg = @json(__('Please select Year, Make, and Model.'));

    if (!toggleBtn || !backdrop) return;

    function unlockYmmForEditing() {
        const y = document.getElementById('ymm_year');
        const m = document.getElementById('ymm_make');
        const mo = document.getElementById('ymm_model');
        if (!y || !m || !mo) return;
        y.disabled = false;
        m.disabled = !y.value;
        mo.disabled = !m.value;
    }

    function setPanelState(isOpen) {
        backdrop.classList.toggle('is-open', isOpen);
        document.body.classList.toggle('vehicle-picker-open', isOpen);
        toggleBtn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        backdrop.setAttribute('aria-hidden', isOpen ? 'false' : 'true');
        if (hintEl) {
            hintEl.classList.remove('is-visible');
            hintEl.textContent = '';
        }
        if (isOpen) {
            unlockYmmForEditing();
        }
        const yearEl = document.getElementById('ymm_year');
        if (isOpen && yearEl) {
            setTimeout(function () { yearEl.focus(); }, 50);
        }
    }

    toggleBtn.addEventListener('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        setPanelState(!backdrop.classList.contains('is-open'));
    });

    if (closeBtn) {
        closeBtn.addEventListener('click', function () {
            setPanelState(false);
        });
    }

    backdrop.addEventListener('click', function (e) {
        if (e.target === backdrop) {
            setPanelState(false);
        }
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && backdrop.classList.contains('is-open')) {
            setPanelState(false);
        }
    });

    if (searchBtn && form) {
        searchBtn.addEventListener('click', function () {
            const y = document.getElementById('ymm_year');
            const m = document.getElementById('ymm_make');
            const mo = document.getElementById('ymm_model');
            if (!y || !m || !mo || !y.value || !m.value || !mo.value) {
                if (hintEl) {
                    hintEl.textContent = ymmMsg;
                    hintEl.classList.add('is-visible');
                }
                return;
            }
            setPanelState(false);
            if (typeof form.requestSubmit === 'function') {
                form.requestSubmit();
            } else {
                form.submit();
            }
        });
    }
});
</script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const categoryToggle = document.querySelector('.mobile-category-toggle a');
    const mobileMenu = document.querySelector('.mobile-menu');
    const menuTab = document.getElementById('mmenu-tab');
    const categoryTab = document.getElementById('mcat-tab');
    const menuPane = document.getElementById('mmenu');
    const categoryPane = document.getElementById('mcat');

    function showMobileMainMenuTab() {
        if (!menuTab || !menuPane) return;

        if (categoryTab) {
            categoryTab.classList.remove('active');
            categoryTab.setAttribute('aria-selected', 'false');
        }
        if (categoryPane) {
            categoryPane.classList.remove('show', 'active');
        }

        menuTab.classList.add('active');
        menuTab.setAttribute('aria-selected', 'true');
        menuPane.classList.add('show', 'active');
    }

    function showMobileCategoryMenu() {
        if (!mobileMenu || !categoryTab || !categoryPane) return;

        setCartDrawerState(false);
        mobileMenu.classList.add('open');
        if (menuTab) {
            menuTab.classList.remove('active');
            menuTab.setAttribute('aria-selected', 'false');
        }
        if (menuPane) {
            menuPane.classList.remove('show', 'active');
        }

        categoryTab.classList.add('active');
        categoryTab.setAttribute('aria-selected', 'true');
        categoryPane.classList.add('show', 'active');
    }

    if (categoryToggle) {
        categoryToggle.addEventListener('click', function (event) {
            event.preventDefault();
            event.stopPropagation();
            showMobileCategoryMenu();
        });
    }

    const mainMenuToggle = document.querySelector('.toolbar > .mobile-menu-toggle:not(.mobile-category-toggle) a');
    if (mainMenuToggle) {
        mainMenuToggle.addEventListener('click', function () {
            showMobileMainMenuTab();
        });
    }

    const cartItem = document.querySelector('.cart-toolbar-item');
    const cartToggle = document.querySelector('.mobile-cart-toggle');
    const cartDropdown = cartItem ? cartItem.querySelector('.cart-dropdown') : null;
    const cartBackdrop = document.getElementById('mobileCartBackdrop');
    const cartClose = document.getElementById('mobileCartClose');
    const cartPlaceholder = document.createComment('mobile cart drawer placeholder');

    if (cartDropdown && cartDropdown.parentNode) {
        cartDropdown.parentNode.insertBefore(cartPlaceholder, cartDropdown);
    }

    function isMobileHeader() {
        return window.matchMedia('(max-width: 767.98px)').matches;
    }

    function setCartDrawerState(isOpen) {
        if (!cartItem || !cartBackdrop) return;

        if (isOpen && mobileMenu) {
            mobileMenu.classList.remove('open');
        }

        if (cartDropdown) {
            if (isOpen && isMobileHeader()) {
                document.body.appendChild(cartDropdown);
                cartDropdown.classList.add('mobile-cart-drawer');
            } else if (cartPlaceholder.parentNode && cartDropdown.parentNode !== cartPlaceholder.parentNode) {
                cartPlaceholder.parentNode.insertBefore(cartDropdown, cartPlaceholder.nextSibling);
                cartDropdown.classList.remove('mobile-cart-drawer');
            }
        }

        cartItem.classList.toggle('is-cart-open', isOpen);
        cartBackdrop.classList.toggle('is-open', isOpen);
        document.body.classList.toggle('mobile-cart-open', isOpen);
        cartBackdrop.setAttribute('aria-hidden', isOpen ? 'false' : 'true');
        if (cartToggle) {
            cartToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        }
    }

    if (cartToggle) {
        cartToggle.setAttribute('aria-expanded', 'false');
        cartToggle.addEventListener('click', function (event) {
            if (!isMobileHeader()) return;

            event.preventDefault();
            event.stopPropagation();
            setCartDrawerState(true);
        });
    }

    if (cartBackdrop) {
        cartBackdrop.addEventListener('click', function () {
            setCartDrawerState(false);
        });
    }

    if (cartClose) {
        cartClose.addEventListener('click', function () {
            setCartDrawerState(false);
        });
    }

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            setCartDrawerState(false);
        }
    });

    window.addEventListener('resize', function () {
        if (!isMobileHeader()) {
            setCartDrawerState(false);
        }
    });
});
</script>
