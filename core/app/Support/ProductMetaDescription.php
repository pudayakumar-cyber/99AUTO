<?php

namespace App\Support;

use App\Models\Item;
use Illuminate\Support\Str;

final class ProductMetaDescription
{
    public static function for(Item $item): string
    {
        $name = trim((string) $item->display_name);
        $details = trim(strip_tags((string) (
            $item->meta_description ?: ($item->sort_details ?: $item->details)
        )));
        $details = preg_replace('/\s+/u', ' ', $details) ?? $details;

        if ($details === '') {
            $details = 'Shop online at 99AutoParts Canada with fast shipping across Canada.';
        }

        $description = $name !== '' && mb_stripos($details, $name) === false
            ? $name.'. '.$details
            : $details;

        return Str::limit($description, 160, '');
    }
}
