<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\View;
use Illuminate\Pagination\Paginator;

class AppServiceProvider extends ServiceProvider
{
    public function boot()
    {
        Paginator::useBootstrap();

        View::composer('*', function ($view) {

            // 🔹 Site settings (cached)
            $setting = Cache::remember('site_settings', 3600, function () {
                return DB::table('settings')->find(1);
            });

            // 🔹 Extra settings (cached)
            $extraSettings = Cache::remember('extra_settings', 3600, function () {
                return DB::table('extra_settings')->find(1);
            });

            // 🔹 Menus (cached)
            $menus = Cache::remember('global_menus', 3600, function () {
                return DB::table('menus')->find(1);
            });

            $defaultLanguage = Cache::remember('default_language', 3600, function () {
                return DB::table('languages')->where('is_default', 1)->first();
            });

            $websiteLanguages = Cache::remember('website_languages', 3600, function () {
                return DB::table('languages')->whereType('Website')->get();
            });

            $currencies = Cache::remember('site_currencies', 3600, function () {
                return DB::table('currencies')->get();
            });

            $headerCategories = Cache::remember('header_categories', 3600, function () {
                return DB::table('categories')->whereStatus(1)->get();
            });

            $headerPages = Cache::remember('header_pages', 3600, function () {
                return DB::table('pages')->where(function ($query) {
                    $query->wherePos(0)->orWhere('pos', 2);
                })->get();
            });

            $footerPages = Cache::remember('footer_pages', 3600, function () {
                return DB::table('pages')->where(function ($query) {
                    $query->wherePos(2)->orWhere('pos', 1);
                })->get();
            });

            $freeShipping = Cache::remember('free_shipping_service', 3600, function () {
                return DB::table('shipping_services')
                    ->whereStatus(1)
                    ->whereIsCondition(1)
                    ->first();
            });

            $view->with([
                'setting'        => $setting,
                'extra_settings' => $extraSettings,
                'menus'          => $menus,
                'default_language' => $defaultLanguage,
                'website_languages' => $websiteLanguages,
                'site_currencies' => $currencies,
                'header_categories' => $headerCategories,
                'header_pages' => $headerPages,
                'footer_pages' => $footerPages,
                'free_shipping' => $freeShipping,
            ]);

            // 🔹 Popup session logic (unchanged)
            if (!session()->has('popup')) {
                view()->share('visit', 1);
            }

            session()->put('popup', 1);
        });
    }

    public function register()
    {
    }
}
