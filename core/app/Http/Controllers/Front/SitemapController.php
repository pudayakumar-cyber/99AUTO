<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Services\ProductSitemap;

class SitemapController extends Controller
{
    public function index(ProductSitemap $sitemap)
    {
        return $this->xml(fn () => $sitemap->index());
    }

    public function pages(ProductSitemap $sitemap)
    {
        return $this->xml(fn () => $sitemap->pages());
    }

    public function products(string $page, ProductSitemap $sitemap)
    {
        $page = filter_var($page, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        abort_if($page === false || $page > $sitemap->pageCount(), 404);

        return $this->xml(fn () => $sitemap->productsPage($page));
    }

    private function xml(callable $render)
    {
        return response()->stream($render, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}
