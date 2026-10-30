<?php

namespace App\Support;

use Illuminate\Support\Str;

class UniversalFitBrand
{
    private const NAMES = [
        'Lucas Oil',
        'LIQUI MOLY',
    ];

    public static function names(): array
    {
        return self::NAMES;
    }

    public static function matches(?string $brand): bool
    {
        $brand = self::canonical($brand);

        return collect(self::NAMES)->contains(
            fn (string $name): bool => self::canonical($name) === $brand
        );
    }

    private static function canonical(?string $value): string
    {
        return Str::of((string) $value)
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/u', '')
            ->toString();
    }
}
