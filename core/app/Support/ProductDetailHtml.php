<?php

namespace App\Support;

final class ProductDetailHtml
{
    /**
     * Remove legacy supplier logos embedded in imported descriptions.
     *
     * These URLs use the retired /images/manufacturers/*_small path and
     * generate repeated 404s. The storefront renders the product's current
     * brand image separately, so unrelated description images are preserved.
     */
    public static function withoutLegacyManufacturerImages(?string $html): string
    {
        return preg_replace(
            '~<img\b[^>]*\bsrc\s*=\s*(["\'])[^"\']*/images/manufacturers/[^"\']+\1[^>]*>~iu',
            '',
            (string) $html
        ) ?? (string) $html;
    }
}
