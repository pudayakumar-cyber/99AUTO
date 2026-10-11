<?php

namespace App\Support;

/** Public storefront URLs, including when a queue worker's APP_URL ends in /core. */
final class KlaviyoUrl
{
    public static function to(string $path): string
    {
        $base = rtrim((string) config('app.url'), '/');
        $base = $base !== '' ? $base : rtrim(url('/'), '/');
        $base = preg_replace('#/core$#i', '', $base);

        return $base.'/'.ltrim($path, '/');
    }

    public static function image(?string $path): ?string
    {
        if (! StorefrontImage::isSafe($path)) {
            return null;
        }

        $path = trim($path);
        if (filter_var($path, FILTER_VALIDATE_URL)) {
            return $path;
        }

        return self::to('/core/public/storage/images/'.basename(parse_url($path, PHP_URL_PATH) ?? $path));
    }
}
