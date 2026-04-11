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

            $view->with([
                'setting'        => $setting,
                'extra_settings' => $extraSettings,
                'menus'          => $menus,
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
