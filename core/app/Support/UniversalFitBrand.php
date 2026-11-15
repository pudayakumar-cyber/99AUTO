<?php

namespace App\Support;

use Illuminate\Support\Str;

class UniversalFitBrand
{
    private const NAMES = [
        'Lucas Oil',
        'LIQUI MOLY',
    ];

    // Only exact manufacturer-verified items can receive a vehicle-wide claim.
    // Do not infer universal compatibility from a brand or broad application.
    private const VERIFIED_PARTS = [
        'lucasoil' => [],
        'liquimoly' => [],
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

    public static function isVerifiedProduct(?string $brand, ?string $sku): bool
    {
        $sku = trim((string) $sku);
        if ($sku === '') {
            return false;
        }

        return in_array($sku, self::VERIFIED_PARTS[self::canonical($brand)] ?? [], true);
    }

    public static function verifiedSkus(): array
    {
        return array_values(array_unique(array_merge(...array_values(self::VERIFIED_PARTS))));
    }

    private static function canonical(?string $value): string
    {
        return Str::of((string) $value)
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/u', '')
            ->toString();
    }
}
