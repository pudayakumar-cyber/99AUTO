<?php

namespace App\Support;

use App\Models\Item;

final class ProductUrl
{
    public static function for(Item $item): string
    {
        // Slugs are not unique; the ID is part of the product's public URL.
        return route('front.product', ['slug' => $item->slug, 'item_id' => $item->id]);
    }
}
