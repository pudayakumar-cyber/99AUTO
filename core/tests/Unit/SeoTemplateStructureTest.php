<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class SeoTemplateStructureTest extends TestCase
{
    private function view(string $path): string
    {
        return file_get_contents(dirname(__DIR__, 2).'/resources/views/'.$path);
    }

    public function test_product_scripts_stay_inside_the_script_section(): void
    {
        $template = $this->view('front/catalog/product.blade.php');
        $contentSection = strpos($template, "@section('content')");
        $scriptSection = strpos($template, "@section('script')");
        $cleanup = strpos($template, "document.querySelectorAll('.ewa-rteLine')");

        $this->assertSame(1, substr_count($template, "@section('script')"));
        $this->assertNotFalse($contentSection);
        $this->assertNotFalse($scriptSection);
        $this->assertNotFalse($cleanup);
        $this->assertGreaterThan($contentSection, $scriptSection);
        $this->assertGreaterThan($scriptSection, $cleanup);
    }

    public function test_product_category_links_are_guarded_against_missing_relations(): void
    {
        $template = $this->view('front/catalog/product.blade.php');

        $this->assertStringContainsString('$hasBreadcrumbCategory', $template);
        $this->assertStringContainsString('$hasBreadcrumbSubcategory', $template);
        $this->assertStringContainsString('$hasBreadcrumbChildcategory', $template);
        $this->assertStringNotContainsString("'?category=' . \$item->category->slug", $template);
    }

    public function test_product_brand_link_requires_a_named_brand_with_a_slug(): void
    {
        $template = $this->view('front/catalog/product.blade.php');

        $this->assertStringContainsString('@if ($item->brand_id)', $template);
        $this->assertStringContainsString(
            '@if (filled($item->brand->name) && filled($item->brand->slug))',
            $template
        );
        $this->assertStringContainsString("'?brand=' . \$item->brand->slug", $template);
        $this->assertStringContainsString(
            '@elseif (filled($item->brand->name))',
            $template
        );
        $this->assertStringContainsString('<span>{{ $item->brand->name }}</span>', $template);
    }

    public function test_catalog_and_related_product_category_links_require_a_name_and_slug(): void
    {
        foreach ([
            'front/catalog/partials/grid-item.blade.php' => '$item',
            'front/catalog/partials/list-item.blade.php' => '$item',
            'front/catalog/product.blade.php' => '$related',
        ] as $path => $product) {
            $template = $this->view($path);
            $guard = '@if (filled('.$product.'->category->name) && filled('.$product.'->category->slug))';

            $this->assertStringContainsString($guard, $template, $path);
            $this->assertStringContainsString("'?category=' . ".$product.'->category->slug', $template, $path);
            $this->assertStringContainsString('@elseif (filled('.$product.'->category->name))', $template, $path);
            $this->assertStringContainsString('<span>{{ '.$product.'->category->name }}</span>', $template, $path);
        }
    }

    public function test_storefront_controls_and_pages_have_accessible_names_and_headings(): void
    {
        $layout = $this->view('master/front.blade.php');
        $trackOrder = $this->view('front/track_order.blade.php');
        $cart = $this->view('front/catalog/cart.blade.php');

        $this->assertMatchesRegularExpression('/class="announcement-banner"[^>]+aria-label=/s', $layout);
        $this->assertStringContainsString('<h1 class="h4 mb-4 text-center">', $trackOrder);
        $this->assertStringContainsString('<label class="sr-only" for="order_number">', $trackOrder);
        $this->assertStringContainsString('<h1 class="h4 mb-2">', $cart);
    }
}
