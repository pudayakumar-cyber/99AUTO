<?php

namespace App\Support;

use App\Models\Item;
use Illuminate\Support\Str;

final class ProductMetaDescription
{
    public static function for(Item $item): string
    {
        $name = trim((string) $item->display_name);
        $brand = mb_strtolower(trim((string) optional($item->brand)->name));
        if (in_array($brand, ['liqui moly', 'lucas oil'], true)) {
            foreach ([$item->meta_description, $item->sort_details, $item->details] as $source) {
                $copy = preg_replace('/\s+/u', ' ', trim(strip_tags((string) $source)));
                if ($copy !== '' && ! str_contains(mb_strtolower($copy), 'shop online at 99autoparts canada with fast shipping')) {
                    $description = mb_stripos($copy, $name) === false ? $name.'. '.$copy : $copy;
                    return Str::limit($description, 160, '');
                }
            }

            return Str::limit($name, 160, '');
        }

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
