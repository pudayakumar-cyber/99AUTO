<?php
namespace Tests\Unit;

use App\Support\CatalogUrl;
use PHPUnit\Framework\TestCase;

class CatalogUrlTest extends TestCase
{
    public function test_distinct_catalog_results_keep_their_filter_and_page(): void
    {
        $base = 'https://99autoparts.ca/catalog';
        $this->assertSame($base.'?brand=bosch&page=2', CatalogUrl::canonical($base, ['page' => '2', 'brand' => 'bosch']));
        $this->assertNotSame(CatalogUrl::canonical($base, ['page' => 2]), CatalogUrl::canonical($base, ['page' => 3]));
    }

    public function test_tracking_display_and_fragment_parameters_are_excluded(): void
    {
        $this->assertSame('https://99autoparts.ca/catalog?tag=popular', CatalogUrl::canonical('https://99autoparts.ca/catalog', [
            'page' => 1, 'tag' => 'popular', 'catalog_chunk' => 2,
            'catalog_chunk_size' => 4, 'view_check' => 'list', 'utm_source' => 'email',
        ]));
    }

    public function test_values_are_encoded_and_malformed_arrays_are_ignored(): void
    {
        $this->assertSame('/catalog?search=oil%20%26%20filter', CatalogUrl::canonical('/catalog', [
            'search' => 'oil & filter', 'brand' => ['bad'], 'page' => ['bad'],
        ]));
    }

    public function test_base_and_page_only_catalog_urls_have_no_content_filters(): void
    {
        $this->assertFalse(CatalogUrl::hasContentFilters([]));
        $this->assertFalse(CatalogUrl::hasContentFilters(['page' => 3, 'view_check' => 'list']));
        $this->assertTrue(CatalogUrl::hasContentFilters(['brand' => 'bosch']));
    }

    public function test_primary_taxonomy_fitment_and_pagination_are_indexable(): void
    {
        $this->assertTrue(CatalogUrl::shouldIndex(['category' => 'brakes', 'page' => 2]));
        $this->assertTrue(CatalogUrl::shouldIndex(['brand' => 'bosch']));
        $this->assertTrue(CatalogUrl::shouldIndex(['year' => '2024', 'make' => 'Honda', 'model' => 'Civic']));
    }

    /**
     * @dataProvider temporaryFilterProvider
     */
    public function test_temporary_and_faceted_catalog_urls_are_not_indexable(string $filter): void
    {
        $this->assertFalse(CatalogUrl::shouldIndex([$filter => 'value']));
    }

    public static function temporaryFilterProvider(): array
    {
        return array_map(
            fn (string $filter): array => [$filter],
            ['tag', 'search', 'attribute', 'option', 'minPrice', 'maxPrice', 'quick_filter', 'new', 'sorting']
        );
    }

    public function test_catalog_templates_apply_the_indexation_policy_and_distinct_tag_metadata(): void
    {
        $layout = file_get_contents(dirname(__DIR__, 2).'/resources/views/master/front.blade.php');
        $catalog = file_get_contents(dirname(__DIR__, 2).'/resources/views/front/catalog/index.blade.php');

        $this->assertStringContainsString('CatalogUrl::shouldIndex($catalogQuery)', $layout);
        $this->assertStringContainsString('<meta name="robots" content="noindex, follow">', $layout);
        $this->assertStringContainsString("request()->filled('tag')", $catalog);
        $this->assertStringContainsString("__('Products tagged')", $catalog);
    }
}
