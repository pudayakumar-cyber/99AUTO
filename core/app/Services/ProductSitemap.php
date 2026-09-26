<?php

namespace App\Services;

use App\Models\Item;
use App\Models\Fcategory;
use App\Models\Page;
use App\Models\Post;
use App\Models\Setting;
use App\Support\ProductUrl;

class ProductSitemap
{
    public const PAGE_SIZE = 10000;

    public function productCount(): int
    {
        return $this->products()->count();
    }

    public function pageCount(): int
    {
        return (int) ceil($this->productCount() / self::PAGE_SIZE);
    }

    public function index(): void
    {
        echo '<?xml version="1.0" encoding="UTF-8"?>'.PHP_EOL;
        echo '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'.PHP_EOL;
        $this->location(route('front.sitemap.pages'), 'sitemap');
        for ($page = 1; $page <= $this->pageCount(); $page++) {
            $this->location(route('front.sitemap.products', ['page' => $page]), 'sitemap');
        }
        echo '</sitemapindex>'.PHP_EOL;
    }

    public function pages(): void
    {
        $this->openUrls();
        foreach (['front.index', 'front.catalog', 'front.order.track'] as $name) {
            $this->location(route($name), 'url');
        }

        $setting = Setting::query()->first();
        if ($setting?->is_brands) {
            $this->location(route('front.brand'), 'url');
        }
        if ($setting?->is_contact) {
            $this->location(route('front.contact'), 'url');
        }
        if ($setting?->is_blog) {
            $this->location(route('front.blog'), 'url');
            foreach (Post::query()->select(['slug', 'updated_at'])->whereNotNull('slug')->where('slug', '<>', '')->cursor() as $post) {
                $this->location(route('front.blog.details', $post->slug), 'url', $post->updated_at?->toAtomString());
            }
        }
        if ($setting?->is_faq) {
            foreach (Fcategory::query()->select('slug')->whereStatus(1)->whereNotNull('slug')->where('slug', '<>', '')->cursor() as $category) {
                $this->location(route('front.faq.details', $category->slug), 'url');
            }
        }

        foreach (Page::query()->select('slug')->whereNotNull('slug')->where('slug', '<>', '')->cursor() as $page) {
            $this->location(route('front.page', $page->slug), 'url');
        }
        $this->closeUrls();
    }

    public function productsPage(int $page): void
    {
        $this->openUrls();
        $items = $this->products()->select(['id', 'slug', 'updated_at'])->orderBy('id')
            ->offset(($page - 1) * self::PAGE_SIZE)->limit(self::PAGE_SIZE)->cursor();
        foreach ($items as $item) {
            $this->location(ProductUrl::for($item), 'url', $item->updated_at?->toAtomString());
        }
        $this->closeUrls();
    }

    private function products()
    {
        return Item::query()->where('status', 1)->whereNotNull('slug')->where('slug', '<>', '');
    }

    private function location(string $url, string $element, ?string $lastModified = null): void
    {
        echo '<'.$element.'><loc>'.htmlspecialchars($url, ENT_XML1 | ENT_QUOTES, 'UTF-8').'</loc>';
        if ($lastModified !== null) {
            echo '<lastmod>'.htmlspecialchars($lastModified, ENT_XML1 | ENT_QUOTES, 'UTF-8').'</lastmod>';
        }
        echo '</'.$element.'>'.PHP_EOL;
    }

    private function openUrls(): void
    {
        echo '<?xml version="1.0" encoding="UTF-8"?>'.PHP_EOL;
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'.PHP_EOL;
    }

    private function closeUrls(): void
    {
        echo '</urlset>'.PHP_EOL;
    }
}
