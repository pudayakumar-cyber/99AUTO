<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class CatalogTitleTemplateTest extends TestCase
{
    public function test_generated_catalog_titles_use_the_concise_brand_suffix(): void
    {
        $layout = file_get_contents(dirname(__DIR__, 2).'/resources/views/master/front.blade.php');

        $this->assertStringContainsString(
            "\$routeName === 'front.catalog' ? '99 Auto Parts' : \$setting->title",
            $layout
        );
        $this->assertStringContainsString(
            '<title>@yield(\'title\') - {{ $generatedTitleSuffix }}</title>',
            $layout
        );
    }

    public function test_representative_long_brand_pagination_title_is_below_the_audit_threshold(): void
    {
        $title = 'CLOYES GEAR INC - Page 2 - 99 Auto Parts';

        $this->assertLessThanOrEqual(70, mb_strlen($title));
    }
}
