<?php

namespace App\Support;

use App\Models\Item;
use Illuminate\Support\Str;

final class ProductMetaTitle
{
    private const MAX_LENGTH = 60;

    private const SITE_SUFFIX = ' | 99 Auto Parts';

    public static function for(Item $item): string
    {
        $name = self::normalize((string) $item->name);
        $brand = self::normalize((string) optional($item->brand)->name);
        $identifier = self::normalize((string) (
            $item->product_part_number ?: $item->prod_number ?: $item->sku
        ));

        if ($name === '') {
            $name = 'Auto Part';
        }

        $qualifier = $identifier;
        if ($qualifier !== '' && mb_stripos($name, $qualifier) !== false) {
            $name = self::normalize(str_ireplace($qualifier, ' ', $name));
        } elseif ($qualifier === '' && $brand !== '' && mb_stripos($name, $brand) === false) {
            $qualifier = $brand;
        }

        // Keep enough room for a useful product phrase even when source
        // identifiers are unexpectedly long.
        $qualifier = Str::limit($qualifier, 24, '');
        $separator = $qualifier !== '' ? ' - ' : '';
        $nameLength = self::MAX_LENGTH
            - mb_strlen(self::SITE_SUFFIX)
            - mb_strlen($separator)
            - mb_strlen($qualifier);
        $name = rtrim(Str::limit($name, max(12, $nameLength), ''), " \t\n\r\0\x0B-|");

        $title = $name.$separator.$qualifier.self::SITE_SUFFIX;

        return Str::limit($title, self::MAX_LENGTH, '');
    }

    private static function normalize(string $value): string
    {
        $value = preg_replace('/\s+/u', ' ', trim($value)) ?? trim($value);

        return trim($value, " \t\n\r\0\x0B-|");
    }
}
