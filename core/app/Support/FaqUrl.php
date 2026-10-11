<?php

namespace App\Support;

use App\Models\Fcategory;

class FaqUrl
{
    private static ?string $resolved = null;

    public static function primary(): string
    {
        if (self::$resolved !== null) {
            return self::$resolved;
        }

        $category = Fcategory::query()
            ->whereStatus(1)
            ->whereSlug('frequently-asked-questions')
            ->first(['slug']);

        if (! $category) {
            $category = Fcategory::query()
                ->whereStatus(1)
                ->latest('id')
                ->first(['slug']);
        }

        return self::$resolved = $category
            ? route('front.faq.details', $category->slug)
            : route('front.faq');
    }
}
