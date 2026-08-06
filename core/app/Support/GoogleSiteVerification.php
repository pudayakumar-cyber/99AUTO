<?php

namespace App\Support;

final class GoogleSiteVerification
{
    /**
     * @param  array<string,string|null>  $sources
     * @return array{token:?string,sources:array<string,string>}
     */
    public static function extractAndSanitize(array $sources): array
    {
        $token = null;
        $sanitized = [];
        $metaPattern = '/<meta\b(?=[^>]*\bname\s*=\s*["\']google-site-verification["\'])(?=[^>]*\bcontent\s*=\s*["\']?([A-Za-z0-9_-]+))[^>]*>/i';
        $assignmentPattern = '/\s*google-site-verification\s*=\s*["\']?([A-Za-z0-9_-]+)["\']?\s*/i';

        foreach ($sources as $key => $source) {
            $source = trim((string) $source);

            if ($token === null && preg_match($metaPattern, $source, $match)) {
                $token = $match[1];
            }
            if ($token === null && preg_match($assignmentPattern, $source, $match)) {
                $token = $match[1];
            }

            $source = preg_replace($metaPattern, '', $source) ?? $source;
            $source = preg_replace($assignmentPattern, '', $source) ?? $source;
            $sanitized[$key] = trim($source);
        }

        return ['token' => $token, 'sources' => $sanitized];
    }
}
