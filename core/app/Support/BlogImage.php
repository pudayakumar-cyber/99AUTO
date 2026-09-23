<?php

namespace App\Support;

final class BlogImage
{
    /**
     * @return array<int, string>
     */
    public static function urls(?string $rawPhotos): array
    {
        $rawPhotos = trim((string) $rawPhotos);
        if ($rawPhotos === '') {
            return [];
        }

        $decoded = json_decode($rawPhotos, true);
        $candidates = is_array($decoded)
            ? array_values($decoded)
            : [is_string($decoded) ? $decoded : $rawPhotos];

        $urls = [];
        foreach ($candidates as $candidate) {
            if (! is_scalar($candidate)) {
                continue;
            }

            $url = StorefrontImage::url((string) $candidate);
            if ($url !== null) {
                $urls[] = $url;
            }
        }

        return array_values(array_unique($urls));
    }

    public static function url(?string $rawPhotos, string $fallback): string
    {
        return self::urls($rawPhotos)[0] ?? $fallback;
    }
}
