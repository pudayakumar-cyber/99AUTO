<?php

namespace Tests\Unit;

use App\Support\CatalogUrl;
use Illuminate\Container\Container;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Http\Request;
use Illuminate\View\Compilers\BladeCompiler;
use PHPUnit\Framework\TestCase;

class CatalogMetaDescriptionTest extends TestCase
{
    private function catalogMeta(array $query, ?string $description, ?string $keywords = null): string
    {
        $view = file_get_contents(dirname(__DIR__, 2).'/resources/views/front/catalog/index.blade.php');
        $start = strpos($view, "@section('meta')") + strlen("@section('meta')");
        $end = strpos($view, '@endsection', $start);
        $section = substr($view, $start, $end - $start);

        $previousContainer = Container::getInstance();
        $container = new Container();
        $container->instance('request', Request::create('/catalog?'.http_build_query($query)));
        Container::setInstance($container);

        $variables = [
            'setting' => (object) ['meta_keywords' => 'site keywords', 'meta_description' => 'Homepage description'],
            'category' => isset($query['category']) ? (object) [
                'meta_keywords' => $keywords,
                'meta_descriptions' => $description,
            ] : null,
            'subcategory' => null,
            'childcategory' => null,
            'brand' => isset($query['brand']) ? (object) [
                'name' => 'Bosch',
                'meta_keywords' => 'bosch keywords',
                'meta_descriptions' => 'Bosch brand description',
            ] : null,
        ];

        $compiled = (new BladeCompiler(new Filesystem(), sys_get_temp_dir()))->compileString($section);
        $compiledFile = tempnam(sys_get_temp_dir(), 'catalog-meta-');
        file_put_contents($compiledFile, $compiled);
        extract($variables);
        ob_start();
        try {
            include $compiledFile;
            return ob_get_contents();
        } finally {
            ob_end_clean();
            unlink($compiledFile);
            Container::setInstance($previousContainer);
        }
    }

    public function test_category_uses_custom_description_without_keywords(): void
    {
        $html = $this->catalogMeta(['category' => 'brakes'], 'Custom & accurate category copy');

        $this->assertStringContainsString('<meta name="description" content="Custom &amp; accurate category copy">', $html);
        $this->assertStringNotContainsString('Homepage description', $html);
    }

    public function test_blank_category_description_omits_meta_tag(): void
    {
        foreach (['brakes-b4zO', 'front-struts-EzHH'] as $slug) {
            $html = $this->catalogMeta(['category' => $slug], null);

            $this->assertStringNotContainsString('name="description"', $html);
            $this->assertStringNotContainsString('Homepage description', $html);
        }
    }

    public function test_blank_paginated_category_does_not_create_page_only_description(): void
    {
        $html = $this->catalogMeta(['category' => 'brakes', 'page' => 2], null);

        $this->assertStringNotContainsString('name="description"', $html);
    }

    public function test_category_preserves_existing_custom_description_and_keywords(): void
    {
        $html = $this->catalogMeta(['category' => 'brakes'], 'Brakes category copy', 'brakes keywords');

        $this->assertStringContainsString('<meta name="description" content="Brakes category copy">', $html);
        $this->assertStringContainsString('<meta name="keywords" content="brakes keywords">', $html);
    }

    public function test_blank_category_with_keywords_or_whitespace_description_omits_tag(): void
    {
        $this->assertStringNotContainsString(
            'name="description"',
            $this->catalogMeta(['category' => 'brakes'], '  ', 'brakes keywords')
        );
    }

    public function test_paginated_category_preserves_custom_description_suffix(): void
    {
        $html = $this->catalogMeta(['category' => 'brakes', 'page' => 3], 'Brakes category copy.');

        $this->assertStringContainsString('<meta name="description" content="Brakes category copy. Page 3.">', $html);
    }

    public function test_unfiltered_catalog_keeps_site_description(): void
    {
        $this->assertStringContainsString(
            '<meta name="description" content="Homepage description">',
            $this->catalogMeta([], null)
        );
    }

    public function test_brand_filter_keeps_brand_description_without_category(): void
    {
        $this->assertStringContainsString(
            '<meta name="description" content="Bosch brand description">',
            $this->catalogMeta(['brand' => 'bosch'], null)
        );
    }

    public function test_brand_filter_keeps_brand_description_when_category_copy_is_blank(): void
    {
        $this->assertStringContainsString(
            '<meta name="description" content="Bosch brand description">',
            $this->catalogMeta(['category' => 'brakes', 'brand' => 'bosch'], null)
        );
    }

    public function test_category_keywords_do_not_hide_brand_description_when_category_copy_is_blank(): void
    {
        $html = $this->catalogMeta(['category' => 'brakes', 'brand' => 'bosch'], null, 'brakes keywords');

        $this->assertStringContainsString('<meta name="description" content="Bosch brand description">', $html);
        $this->assertStringContainsString('<meta name="keywords" content="brakes keywords">', $html);
    }

    public function test_category_keywords_do_not_hide_tag_description_when_category_copy_is_blank(): void
    {
        $html = $this->catalogMeta(['category' => 'brakes', 'tag' => 'popular'], null, 'brakes keywords');

        $this->assertStringContainsString('name="description" content="Browse 99AutoParts products tagged popular.', $html);
    }

    public function test_paginated_brand_filter_preserves_description_when_category_copy_is_blank(): void
    {
        $html = $this->catalogMeta(['category' => 'brakes', 'brand' => 'bosch', 'page' => 2], null, 'brakes keywords');

        $this->assertStringContainsString('<meta name="description" content="Bosch brand description. Page 2.">', $html);
    }

    public function test_layout_selects_catalog_section_for_category_queries(): void
    {
        $layout = file_get_contents(dirname(__DIR__, 2).'/resources/views/master/front.blade.php');

        $this->assertTrue(CatalogUrl::hasContentFilters(['category' => 'brakes-b4zO']));
        $this->assertStringContainsString('if (! \\App\\Support\\CatalogUrl::hasContentFilters($catalogQuery))', $layout);
        $this->assertStringContainsString("@if (View::hasSection('meta'))", $layout);
        $this->assertStringContainsString("@yield('meta')", $layout);
    }
}
