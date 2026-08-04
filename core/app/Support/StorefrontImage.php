<?php

namespace App\Support;

final class StorefrontImage
{
    private const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'avif', 'svg'];

    public static function isSafe(?string $rawPath): bool
    {
        $rawPath = trim((string) $rawPath);
        if ($rawPath === '' || strtolower($rawPath) === 'null') {
            return false;
        }

        $path = parse_url($rawPath, PHP_URL_PATH) ?? $rawPath;
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return in_array($extension, self::ALLOWED_EXTENSIONS, true);
    }

    public static function url(?string $rawPath, ?string $fallback = null): ?string
    {
        if (! self::isSafe($rawPath)) {
            return $fallback;
        }

        $rawPath = trim((string) $rawPath);
        if (filter_var($rawPath, FILTER_VALIDATE_URL)) {
            return $rawPath;
        }

        $path = parse_url($rawPath, PHP_URL_PATH) ?? $rawPath;
        $filename = basename($path);

        return $filename !== ''
            ? url('/core/public/storage/images/'.$filename)
            : $fallback;
    }
}
