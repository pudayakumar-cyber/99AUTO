<?php

namespace App\Providers;

use App\Models\Category;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function boot()
    {
        Paginator::useBootstrap();

        $setting = Cache::remember('site_settings', 3600, function () {
            return DB::table('settings')->find(1);
        });

        $extraSettings = Cache::remember('extra_settings', 3600, function () {
            return DB::table('extra_settings')->find(1);
        });

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

        $menuCategories = Cache::remember('menu_categories', 3600, function () {
            return Category::with(['subcategory.childcategory'])
                ->whereStatus(1)
                ->whereNotNull('photo')
                ->where('photo', '!=', '')
                ->orderBy('serial', 'asc')
                ->take(8)
                ->get();
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

        View::share([
            'setting' => $setting,
            'extra_settings' => $extraSettings,
            'menus' => $menus,
            'default_language' => $defaultLanguage,
            'website_languages' => $websiteLanguages,
            'site_currencies' => $currencies,
            'header_categories' => $headerCategories,
            'menu_categories' => $menuCategories,
            'header_pages' => $headerPages,
            'footer_pages' => $footerPages,
            'free_shipping' => $freeShipping,
        ]);

        if (!session()->has('popup')) {
            View::share('visit', 1);
        }

        session()->put('popup', 1);
    }

    public function register()
    {
    }
}
