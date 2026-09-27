<?php

namespace App\Support;

final class CatalogUrl
{
    public static function canonical(string $base, array $query): string
    {
        $params = [];
        foreach (['category', 'subcategory', 'childcategory', 'brand', 'tag', 'search', 'year', 'make', 'model', 'attribute', 'option', 'minPrice', 'maxPrice', 'quick_filter', 'new', 'sorting'] as $key) {
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
}
