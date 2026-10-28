<?php

namespace App\Support;

final class CatalogUrl
{
    private const CONTENT_FILTERS = [
        'category', 'subcategory', 'childcategory', 'brand', 'tag', 'search',
        'year', 'make', 'model', 'attribute', 'option', 'minPrice', 'maxPrice',
        'quick_filter', 'new', 'sorting',
    ];

    private const NON_INDEXABLE_FILTERS = [
        'tag', 'search', 'attribute', 'option', 'minPrice', 'maxPrice',
        'quick_filter', 'new', 'sorting',
    ];

    public static function canonical(string $base, array $query): string
    {
        $params = [];
        foreach (self::CONTENT_FILTERS as $key) {
            if (isset($query[$key]) && is_scalar($query[$key]) && (string) $query[$key] !== '') {
                $params[$key] = (string) $query[$key];
            }
        }
        $page = isset($query['page']) && is_scalar($query['page'])
            ? filter_var($query['page'], FILTER_VALIDATE_INT) : false;
        if ($page > 1) {
            $params['page'] = $page;
        }
        return $base.($params ? '?'.http_build_query($params, '', '&', PHP_QUERY_RFC3986) : '');
    }

    public static function hasContentFilters(array $query): bool
    {
        foreach (self::CONTENT_FILTERS as $key) {
            if (self::hasValue($query, $key)) {
                return true;
            }
        }

        return false;
    }

    public static function shouldIndex(array $query): bool
    {
        foreach (self::NON_INDEXABLE_FILTERS as $key) {
            if (self::hasValue($query, $key)) {
                return false;
            }
        }

        return true;
    }

    private static function hasValue(array $query, string $key): bool
    {
        return isset($query[$key])
            && is_scalar($query[$key])
            && trim((string) $query[$key]) !== '';
    }
}
