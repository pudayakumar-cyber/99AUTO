<?php

namespace App\Support;

use App\Models\Item;

final class ProductUrl
{
    public static function for(Item $item): string
    {
        // The ID keeps the URL tied to one product if slugs are reused or changed.
        return self::forSlugAndId($item->slug, $item->id);
    }

    public static function forSlugAndId(string $slug, int $id): string
    {
        return route('front.product', ['slug' => $slug, 'item_id' => $id]);
    }
}
