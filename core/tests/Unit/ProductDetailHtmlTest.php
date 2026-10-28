<?php

namespace Tests\Unit;

use App\Support\ProductDetailHtml;
use PHPUnit\Framework\TestCase;

class ProductDetailHtmlTest extends TestCase
{
    public function test_it_removes_legacy_internal_manufacturer_images(): void
    {
        $html = '<p><img src="https://99autoparts.ca/images/manufacturers/NGK_small.png" alt="Brand"></p>';

        $result = ProductDetailHtml::withoutLegacyManufacturerImages($html);

        $this->assertStringNotContainsString('NGK_small.png', $result);
        $this->assertSame('<p></p>', $result);
    }

    public function test_it_removes_legacy_supplier_manufacturer_images(): void
    {
        $html = "<img class='brand' src='https://partsavatar.ca/images/manufacturers/MOOG_small.png' alt='Brand'>";

        $this->assertSame('', ProductDetailHtml::withoutLegacyManufacturerImages($html));
    }

    public function test_it_preserves_normal_description_images(): void
    {
        $html = '<p><img src="https://cdn.example.com/products/install-guide.webp" alt="Installation guide"></p>';

        $this->assertSame($html, ProductDetailHtml::withoutLegacyManufacturerImages($html));
    }

    public function test_it_accepts_empty_content(): void
    {
        $this->assertSame('', ProductDetailHtml::withoutLegacyManufacturerImages(null));
    }
}
