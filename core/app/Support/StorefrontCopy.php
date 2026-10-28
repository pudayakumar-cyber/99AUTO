<?php

namespace App\Support;

final class StorefrontCopy
{
    public static function serviceDetails(?string $details): string
    {
        return preg_replace_callback(
            '/\bposess\b/iu',
            static fn (array $match): string => ctype_upper(substr($match[0], 0, 1))
                ? 'Possess'
                : 'possess',
            (string) $details
        ) ?? (string) $details;
    }

    public static function usefulLinksLabel(string $translatedLabel): string
    {
        return $translatedLabel === 'Usefull Links' ? 'Useful Links' : $translatedLabel;
    }
}
