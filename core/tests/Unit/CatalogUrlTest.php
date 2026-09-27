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
}
