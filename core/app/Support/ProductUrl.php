<?php

namespace App\Support;

use App\Models\Item;

final class ProductUrl
{
    public static function for(Item $item): string
    {
        // The ID keeps the URL tied to one product if slugs are reused or changed.
        return route('front.product', ['slug' => $item->slug, 'item_id' => $item->id]);
    }
}
